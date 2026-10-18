<?php

namespace NineteenNinetyFour\AbandonedCart;

use DuncanMcClean\Cargo\Contracts\Cart\Cart;
use DuncanMcClean\Cargo\Contracts\Orders\Order;
use Illuminate\Support\Facades\DB;
use NineteenNinetyFour\AbandonedCart\Cargo\Carts;
use NineteenNinetyFour\AbandonedCart\Events\CartRecovered;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use Throwable;

/**
 * Keeping up with Cargo: each cart's record follows the cart as it
 * changes, and stops when it's ordered or deleted.
 */
class Tracker
{
    /** Where consent given before there's a cart waits (the session). */
    public const SESSION_KEY = 'abandoned_cart_consent';

    /** A cart field for consent, for checkouts that add one to the order blueprint. */
    public const FIELD = 'abandoned_cart_consent';

    public function __construct(protected Carts $carts) {}

    /**
     * A cart was saved: bring its record up to date.
     */
    public function track(Cart $cart): ?Basket
    {
        if (! $cart->id()) {
            return null;
        }

        $basket = Basket::query()->firstWhere('cart_id', (string) $cart->id());

        // Ordered, or gone: nothing more to follow.
        if ($basket && $basket->status !== Status::Open) {
            return $basket;
        }

        $items = $this->carts->items($cart);

        // An empty cart nobody has a record of isn't worth one.
        if (! $basket && ! $items) {
            return null;
        }

        $basket ??= new Basket(['cart_id' => (string) $cart->id(), 'status' => Status::Open]);
        $site = $cart->site();
        $updatedAt = $this->carts->updatedAt($cart);
        $changed = ! $basket->exists || ! $basket->cart_updated_at || $basket->cart_updated_at->timestamp !== $updatedAt->timestamp;

        $basket->fill([
            'site' => $site->handle(),
            'locale' => $site->lang(),
            'email' => $this->carts->email($cart),
            'name' => $this->carts->name($cart),
            'user_id' => $this->carts->userId($cart),
            'customer_id' => $this->carts->customerId($cart),
            'items' => $items,
            'items_count' => $this->carts->itemsCount($cart),
            'total' => (int) $cart->grandTotal(),
            'currency' => $this->carts->currency($site),
            'cart_updated_at' => $updatedAt,
        ]);

        // Changed since it was last looked at: whatever stopped it being
        // emailed may not apply now.
        if ($changed) {
            $basket->skip_reason = null;
        }

        $this->readConsent($cart, $basket);

        $basket->save();

        return $basket;
    }

    /**
     * Record whether a cart's shopper agreed to reminders, with the wording
     * they saw.
     */
    public function consent(Cart $cart, bool $given, ?string $text = null): ?Basket
    {
        $basket = $cart->id() ? Basket::query()->firstWhere('cart_id', (string) $cart->id()) : null;

        if (! $basket && $cart->id()) {
            $basket = $this->track($cart) ?? new Basket([
                'cart_id' => (string) $cart->id(),
                'site' => $cart->site()->handle(),
                'locale' => $cart->site()->lang(),
                'status' => Status::Open,
                'cart_updated_at' => $this->carts->updatedAt($cart),
            ]);
        }

        if (! $basket) {
            return null;
        }

        $this->setConsent($basket, $given, $text);

        if ($given) {
            $basket->skip_reason = null;
        }

        $basket->save();

        return $basket;
    }

