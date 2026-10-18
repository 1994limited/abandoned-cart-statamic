<?php

namespace NineteenNinetyFour\AbandonedCart;

use NineteenNinetyFour\AbandonedCart\Models\Basket;

/**
 * Whether a basket may be emailed, under the consent mode in the settings.
 */
class Consent
{
    public static function mode(): string
    {
        return Settings::consentMode();
    }

    /**
     * The wording next to the box: "Email me…" (opt-in), or "Don't email
     * me…" (soft opt-in).
     */
    public static function text(?string $locale = null): string
    {
        return self::mode() === 'soft_opt_in'
            ? Settings::text('opt_out_text', [], $locale)
            : Settings::text('consent_text', [], $locale);
    }

    /**
     * Opt-in: only when the box was ticked. Soft opt-in: unless the
     * "don't email me" box was ticked.
     */
    public static function allows(Basket $basket): bool
    {
        return self::mode() === 'soft_opt_in'
            ? $basket->consent !== 'refused'
            : $basket->consent === 'given';
    }
}
