<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use NineteenNinetyFour\AbandonedCart\Status;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TrackingTest extends TestCase
{
    #[Test]
    public function a_saved_cart_is_recorded_with_its_shopper_and_contents(): void
    {
        $cart = $this->cart();

        $basket = $this->basket($cart);

        $this->assertNotNull($basket);
        $this->assertSame('reader@example.com', $basket->email);
        $this->assertSame('Ada Reader', $basket->name);
        $this->assertTrue($basket->isGuest());
        $this->assertSame('guest::reader@example.com', $basket->customer_id);
        $this->assertSame(1, $basket->items_count);
        $this->assertSame('The Lantern Ledger', $basket->items[0]['title']);
        $this->assertSame('GBP', $basket->currency);
        $this->assertSame('given', $basket->consent);
        $this->assertSame(Status::Open, $basket->status);
    }

    #[Test]
    public function an_empty_cart_is_not_recorded(): void
    {
        $cart = $this->cart(lineItems: []);

        $this->assertNull($this->basket($cart));
    }

    #[Test]
    public function an_order_from_a_cart_that_was_not_reminded_is_just_ordered(): void
    {
        $cart = $this->cart();

        $order = $this->checkout($cart);

        $basket = $this->basket($cart);
        $this->assertSame(Status::Ordered, $basket->status);
        $this->assertSame((string) $order->id(), $basket->order_id);
    }

    #[Test]
    public function a_deleted_cart_keeps_its_record(): void
    {
        $cart = $this->cart();

        $cart->delete();

        $this->assertSame(Status::Deleted, $this->basket($cart)->status);
    }
}
