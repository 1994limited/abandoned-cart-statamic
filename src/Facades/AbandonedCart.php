<?php

namespace NineteenNinetyFour\AbandonedCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \NineteenNinetyFour\AbandonedCart\Models\Basket|null consent(bool $given, ?\DuncanMcClean\Cargo\Contracts\Cart\Cart $cart = null, ?string $text = null)
 * @method static void unsubscribe(string $email)
 * @method static void unsubscribeHash(string $hash)
 * @method static void lift(string $email)
 * @method static bool isUnsubscribed(string $email)
 * @method static int forget(string $email)
 * @method static void stop(\NineteenNinetyFour\AbandonedCart\Models\Basket $basket)
 *
 * @see \NineteenNinetyFour\AbandonedCart\AbandonedCart
 */
class AbandonedCart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NineteenNinetyFour\AbandonedCart\AbandonedCart::class;
    }
}
