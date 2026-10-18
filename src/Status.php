<?php

namespace NineteenNinetyFour\AbandonedCart;

/**
 * Where a basket is.
 */
enum Status: string
{
    /** Still being shopped, or waiting for (or past) its reminders. */
    case Open = 'open';

    /** Ordered after a reminder. */
    case Recovered = 'recovered';

    /** Ordered before any reminder went. */
    case Ordered = 'ordered';

    /** Recovered, then the order was cancelled or returned. */
    case Cancelled = 'cancelled';

    /** Deleted by Cargo (purged, or merged into another when signing in). */
    case Deleted = 'deleted';

    public function label(): string
    {
        return __('abandoned-cart::messages.status.'.$this->value);
    }
}
