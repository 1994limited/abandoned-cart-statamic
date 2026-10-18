<?php

namespace NineteenNinetyFour\AbandonedCart\Cargo;

use Carbon\CarbonInterface;
use DuncanMcClean\Cargo\Contracts\Cart\Cart;
use DuncanMcClean\Cargo\Customers\GuestCustomer;
use DuncanMcClean\Cargo\Facades\Cart as CargoCarts;
use DuncanMcClean\Cargo\Orders\LineItem;
use DuncanMcClean\Cargo\Support\Money;
use Illuminate\Support\Carbon;
use Statamic\Sites\Site;
use Throwable;

/**
 * Reading Cargo's carts. Nothing here saves one: Cargo puts a cart in the
 * visitor's cookie whenever it's saved, so saving someone else's basket
 * from a web request would hand it to whoever made the request.
 */
class Carts
{
    public function find(string $id): ?Cart
    {
        try {
            return CargoCarts::find($id);
        } catch (Throwable) {
            return null;
        }
    }

    public function email(Cart $cart): ?string
    {
        try {
            $email = $cart->customer()?->email();
        } catch (Throwable) {
            return null;
        }

        return is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) ? mb_strtolower(trim($email)) : null;
    }

    public function name(Cart $cart): ?string
    {
        try {
            $customer = $cart->customer();
            $name = $customer?->name();
        } catch (Throwable) {
            return null;
        }

        $name = is_string($name) ? trim($name) : '';

        return $name !== '' && $name !== $this->email($cart) ? mb_substr($name, 0, 255) : null;
    }

    /**
     * The customer account's id, or null for a guest.
     */
    public function userId(Cart $cart): ?string
    {
        try {
            $customer = $cart->customer();
        } catch (Throwable) {
            return null;
        }

        return $customer && ! $customer instanceof GuestCustomer ? (string) $customer->id() : null;
    }

    /**
     * Cargo's id for the customer ("guest::email" for a guest), as its
     * discounts match it.
     */
    public function customerId(Cart $cart): ?string
    {
        try {
            $id = $cart->customer()?->id();
        } catch (Throwable) {
            return null;
        }

        return $id === null ? null : (string) $id;
    }

    /**
     * When the cart last changed. Cargo keeps it as a timestamp (files) or
     * a date (database).
     */
    public function updatedAt(Cart $cart): Carbon
    {
        $value = $cart->get('updated_at');

        try {
            return match (true) {
                $value instanceof CarbonInterface => Carbon::instance($value),
                is_numeric($value) => Carbon::createFromTimestamp((int) $value),
                is_string($value) && $value !== '' => Carbon::parse($value),
                default => now(),
            };
        } catch (Throwable) {
            return now();
        }
    }

    /**
     * What's in the cart, kept with the record so it can be shown after
     * Cargo has deleted the cart.
     *
     * @return array<int, array{product: string, variant: string|null, title: string, variant_name: string|null, quantity: int, total: int}>
     */
    public function items(Cart $cart): array
    {
        return $cart->lineItems()->map(function (LineItem $item) {
            $product = $this->safely(fn () => $item->product());
            $variant = $this->safely(fn () => $item->variant());

            return [
                'product' => (string) ($item->product ?? $product?->id()),
                'variant' => $variant ? (string) $variant->key() : ($item->variant ?: null),
                'title' => (string) ($product?->value('title') ?? $item->product),
                'variant_name' => $variant ? (string) $variant->name() : null,
                'quantity' => (int) $item->quantity(),
                'total' => (int) $item->total(),
            ];
        })->values()->all();
    }

    public function itemsCount(Cart $cart): int
    {
        return (int) $cart->lineItems()->sum(fn (LineItem $item) => (int) $item->quantity());
    }

    public function currency(Site $site): ?string
    {
        $currency = $site->attribute('currency');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : null;
    }

    public function format(?int $amount, Site $site): string
    {
        if ($amount === null) {
            return '';
        }

        try {
            return Money::format($amount, $site);
        } catch (Throwable) {
            return number_format($amount / 100, 2);
        }
    }

    /**
     * Whether a line item can still be bought: the product's there and
     * live, and (if stock is counted) in stock.
     */
    public function available(LineItem $item): bool
    {
        $product = $this->safely(fn () => $item->product());

        if (! $product || $product->status() !== 'published') {
            return false;
        }

        if ($item->variant) {
            $variant = $this->safely(fn () => $item->variant());

            if (! $variant) {
                return false;
            }

            return ! $variant->isStockEnabled() || (int) $variant->stock() >= (int) $item->quantity();
        }

        return ! $product->isStockEnabled() || (int) $product->stock() >= (int) $item->quantity();
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