    /**
     * An order was placed. From a basket that was reminded, it's recovered;
     * otherwise it was just ordered. Either way no more reminders go to
     * that address for baskets left before it.
     */
    public function orderPlaced(Order $order): void
    {
        $cartId = $order->cart();
        $email = null;

        try {
            $email = $order->customer()?->email();
        } catch (Throwable) {
        }

        $email = is_string($email) && $email !== '' ? Basket::normaliseEmail($email) : null;

        DB::connection((new Basket)->getConnectionName())->transaction(function () use ($order, $cartId, $email) {
            $basket = $cartId ? Basket::query()->where('cart_id', (string) $cartId)->lockForUpdate()->first() : null;
            $recovered = null;

            if ($basket && $basket->status === Status::Open) {
                $this->linkOrder($basket, $order, $basket->reminders()->where('test', false)->exists() ? Status::Recovered : Status::Ordered);

                if ($basket->status === Status::Recovered) {
                    $recovered = $basket;
                }
            }

            if ($email) {
                // A reminder clicked lately, for a basket that's since gone
                // (merged into the account's own when signing in, say): the
                // order counts for it.
                if (! $recovered) {
                    $clicked = Basket::query()
                        ->where('email', $email)
                        ->whereIn('status', [Status::Open->value, Status::Deleted->value])
                        ->when($basket, fn ($query) => $query->whereKeyNot($basket->id))
                        ->whereHas('reminders', fn ($query) => $query
                            ->where('test', false)
                            ->where('clicked_at', '>=', now()->subDays(max(0, (int) config('abandoned-cart.attribution_days', 7)))))
                        ->latest('cart_updated_at')
                        ->lockForUpdate()
                        ->first();

                    if ($clicked) {
                        $this->linkOrder($clicked, $order, Status::Recovered);
                        $recovered = $clicked;
                    }
                }

                // Other baskets left before this order: no more reminders.
                Basket::query()
                    ->where('email', $email)
                    ->where('status', Status::Open->value)
                    ->when($basket, fn ($query) => $query->whereKeyNot($basket->id))
                    ->where('cart_updated_at', '<=', now())
                    ->update(['skip_reason' => 'ordered_since']);
            }

            if ($recovered) {
                DB::afterCommit(fn () => CartRecovered::dispatch($recovered, $order));
            }
        });
    }

    /**
     * A recovered order was cancelled or returned: it no longer counts.
     */
    public function orderCancelled(Order $order): void
    {
        Basket::query()
            ->where('order_id', (string) $order->id())
            ->where('status', Status::Recovered->value)
            ->update(['status' => Status::Cancelled->value]);
    }

    /**
     * Cargo deleted a cart (purged, or merged into another). Its record
     * stays for the reports; nothing more is sent.
     */
    public function cartDeleted(Cart $cart): void
    {
        if (! $cart->id()) {
            return;
        }

        Basket::query()
            ->where('cart_id', (string) $cart->id())
            ->where('status', Status::Open->value)
            ->update(['status' => Status::Deleted->value, 'claimed_at' => null]);
    }

    protected function linkOrder(Basket $basket, Order $order, Status $status): void
    {
        $basket->forceFill([
            'status' => $status,
            'order_id' => (string) $order->id(),
            'order_reference' => $order->orderNumber() ? '#'.$order->orderNumber() : null,
            'order_total' => (int) $order->grandTotal(),
            'ordered_at' => now(),
            'claimed_at' => null,
        ])->save();
    }

    /**
     * Consent from a cart field, or given on this visit before the cart
     * was saved (it waits in the session).
     */
    protected function readConsent(Cart $cart, Basket $basket): void
    {
        $value = $cart->get(self::FIELD);

        if ($value !== null && $value !== '') {
            $given = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($given !== null && ($basket->consent === null || ($basket->consent === 'given') !== $given)) {
                $this->setConsent($basket, $given, Consent::text());
            }

            return;
        }

        if ($basket->consent !== null) {
            return;
        }

        try {
            $pending = app()->bound('session') && request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;
        } catch (Throwable) {
            $pending = null;
        }

        if (is_array($pending) && isset($pending['given'])) {
            $this->setConsent($basket, (bool) $pending['given'], $pending['text'] ?? null);
        }
    }

    protected function setConsent(Basket $basket, bool $given, ?string $text): void
    {
        $basket->consent = $given ? 'given' : 'refused';
        $basket->consent_text = $text !== null ? mb_substr($text, 0, 2000) : null;
        $basket->consent_at = now();
    }
}
