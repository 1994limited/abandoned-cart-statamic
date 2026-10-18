<?php

namespace NineteenNinetyFour\AbandonedCart;

use DuncanMcClean\Cargo\Contracts\Cart\Cart;
use DuncanMcClean\Cargo\Facades\Cart as Carts;
use DuncanMcClean\Cargo\Orders\LineItem;
use NineteenNinetyFour\AbandonedCart\Cargo\Carts as CartReader;
use NineteenNinetyFour\AbandonedCart\Events\CartRestored;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Throwable;

/**
 * Bringing a basket back from the link in a reminder: it becomes the
 * visitor's cart (on its own site), re-priced, without anything that's
 * sold out or gone, and with the reminder's code.
 */
class Restorer
{
    public function __construct(protected CartReader $carts) {}

    /**
     * Why the basket can't be brought back ('ordered', 'gone', 'sign_in'),
     * or null if it can.
     */
    public function problem(Reminder $reminder, ?Cart $cart): ?string
    {
        $basket = $reminder->basket;

        if ($basket->status !== Status::Open && $basket->status !== Status::Deleted) {
            return 'ordered';
        }

        if (! $cart) {
            return 'gone';
        }

        try {
            if ($cart->order()) {
                return 'ordered';
            }
        } catch (Throwable) {
        }

        // A customer account's basket only comes back for that customer.
        if ($basket->user_id !== null && (string) User::current()?->id() !== $basket->user_id) {
            return 'sign_in';
        }

        return null;
    }

    public function cart(Reminder $reminder): ?Cart
    {
        return $this->carts->find($reminder->basket->cart_id);
    }

    /**
     * The basket's lines as they'd be now: today's price, and whether each
     * can still be bought.
     *
     * @return array<int, array{title: string, variant: string|null, quantity: int, price: string, was: string|null, available: bool}>
     */
    public function lines(Cart $cart): array
    {
        $site = $cart->site();

        return $cart->lineItems()->map(function (LineItem $item) use ($site) {
            $product = $this->safely(fn () => $item->product());
            $variant = $item->variant ? $this->safely(fn () => $item->variant()) : null;
            $now = $variant ? $variant->price() : $product?->price();
            $then = (int) $item->unitPrice();

            return [
                'title' => (string) ($product?->value('title') ?? $item->product),
                'variant' => $variant ? (string) $variant->name() : null,
                'quantity' => (int) $item->quantity(),
                'price' => $this->carts->format(is_numeric($now) ? (int) $now * (int) $item->quantity() : (int) $item->total(), $site),
                'was' => is_numeric($now) && $then > 0 && (int) $now !== $then ? $this->carts->format($then * (int) $item->quantity(), $site) : null,
                'available' => $this->carts->available($item),
            ];
        })->values()->all();
    }

    /**
     * Make the basket the visitor's cart. Returns the names of anything
     * taken out (sold out, or no longer sold).
     *
     * @return array<int, string>
     */
    public function restore(Reminder $reminder, Cart $cart): array
    {
        // Cargo keeps a cart per site, named by the site it reads from the
        // request: an email link has none, so set it.
        Site::setCurrent($cart->site()->handle());

        $removed = [];

        foreach ($cart->lineItems()->all() as $item) {
            if (! $this->carts->available($item)) {
                $product = $this->safely(fn () => $item->product());
                $removed[] = (string) ($product?->value('title') ?? $item->product);
                $cart->lineItems()->remove($item->id());
            }
        }

        if ($reminder->discount_code && ! $cart->get('discount_code')) {
            $cart->set('discount_code', $reminder->discount_code);
        }

        // Today's prices, and the code checked.
        $cart->recalculate();
        $cart->save();

        Carts::setCurrent($cart);

        $reminder->forceFill([
            'clicked_at' => $reminder->clicked_at ?? now(),
            'viewed_at' => $reminder->viewed_at ?? now(),
            'clicks' => $reminder->clicks + 1,
        ])->save();

        CartRestored::dispatch($reminder);

        return $removed;
    }

    protected function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
