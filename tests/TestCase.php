<?php

namespace NineteenNinetyFour\AbandonedCart\Tests;

use DuncanMcClean\Cargo\Contracts\Cart\Cart as CartContract;
use DuncanMcClean\Cargo\Contracts\Orders\Order as OrderContract;
use DuncanMcClean\Cargo\Contracts\Products\Product as ProductContract;
use DuncanMcClean\Cargo\Discounts\DiscountServiceProvider;
use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\PaymentGateway;
use DuncanMcClean\Cargo\Facades\Product;
use DuncanMcClean\Cargo\Orders\Actions\CreateOrderFromCart;
use DuncanMcClean\Cargo\Orders\OrderServiceProvider;
use DuncanMcClean\Cargo\Payments\PaymentServiceProvider;
use DuncanMcClean\Cargo\ServiceProvider as CargoServiceProvider;
use DuncanMcClean\Cargo\Shipping\ShippingServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Sender;
use NineteenNinetyFour\AbandonedCart\ServiceProvider;
use NineteenNinetyFour\AbandonedCart\Settings;
use Statamic\Addons\Manifest;
use Statamic\Facades\Addon;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stillat\Proteus\WriterServiceProvider;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk, RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    /** Where Cargo keeps carts and orders, and blueprints are read from, during a test. */
    protected static string $content = __DIR__.'/__fixtures__/tmp';

    protected function getPackageProviders($app)
    {
        // Cargo first, as a site would have it: this addon depends on it.
        return array_merge([
            CargoServiceProvider::class,
            DiscountServiceProvider::class,
            OrderServiceProvider::class,
            PaymentServiceProvider::class,
            ShippingServiceProvider::class,
            WriterServiceProvider::class,
        ], parent::getPackageProviders($app));
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.cargo', require __DIR__.'/../vendor/duncanmcclean/statamic-cargo/config/cargo.php');

        Route::get('checkout', fn () => 'Checkout')->name('checkout');
        Route::get('checkout/confirmation', fn () => 'Confirmation')->name('checkout.confirmation');
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Statamic boots only the addons in its manifest, and the test case
        // lists just this one. Cargo is installed alongside it on a site.
        $app->make(Manifest::class)->manifest['duncanmcclean/statamic-cargo'] = [
            'id' => 'duncanmcclean/statamic-cargo',
            'slug' => 'cargo',
            'version' => '1.15.1',
            'namespace' => 'DuncanMcClean\\Cargo',
            'autoload' => 'src',
            'provider' => CargoServiceProvider::class,
        ];

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('statamic.cargo.carts.directory', static::$content.'/carts');
        $app['config']->set('statamic.cargo.orders.directory', static::$content.'/orders');
        $app['config']->set('statamic.cargo.discounts.directory', static::$content.'/discounts');
        $app['config']->set('statamic.cargo.taxes.tax_classes.directory', static::$content.'/tax-classes');
        $app['config']->set('statamic.cargo.taxes.tax_zones.directory', static::$content.'/tax-zones');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
    }

    protected function setUp(): void
    {
        $files = new Filesystem;
        $files->deleteDirectory(static::$content);
        $files->ensureDirectoryExists(static::$content.'/blueprints/collections/products');
        $files->copy(__DIR__.'/__fixtures__/blueprints/product.yaml', static::$content.'/blueprints/collections/products/product.yaml');

        parent::setUp();

        Site::setSites([
            'default' => ['name' => 'Dog-ear Books', 'url' => 'http://localhost/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']],
        ])->save();

        Blueprint::setDirectory(static::$content.'/blueprints');

        // Cargo makes these when it boots, before the directory above is set.
        foreach (['order' => 'Order', 'line_item' => 'Line Item'] as $handle => $title) {
            if (! Blueprint::find('cargo::'.$handle)) {
                Blueprint::make($handle)->setNamespace('cargo')->setContents(['title' => $title])->save();
            }
        }

        Collection::make('products')->routes('/books/{slug}')->save();

        RateLimiter::clear(Sender::RATE_KEY);
    }

    protected function tearDown(): void
    {
        Cart::forgetCurrentCart();

        // Addon settings saved by a test are written to the test app.
        (new Filesystem)->deleteDirectory(resource_path('addons'));

        parent::tearDown();

        (new Filesystem)->deleteDirectory(static::$content);
    }

    /**
     * A book with a stock level (null: stock isn't counted).
     */
    protected function book(string $id = 'lantern-ledger', ?int $stock = 10, array $data = []): ProductContract
    {
        return $this->productEntry($id, array_merge(['title' => 'The Lantern Ledger', 'price' => 999], $stock === null ? [] : ['stock' => $stock], $data));
    }

    /**
     * A book in hardback and paperback, each with its own stock.
     */
    protected function editions(string $id = 'coral-arithmetic', int $hardback = 5, int $paperback = 7, array $data = []): ProductContract
    {
        return $this->productEntry($id, array_merge([
            'title' => 'Coral Arithmetic',
            'tax_class' => 'general',
            'product_variants' => [
                'variants' => [['name' => 'Edition', 'values' => ['Hardback', 'Paperback']]],
                'options' => [
                    ['key' => 'Hardback', 'variant' => 'Hardback', 'price' => 1800, 'stock' => $hardback],
                    ['key' => 'Paperback', 'variant' => 'Paperback', 'price' => 1300, 'stock' => $paperback],
                ],
            ],
        ], $data));
    }

    protected function productEntry(string $id, array $data): ProductContract
    {
        $published = $data['published'] ?? true;
        unset($data['published']);

        Entry::make()->collection('products')->blueprint('product')->id($id)->slug($id)->published($published)->data($data)->save();

        return Product::find($id);
    }

    /**
     * A guest's cart with a book in it, as the checkout's first step leaves
     * it (name and email saved). Consent given unless said otherwise.
     */
    protected function cart(array $lineItems = [['product' => 'lantern-ledger']], ?string $email = 'reader@example.com', ?bool $consent = true, bool $current = false): CartContract
    {
        if (! Product::find('lantern-ledger')) {
            $this->book();
        }

        $cart = Cart::make()
            ->set('billing_address', ['line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR']);

        if ($email !== null) {
            $cart->customer(['name' => 'Ada Reader', 'email' => $email]);
        }

        if ($consent !== null) {
            $cart->set('abandoned_cart_consent', $consent);
        }

        foreach ($lineItems as $lineItem) {
            $cart->lineItems()->create(array_merge(['quantity' => 1], $lineItem));
        }

        $cart->save();

        $current ? Cart::setCurrent($cart) : Cart::forgetCurrentCart();

        return $cart;
    }

    protected function basket(CartContract $cart): ?Basket
    {
        return Basket::query()->firstWhere('cart_id', (string) $cart->id());
    }

    protected function checkout(CartContract $cart): OrderContract
    {
        $cart = Cart::find($cart->id());

        return app(CreateOrderFromCart::class)->handle($cart, $cart->isPaid() ? PaymentGateway::find('dummy') : null);
    }

    /**
     * Leave the cart long enough for its first reminder, and send it.
     */
    protected function remind(CartContract $cart, int $hours = 2): Reminder
    {
        $this->travel($hours)->hours();
        $this->send();

        return $this->basket($cart)->reminders()->latest('sent_at')->firstOrFail();
    }

    /**
     * Save the addon's settings, as the Control Panel does.
     */
    protected function saveSettings(array $values): void
    {
        Addon::get(Settings::ADDON)->settings()->set($values)->save();
    }

    protected function send(): int
    {
        return app(Sender::class)->sendDue();
    }

    protected function user(string $email = 'admin@example.com', array $permissions = [], bool $super = false): \Statamic\Contracts\Auth\User
    {
        if ($permissions) {
            $this->setTestRoles(['tester' => ['access cp', ...$permissions]]);
        }

        $user = User::make()->email($email)->id((string) str($email)->before('@')->slug());

        if ($permissions) {
            $user->assignRole('tester');
        }

        if ($super) {
            $user->makeSuper();
        }

        $user->save();

        return $user;
    }

    /**
     * Render Antlers as a site template would (trusted, so tags run).
     */
    protected function antlers(string $template, array $data = []): string
    {
        return (string) Antlers::parse($template, $data, true);
    }
}
