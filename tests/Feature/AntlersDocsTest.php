<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\Product;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Facades\AbandonedCart;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Step;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use NineteenNinetyFour\AbandonedCart\Widgets\AbandonedCartWidget;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;

/**
 * The Antlers, PHP and YAML examples in the docs, run as they're written.
 */
class AntlersDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Cargo's prebuilt checkout partials, as a site that published them has.
        view()->addLocation(__DIR__.'/../../vendor/duncanmcclean/statamic-cargo/resources/views');
    }

    /**
     * @return array<int, string>
     */
    protected function blocks(string $doc, string $language): array
    {
        $path = $doc === 'README.md' ? __DIR__.'/../../README.md' : __DIR__.'/../../docs/'.$doc;

        preg_match_all('/```'.$language.'\n(.*?)\n```/s', file_get_contents($path), $matches);

        return $matches[1];
    }

    /**
     * Bring a basket back with a reminder's button, after its hardback
     * sold out, as the "Your basket's back" examples expect.
     */
    protected function restoreWithSomethingSoldOut(): void
    {
        $this->editions(hardback: 2);
        $reminder = $this->remind($this->cart([['product' => 'lantern-ledger'], ['product' => 'coral-arithmetic', 'variant' => 'Hardback']]));

        $editions = Product::find('coral-arithmetic');
        $variants = $editions->get('product_variants');
        $variants['options'][0]['stock'] = 0;
        $editions->set('product_variants', $variants)->save();

        $this->post($reminder->restorePostUrl())->assertRedirect('http://localhost/checkout');
    }

    #[Test]
    public function the_readme_example_renders(): void
    {
        $blocks = $this->blocks('README.md', 'antlers');
        $this->assertCount(1, $blocks);

        $html = $this->antlers($blocks[0]);

        $this->assertStringContainsString('name="abandoned_cart_consent"', $html);
        $this->assertStringContainsString('data-abandoned-cart-consent="http://localhost/!/abandoned-cart/consent"', $html);
        $this->assertStringContainsString('Email me a reminder if I leave items in my basket.', $html);
    }

    #[Test]
    public function every_antlers_example_in_templates_renders(): void
    {
        $blocks = $this->blocks('templates.md', 'antlers');
        $this->assertCount(5, $blocks);

        [$checkout, $signedIn, $ownMarkup, $restored, $restoredPair] = $blocks;

        // Cargo's customer step, with the box under the email field.
        $html = $this->antlers($checkout);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('<label class="abandoned-cart-consent"', $html);
        $this->assertLessThan(strpos($html, 'abandoned-cart-consent'), strpos($html, 'name="email"'));

        // The basket page, for signed-in customers only.
        $this->assertSame('', trim($this->antlers($signedIn, ['logged_in' => false])));
        $this->assertStringContainsString('name="abandoned_cart_consent"', $this->antlers($signedIn, ['logged_in' => true]));

        // Your own markup, showing what the visitor said.
        $this->cart(current: true);
        $html = $this->antlers($ownMarkup);
        $this->assertStringContainsString('name="abandoned_cart_consent" value="1" checked', $html);
        $this->assertStringContainsString('data-consent-url="http://localhost/!/abandoned-cart/consent" data-consent-mode="opt_in"', $html);
        $this->assertStringContainsString('Email me a reminder if I leave items in my basket.', $html);

        config(['abandoned-cart.consent' => 'soft_opt_in']);
        $html = $this->antlers($ownMarkup);
        $this->assertStringContainsString('name="abandoned_cart_opt_out" value="1" ', $html);
        $this->assertStringNotContainsString('checked', $html);
        $this->assertStringContainsString('Tick if you’d rather we didn’t.', $html);
        config(['abandoned-cart.consent' => 'opt_in']);

        // "Your basket's back": nothing until the button's been used.
        $this->assertSame('', trim($this->antlers($restored)));
        $this->assertSame('', trim($this->antlers($restoredPair)));

        $this->restoreWithSomethingSoldOut();

        $this->assertStringContainsString('<p class="flash">Your basket’s back. We took out what’s no longer available: Coral Arithmetic.</p>', $this->antlers($restored));
        $this->assertStringContainsString('<div class="flash flash--warning">Your basket’s back. We took out what’s no longer available: Coral Arithmetic.</div>', $this->antlers($restoredPair));
    }

    #[Test]
    public function the_consent_field_example_saves_the_answer(): void
    {
        [$yaml] = $this->blocks('consent.md', 'yaml');
        Blueprint::find('cargo::order')->setContents([
            'title' => 'Order',
            'tabs' => ['main' => ['sections' => [['fields' => YAML::parse($yaml)]]]],
        ])->save();

        $cart = $this->cart(consent: null, current: true);
        $blocks = $this->blocks('consent.md', 'antlers');
        $this->assertCount(1, $blocks);

        $html = $this->antlers($blocks[0]);
        $this->assertStringContainsString('action="http://localhost/!/cargo/cart"', $html);
        $this->assertStringContainsString('value="reader@example.com"', $html);
        $this->assertStringContainsString('<input type="hidden" name="abandoned_cart_consent" value="0">', $html);
        $this->assertStringContainsString('<input type="checkbox" name="abandoned_cart_consent" value="1" >', $html);
        $this->assertStringContainsString('Email me a reminder if I leave items in my basket.', $html);

        // Sent ticked, then unticked (only the hidden field's 0).
        $this->patch('/!/cargo/cart', ['email' => 'reader@example.com', 'abandoned_cart_consent' => '1']);
        $this->assertSame('given', $this->basket($cart)->consent);
        $this->assertSame('Email me a reminder if I leave items in my basket.', $this->basket($cart)->consent_text);
        $this->assertStringContainsString('name="abandoned_cart_consent" value="1" checked', $this->antlers($blocks[0]));

        $this->patch('/!/cargo/cart', ['email' => 'reader@example.com', 'abandoned_cart_consent' => '0']);
        $this->assertSame('refused', $this->basket($cart)->consent);
    }

    #[Test]
    public function the_consent_php_example_runs(): void
    {
        $visitors = $this->cart(email: 'visitor@example.com', consent: null, current: true);
        $cart = Cart::find($this->cart(email: 'other@example.com', consent: null)->id());
        Cart::setCurrent(Cart::find($visitors->id()));

        eval($this->blocks('consent.md', 'php')[0]);

        $this->assertSame('given', $this->basket($visitors)->consent);
        $this->assertSame('refused', $this->basket($cart)->consent);
        $this->assertSame('Email me a reminder if I leave items in my basket.', $this->basket($cart)->consent_text);
    }

    #[Test]
    public function the_php_api_examples_run(): void
    {
        $this->cart(consent: null, current: true);
        $this->cart(email: 'someone@example.com');

        [$api, $query] = $this->blocks('php-api.md', 'php');

        eval($api);

        $this->assertFalse(AbandonedCart::isUnsubscribed('reader@example.com'));
        $this->assertSame(['someone@example.com'], Basket::query()->pluck('email')->all());

        $this->assertSame(0, (int) eval(str_replace('Basket::query()', 'return Basket::query()', $query)));
    }

    #[Test]
    public function the_config_examples_are_valid(): void
    {
        [$reminders] = $this->blocks('configuration.md', 'php');
        $config = eval('return ['.$reminders.'];');
        config(['abandoned-cart.reminders' => $config['reminders']]);

        $steps = Settings::steps();
        $this->assertSame([1, 24, 72], array_map(fn (Step $step) => $step->delay, $steps));
        $this->assertSame([null, 'existing', 'generate'], array_map(fn (Step $step) => $step->discountMode, $steps));
        $this->assertSame('come-back', $steps[1]->discountHandle);
        $this->assertSame(10.0, $steps[2]->discountValue);

        [$widgets] = $this->blocks('control-panel.md', 'php');
        $cp = eval('return ['.$widgets.'];');
        $this->assertSame(AbandonedCartWidget::handle(), $cp['widgets'][0]['type']);
    }
}
