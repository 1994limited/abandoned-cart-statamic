<?php

namespace NineteenNinetyFour\AbandonedCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;

/**
 * A reminder was emailed. $reminder->basket is the basket.
 */
class ReminderSent
{
    use Dispatchable;

    public function __construct(public Reminder $reminder) {}
}
