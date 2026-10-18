<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use NineteenNinetyFour\AbandonedCart\Facades\AbandonedCart;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConsentTest extends TestCase
{
    #[Test]
    public function the_tag_shows_an_unticked_box_for_opt_in(): void
    {
        $html = $this->antlers('{{ abandoned_cart:consent }}');

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="abandoned_cart_consent"', $html);
        $this->assertStringNotContainsString(' checked', $html);
        $this->assertStringContainsString('Email me a reminder if I leave items in my basket.', $html);
        $this->assertStringContainsString('/!/abandoned-cart/consent', $html);
    }

    #[Test]
    public function soft_opt_in_shows_a_box_to_say_no(): void
    {
        config(['abandoned-cart.consent' => 'soft_opt_in']);

        $html = $this->antlers('{{ abandoned_cart:consent }}');

        $this->assertStringContainsString('name="abandoned_cart_opt_out"', $html);
        $this->assertStringContainsString('Tick if you’d rather we didn’t.', $html);
    }

    #[Test]
    public function the_wording_comes_from_the_settings(): void
    {
        $this->saveSettings(['consent_text' => 'Remind me about my basket <please>']);

        $this->assertStringContainsString('Remind me about my basket &lt;please&gt;', $this->antlers('{{ abandoned_cart:consent }}'));
    }

    #[Test]
    public function ticking_the_box_records_consent_for_the_visitors_cart(): void
    {
        $cart = $this->cart(consent: null, current: true);

        $this->postJson('/!/abandoned-cart/consent', ['consent' => '1'])->assertOk()->assertJson(['consent' => true, 'saved' => true]);

        $basket = $this->basket($cart);
        $this->assertSame('given', $basket->consent);
        $this->assertSame('Email me a reminder if I leave items in my basket.', $basket->consent_text);
        $this->assertNotNull($basket->consent_at);

        $this->assertStringContainsString(' checked', $this->antlers('{{ abandoned_cart:consent }}'));

        $this->postJson('/!/abandoned-cart/consent', ['consent' => '0']);
        $this->assertSame('refused', $this->basket($cart)->consent);
    }

    #[Test]
    public function consent_given_before_there_is_a_cart_waits_for_one(): void
    {
        $this->postJson('/!/abandoned-cart/consent', ['consent' => '1'])->assertJson(['saved' => false]);

        $cart = $this->cart(consent: null);

        $this->assertSame('given', $this->basket($cart)->consent);
    }

    #[Test]
    public function the_pair_gives_the_parts_for_your_own_markup(): void
    {
        $html = $this->antlers('{{ abandoned_cart:consent }}<input type="checkbox" name="{{ name }}" data-url="{{ url }}" {{ checked ?= "checked" }}>{{ text }}|{{ mode }}{{ /abandoned_cart:consent }}');

        $this->assertStringContainsString('name="abandoned_cart_consent"', $html);
        $this->assertStringContainsString('|opt_in', $html);
    }

    #[Test]
    public function a_checkout_of_your_own_can_record_it_in_php(): void
    {
        $cart = $this->cart(consent: null);

        AbandonedCart::consent(true, Cart::find($cart->id()), 'Yes, remind me.');

        $this->assertSame('Yes, remind me.', $this->basket($cart)->consent_text);
    }

    #[Test]
    public function the_restored_tag_shows_the_message_once(): void
    {
        session()->flash('abandoned_cart_restored', 'Your basket’s back.');
        session()->flash('abandoned_cart_removed', ['Coral Arithmetic']);

        $this->assertSame('Your basket’s back.', $this->antlers('{{ abandoned_cart:restored }}'));
        $this->assertSame('Your basket’s back.: Coral Arithmetic', $this->antlers('{{ abandoned_cart:restored }}{{ message }}{{ removed }}: {{ name }}{{ /removed }}{{ /abandoned_cart:restored }}'));
    }
}
