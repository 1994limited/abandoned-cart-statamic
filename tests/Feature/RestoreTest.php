<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\Product;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Events\CartRestored;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Sender;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Site;

class RestoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function the_link_shows_the_basket_with_a_button_and_changes_nothing(): void
    {
        $cart = $this->cart();
        $reminder = $this->remind($cart);

        $response = $this->get($reminder->restoreUrl())
            ->assertOk()
            ->assertSee('Your basket')
            ->assertSee('The Lantern Ledger')
            ->assertSee('£9.99')
            ->assertSee('Back to my basket')
            ->assertSee($reminder->restorePostUrl())
            ->assertHeader('X-Robots-Tag', 'noindex');

        // Opening the link doesn't hand over the basket (a mail app's
        // scanner opens it too).
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'cargo-cart');
        $this->assertTrue(! $cookie || $cookie->isCleared());

        $this->assertNotNull($reminder->refresh()->viewed_at);
        $this->assertNull($reminder->clicked_at);
    }

    #[Test]
    public function the_button_makes_it_the_visitors_cart_and_goes_to_the_checkout(): void
    {
        Event::fake([CartRestored::class]);
        $cart = $this->cart();
        $reminder = $this->remind($cart);

        $this->post($reminder->restorePostUrl())
            ->assertRedirect('http://localhost/checkout')
            ->assertSessionHas('abandoned_cart_restored')
            ->assertCookie('cargo-cart', $cart->id());

        $this->assertSame($cart->id(), Cart::current()->id());
        $reminder->refresh();
        $this->assertNotNull($reminder->clicked_at);
        $this->assertSame(1, $reminder->clicks);
        Event::assertDispatched(CartRestored::class);
    }

    #[Test]
    public function the_basket_comes_back_at_todays_prices_without_what_is_sold_out(): void
    {
        $this->editions(hardback: 2);
        $cart = $this->cart([['product' => 'lantern-ledger'], ['product' => 'coral-arithmetic', 'variant' => 'Hardback']]);
        $reminder = $this->remind($cart);

        Product::find('lantern-ledger')->set('price', 1299)->save();
        $editions = Product::find('coral-arithmetic');
        $variants = $editions->get('product_variants');
        $variants['options'][0]['stock'] = 0;
        $editions->set('product_variants', $variants)->save();

        $this->get($reminder->restoreUrl())
            ->assertSee('£12.99')
            ->assertSee('£9.99')
            ->assertSee('no longer available');

        $this->post($reminder->restorePostUrl())
            ->assertSessionHas('abandoned_cart_removed', ['Coral Arithmetic']);

        $cart = Cart::find($cart->id());
        $this->assertCount(1, $cart->lineItems());
        $this->assertSame(1299, $cart->lineItems()->first()->total());
    }

    #[Test]
    public function links_are_signed_and_expire(): void
    {
        $cart = $this->cart();
        $reminder = $this->remind($cart);

        $this->get(str_replace('signature=', 'signature=x', $reminder->restoreUrl()))->assertForbidden();
        $this->post('/!/abandoned-cart/basket/'.$reminder->id)->assertForbidden();

        $url = $reminder->restoreUrl();
        $this->travel(8)->days();

        $this->get($url)->assertStatus(410)->assertSee('That link has expired');
        $this->post($reminder->restorePostUrl())->assertStatus(410);
    }

    #[Test]
    public function an_ordered_or_deleted_basket_says_so(): void
    {
        $ordered = $this->cart(email: 'ordered@example.com');
        $first = $this->remind($ordered);
        $this->checkout($ordered);

        $this->get($first->restoreUrl())->assertSee('You’ve already ordered this basket');

        $deleted = $this->cart(email: 'deleted@example.com');
        $second = $this->remind($deleted);
        Cart::find($deleted->id())->delete();

        $this->post($second->restorePostUrl())->assertStatus(410)->assertSee('That basket isn’t around any more');
    }

    #[Test]
    public function a_customer_accounts_basket_only_comes_back_for_them_signed_in(): void
    {
        $user = $this->user('member@example.com');
        $cart = Cart::make()->customer($user);
        $cart->lineItems()->create(['product' => $this->book()->id(), 'quantity' => 1]);
        $cart->set('abandoned_cart_consent', true)->save();
        Cart::forgetCurrentCart();
        $reminder = $this->remind($cart);

        $this->post($reminder->restorePostUrl())->assertOk()->assertSee('Sign in to see your basket');
        $this->assertNull($reminder->refresh()->clicked_at);

        $this->actingAs($this->user('other@example.com'))->post($reminder->restorePostUrl())->assertSee('Sign in to see your basket');

        $this->actingAs($user)->post($reminder->restorePostUrl())->assertRedirect('http://localhost/checkout');
    }

    #[Test]
    public function a_basket_comes_back_on_its_own_site(): void
    {
        config(['statamic.system.multisite' => true]);
        Site::setSites([
            'default' => ['name' => 'Dog-ear Books', 'url' => 'http://localhost/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']],
            'french' => ['name' => 'Livres Corne', 'url' => 'http://localhost/fr/', 'locale' => 'fr_FR', 'attributes' => ['currency' => 'EUR']],
        ])->save();

        $cart = Cart::make()->site('french')->customer(['name' => 'Ada', 'email' => 'ada@example.com'])->set('abandoned_cart_consent', true);
        $cart->lineItems()->create(['product' => $this->book()->id(), 'quantity' => 1]);
        $cart->save();
        Cart::forgetCurrentCart();
        $reminder = $this->remind($cart);

        $this->assertSame('EUR', Basket::query()->firstWhere('cart_id', $cart->id())->currency);

        $this->post($reminder->restorePostUrl())
            ->assertRedirect('http://localhost/fr/checkout')
            ->assertCookie('cargo-cart_french', $cart->id());
    }

    #[Test]
    public function a_test_reminder_shows_the_basket_but_cannot_bring_it_back(): void
    {
        $cart = $this->cart();
        $this->travel(2)->hours();
        $reminder = app(Sender::class)->sendTest($this->basket($cart), Settings::steps()[0], 'admin@example.com');

        $this->get($reminder->restoreUrl())->assertOk()->assertSee('This is a test reminder');
        $this->post($reminder->restorePostUrl())->assertSee('This is a test reminder');

        $this->assertFalse(Cart::hasCurrentCart());
        $this->assertSame(0, $this->basket($cart)->reminders_sent);
    }

    #[Test]
    public function the_buttons_in_emails_post_without_a_csrf_token(): void
    {
        $cart = $this->cart();
        $reminder = $this->remind($cart);

        // Laravel skips its CSRF check in tests, so run it for real.
        $csrf = new class(app(), app('encrypter')) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $post = function (string $url) use ($csrf) {
            $request = Request::create($url, 'POST');
            $request->setLaravelSession(app('session.store'));

            return $csrf->handle($request, fn () => response('passed'))->getContent();
        };

        $this->assertSame('passed', $post($reminder->restorePostUrl()));
        $this->assertSame('passed', $post($reminder->unsubscribePostUrl()));

        // Everything else still needs a token, the consent box included.
        $this->expectException(TokenMismatchException::class);
        $post(url('/!/abandoned-cart/consent'));
    }
}
