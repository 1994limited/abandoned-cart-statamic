<?php

namespace NineteenNinetyFour\AbandonedCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;

/**
 * A shopper brought their basket back with the button on a reminder's page.
 */
class CartRestored
{
    use Dispatchable;

    public function __construct(public Reminder $reminder) {}
}
