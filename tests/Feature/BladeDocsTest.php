<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Product;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;

/**
 * The Blade examples in the docs, rendered for real. Each file in
 * tests/__fixtures__/views/docs is in the docs word for word.
 */
class BladeDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        view()->addNamespace('docs', __DIR__.'/../__fixtures__/views/docs');
    }

    protected function render(string $view, array $data = []): string
    {
        return view("docs::{$view}", $data)->render();
    }

    public static function examples(): array
    {
        return collect(glob(__DIR__.'/../__fixtures__/views/docs/*.blade.php'))
            ->mapWithKeys(fn ($path) => [basename($path) => [$path]])
            ->all();
    }

    /**
     * Bring a basket back with a reminder's button, optionally after its
     * hardback sold out.
     */
    protected function restore(bool $soldOut = false): void
    {
        $this->editions(hardback: 2);
        $reminder = $this->remind($this->cart([['product' => 'lantern-ledger'], ['product' => 'coral-arithmetic', 'variant' => 'Hardback']]));

        if ($soldOut) {
            $editions = Product::find('coral-arithmetic');
            $variants = $editions->get('product_variants');
            $variants['options'][0]['stock'] = 0;
            $editions->set('product_variants', $variants)->save();
        }

        $this->post($reminder->restorePostUrl())->assertRedirect('http://localhost/checkout');
    }

    #[Test]
    #[DataProvider('examples')]
    public function each_example_is_in_the_docs(string $path): void
    {
        $docs = collect([...glob(__DIR__.'/../../docs/*.md'), __DIR__.'/../../README.md'])->map(fn ($file) => file_get_contents($file))->implode("\n");

        $this->assertStringContainsString(trim(file_get_contents($path)), $docs, basename($path).' is not in the docs word for word.');
    }

    #[Test]
    public function the_readme_example(): void
    {
        $html = $this->render('readme');

        $this->assertStringContainsString('<label class="abandoned-cart-consent"', $html);
        $this->assertStringContainsString('name="abandoned_cart_consent"', $html);
        $this->assertStringContainsString('data-abandoned-cart-consent="http://localhost/!/abandoned-cart/consent"', $html);
        $this->assertStringContainsString('<script>', $html);
    }

    #[Test]
    public function the_box_next_to_the_email_field(): void
    {
        $this->saveSettings(['consent_text' => 'Remind me about my basket <please>']);

        $html = $this->render('checkout');

        $this->assertStringContainsString('<input type="email" id="email" name="email" autocomplete="email" required>', $html);
        $this->assertStringContainsString('Remind me about my basket &lt;please&gt;', $html);
    }

    #[Test]
    public function the_box_for_signed_in_customers(): void
    {
        $this->assertSame('', trim($this->render('cart-page')));

        $this->actingAs($this->user('member@example.com'));

        $this->assertStringContainsString('name="abandoned_cart_consent"', $this->render('cart-page'));
    }

    #[Test]
    public function your_own_markup(): void
    {
        $html = $this->render('own-markup');
        $this->assertStringContainsString('name="abandoned_cart_consent" value="1" ', $html);
        $this->assertStringNotContainsString('checked', $html);
        $this->assertStringContainsString('data-consent-url="http://localhost/!/abandoned-cart/consent" data-consent-mode="opt_in"', $html);

        // Agreed before there was a basket: it waits in the session, and shows.
        $this->postJson('/!/abandoned-cart/consent', ['consent' => '1'])->assertJson(['saved' => false]);
        $this->assertStringContainsString('value="1" checked', $this->render('own-markup'));

        config(['abandoned-cart.consent' => 'soft_opt_in']);
        $this->saveSettings(['opt_out_text' => "Don't email me <please>"]);

        $html = $this->render('own-markup');
        $this->assertStringContainsString('name="abandoned_cart_opt_out"', $html);
        $this->assertStringContainsString('data-consent-mode="soft_opt_in"', $html);
        $this->assertStringContainsString('Don&#039;t email me &lt;please&gt;', $html);
    }

    #[Test]
    public function your_basket_is_back(): void
    {
        $this->assertSame('', trim($this->render('restored')));
        $this->assertSame('', trim($this->render('restored-removed')));

        $this->restore();

        $this->assertStringContainsString('<p class="flash">Your basket’s back. Pick up where you left off.</p>', $this->render('restored'));
        $this->assertStringContainsString('<div class="flash">Your basket’s back. Pick up where you left off.</div>', $this->render('restored-removed'));
    }

    #[Test]
    public function your_basket_is_back_without_what_sold_out(): void
    {
        $this->restore(soldOut: true);

        $this->assertStringContainsString('<div class="flash flash--warning">Your basket’s back. We took out what’s no longer available: Coral Arithmetic.</div>', $this->render('restored-removed'));
    }

    #[Test]
    public function the_consent_field_in_your_own_checkout(): void
    {
        Blueprint::find('cargo::order')->setContents([
            'title' => 'Order',
            'tabs' => ['main' => ['sections' => [['fields' => [['handle' => 'abandoned_cart_consent', 'field' => ['type' => 'toggle']]]]]]],
        ])->save();
        $cart = $this->cart(consent: null, current: true);

        $html = $this->render('consent-field');
        $this->assertStringContainsString('action="http://localhost/!/cargo/cart"', $html);
        $this->assertStringContainsString('<input type="email" name="email" value="reader@example.com"', $html);
        $this->assertStringContainsString('<input type="hidden" name="abandoned_cart_consent" value="0">', $html);
        $this->assertStringContainsString('<input type="checkbox" name="abandoned_cart_consent" value="1" >', $html);

        $this->patch('/!/cargo/cart', ['abandoned_cart_consent' => '1']);
        $this->assertSame('given', $this->basket($cart)->consent);
        $this->assertStringContainsString('name="abandoned_cart_consent" value="1" checked', $this->render('consent-field'));
    }
}
