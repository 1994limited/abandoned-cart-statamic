<?php

namespace NineteenNinetyFour\AbandonedCart;

use DuncanMcClean\Cargo\Contracts\Cart\Cart;
use DuncanMcClean\Cargo\Facades\Cart as Carts;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Models\Suppression;

/**
 * The addon's PHP API (the AbandonedCart facade): recording consent from a
 * checkout of your own, unsubscribing and forgetting people.
 */
class AbandonedCart
{
    public function __construct(protected Tracker $tracker, protected Discounts $discounts) {}

    /**
     * Record whether the shopper agreed to reminders, for a cart (the
     * visitor's own, if none is given). The wording they saw is kept with
     * it; null keeps the wording in the settings.
     */
    public function consent(bool $given, ?Cart $cart = null, ?string $text = null): ?Basket
    {
        $cart ??= Carts::hasCurrentCart() ? Carts::current() : null;

        if (! $cart || ! $cart->id()) {
            return null;
        }

        return $this->tracker->consent($cart, $given, $text ?? Consent::text());
    }

    /**
     * Never email this address again, unless they tick the box to ask for
     * reminders afterwards (or lift() takes it off the list).
     */
    public function unsubscribe(string $email): void
    {
        Suppression::add($email);

        Basket::query()
            ->where('email', Basket::normaliseEmail($email))
            ->where('status', Status::Open->value)
            ->update(['skip_reason' => 'unsubscribed']);
    }

    /**
     * Unsubscribe an address known only by its hash (the link in an old
     * email, after the address was removed from the records).
     */
    public function unsubscribeHash(string $hash): void
    {
        Suppression::addHash($hash);
    }

    /**
     * Take an address off the unsubscribed list.
     */
    public function lift(string $email): void
    {
        Suppression::lift($email);
    }

    public function isUnsubscribed(string $email): bool
    {
        return Suppression::blocks($email);
    }

    /**
     * Delete everything kept about an address: its baskets, their reminders
     * and any unused one-off codes. The unsubscribed list keeps its hash,
     * so it still isn't emailed. Returns how many baskets were deleted.
     */
    public function forget(string $email): int
    {
        $baskets = Basket::query()->where('email', Basket::normaliseEmail($email))->get();

        Reminder::query()
            ->whereIn('abandoned_cart_id', $baskets->modelKeys())
            ->where('discount_generated', true)
            ->pluck('discount_handle')
            ->each(fn ($handle) => $this->discounts->deleteUnused($handle));

        Reminder::query()->whereIn('abandoned_cart_id', $baskets->modelKeys())->delete();

        return Basket::query()->whereKey($baskets->modelKeys())->delete();
    }

    /**
     * Send no more reminders for a basket (until it changes).
     */
    public function stop(Basket $basket): void
    {
        $basket->forceFill(['skip_reason' => 'stopped'])->save();
    }
}
