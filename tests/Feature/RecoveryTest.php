<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Orders\OrderStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Events\CartRecovered;
use NineteenNinetyFour\AbandonedCart\Reports;
use NineteenNinetyFour\AbandonedCart\Status;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function an_order_from_a_reminded_basket_is_recovered(): void
    {
        Event::fake([CartRecovered::class]);
        $cart = $this->cart();
        $reminder = $this->remind($cart);
        $this->post($reminder->restorePostUrl());

        $order = $this->checkout($cart);

        $basket = $this->basket($cart);
        $this->assertSame(Status::Recovered, $basket->status);
        $this->assertSame((string) $order->id(), $basket->order_id);
        $this->assertSame(999, $basket->order_total);
        Event::assertDispatched(CartRecovered::class, fn ($event) => $event->basket->is($basket));
    }

    #[Test]
    public function a_click_then_an_order_from_another_basket_counts(): void
    {
        $cart = $this->cart();
        $reminder = $this->remind($cart);
        $this->post($reminder->restorePostUrl());

        // Signing in merged it into the account's basket, and deleted it.
        Cart::find($cart->id())->delete();
        $other = $this->cart(email: 'Reader@Example.com', consent: null);
        $this->checkout($other);

        $this->assertSame(Status::Recovered, $this->basket($cart)->status);
        $this->assertSame(Status::Ordered, $this->basket($other)->status);
    }

    #[Test]
    public function but_not_after_the_attribution_window(): void
    {
        config(['abandoned-cart.attribution_days' => 7]);
        $cart = $this->cart();
        $reminder = $this->remind($cart);
        $this->post($reminder->restorePostUrl());

        $this->travel(8)->days();
        $this->checkout($this->cart(email: 'reader@example.com'));

        $this->assertSame(Status::Open, $this->basket($cart)->status);
    }

    #[Test]
    public function a_cancelled_order_no_longer_counts(): void
    {
        $cart = $this->cart();
        $this->remind($cart);
        $order = $this->checkout($cart);

        $order->status(OrderStatus::Cancelled)->save();

        $this->assertSame(Status::Cancelled, $this->basket($cart)->status);
    }

    #[Test]
    public function the_reports_add_it_up(): void
    {
        $recovered = $this->cart(email: 'a@example.com');
        $this->cart(email: 'b@example.com');
        $this->cart(email: 'c@example.com', consent: null);
        $this->checkout($this->cart(email: 'd@example.com'));

        $reminder = $this->remind($recovered);
        $this->post($reminder->restorePostUrl());
        $this->checkout($recovered);

        $stats = app(Reports::class)->stats(now()->subDays(30));

        $this->assertSame(3, $stats['abandoned']);
        $this->assertSame(2, $stats['emailed']);
        $this->assertSame(1, $stats['not_emailed']);
        $this->assertSame(1, $stats['clicked']);
        $this->assertSame(1, $stats['recovered']);
        $this->assertSame('50%', $stats['recovery_rate']);
        $this->assertSame(['£9.99'], $stats['revenue']);
        $this->assertSame('no_consent', $stats['reasons'][0]['reason']);
    }
}
