<?php

namespace NineteenNinetyFour\AbandonedCart;

/**
 * One of the reminders in the settings: when it goes, and its discount.
 */
final class Step
{
    /**
     * @param  int  $number  1, 2 or 3: its place in the settings, which picks its wording
     * @param  int  $delay  hours after the basket last changed
     * @param  string|null  $discountMode  null, 'existing' or 'generate'
     * @param  string|null  $discountHandle  an existing Cargo discount
     * @param  string|null  $discountType  percentage_off, amount_off or free_shipping
     * @param  float|null  $discountValue  10 for 10%, or 5 for 5.00
     */
    public function __construct(
        public readonly int $number,
        public readonly int $delay,
        public readonly ?string $discountMode = null,
        public readonly ?string $discountHandle = null,
        public readonly ?string $discountType = null,
        public readonly ?float $discountValue = null,
    ) {}

    public function hasDiscount(): bool
    {
        return match ($this->discountMode) {
            'existing' => $this->discountHandle !== null,
            'generate' => $this->discountType === 'free_shipping' || ($this->discountType !== null && $this->discountValue > 0),
            default => false,
        };
    }
}
