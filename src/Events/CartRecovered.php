<?php

namespace NineteenNinetyFour\AbandonedCart\Events;

use DuncanMcClean\Cargo\Contracts\Orders\Order;
use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\AbandonedCart\Models\Basket;

/**
 * A basket that was reminded became an order.
 */
class CartRecovered
{
    use Dispatchable;

    public function __construct(public Basket $basket, public Order $order) {}
}
