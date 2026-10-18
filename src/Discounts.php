<?php

namespace NineteenNinetyFour\AbandonedCart;

use DuncanMcClean\Cargo\Contracts\Discounts\Discount;
use DuncanMcClean\Cargo\Facades\Discount as CargoDiscounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use Throwable;

/**
 * The discount codes in reminders: an existing Cargo discount, or a
 * one-off one made for the email (once, for that shopper, until the link
 * expires).
 */
class Discounts
{
    /** One-off discounts' handles start with this. */
    public const PREFIX = 'abandoned-cart-';

    /** Letters and numbers that can't be mistaken for each other. */
    protected const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * The discount for a reminder: [handle, code, generated], or null.
     *
     * @return array{0: string, 1: string, 2: bool}|null
     */
    public function forReminder(Step $step, Basket $basket, Carbon $expires, bool $test = false): ?array
    {
        if (! $step->hasDiscount()) {
            return null;
        }

        if ($step->discountMode === 'existing') {
            $discount = $this->find($step->discountHandle);
            $code = $discount?->get('discount_code');

            return $discount && is_string($code) && $code !== '' ? [$discount->handle(), strtoupper($code), false] : null;
        }

        // A test shows what the code would look like; no discount is made.
        if ($test) {
            return ['', $this->prefix().'-TEST', false];
        }

        // Without a customer to tie it to, a one-off code would work for anyone.
        if (! $basket->customer_id) {
            return null;
        }

        $code = $this->uniqueCode();
        $handle = self::PREFIX.Str::lower((string) Str::ulid());

        $discount = CargoDiscounts::make()
            ->handle($handle)
            ->title(__('abandoned-cart::messages.discount_title', ['code' => $code]))
            ->type($step->discountType)
            ->set('discount_code', $code)
            ->set('customers', [$basket->customer_id])
            ->set('maximum_uses', 1)
            // Cargo reads the date as the start of that day.
            ->set('end_date', $expires->copy()->addDay()->format('Y-m-d'));

        match ($step->discountType) {
            'percentage_off' => $discount->set('percentage_off', (int) round(min(100, max(0, (float) $step->discountValue)))),
            'amount_off' => $discount->set('amount_off', (int) round(max(0, (float) $step->discountValue) * 100)),
            default => null,
        };

        $discount->save();

        return [$handle, $code, true];
    }

    /**
     * "10% off", "£5.00 off", "Free delivery": for the email's wording.
     */
    public function describe(Step $step, Basket $basket): ?string
    {
        if (! $step->hasDiscount()) {
            return null;
        }

        [$type, $value] = $step->discountMode === 'existing'
            ? $this->existing($step->discountHandle)
            : [$step->discountType, $step->discountValue];

        $site = $basket->siteObject();

        return match ($type) {
            'percentage_off' => __('abandoned-cart::messages.discount.percentage_off', ['value' => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.')], $basket->locale()),
            'amount_off' => __('abandoned-cart::messages.discount.amount_off', ['value' => app(Cargo\Carts::class)->format($step->discountMode === 'existing' ? (int) $value : (int) round((float) $value * 100), $site)], $basket->locale()),
            'free_shipping' => __('abandoned-cart::messages.discount.free_shipping', [], $basket->locale()),
            default => null,
        };
    }

    /**
     * Delete a one-off discount nobody used.
     */
    public function deleteUnused(?string $handle): void
    {
        if (! $handle || ! str_starts_with($handle, self::PREFIX)) {
            return;
        }

        $discount = $this->find($handle);

        if ($discount && (int) $discount->get('redemptions_count', 0) === 0) {
            $discount->delete();
        }
    }

    /**
     * One-off discounts that expired unused. Returns how many were deleted.
     */
    public function pruneExpired(): int
    {
        $deleted = 0;

        Reminder::query()
            ->where('discount_generated', true)
            ->whereNotNull('discount_handle')
            ->where('expires_at', '<', now()->subDay())
            ->each(function (Reminder $reminder) use (&$deleted) {
                $discount = $this->find($reminder->discount_handle);

                if ($discount && (int) $discount->get('redemptions_count', 0) === 0) {
                    $discount->delete();
                    $deleted++;
                }

                $reminder->forceFill(['discount_generated' => false])->save();
            });

        return $deleted;
    }

    public function find(?string $handle): ?Discount
    {
        if (! $handle) {
            return null;
        }

        try {
            return CargoDiscounts::find($handle);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Cargo's discounts with a code, for the settings.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        try {
            return CargoDiscounts::all()
                ->reject(fn (Discount $discount) => str_starts_with((string) $discount->handle(), self::PREFIX))
                ->filter(fn (Discount $discount) => $discount->get('discount_code'))
                ->mapWithKeys(fn (Discount $discount) => [$discount->handle() => $discount->title().' ('.strtoupper((string) $discount->get('discount_code')).')'])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{0: string|null, 1: int|float|null}
     */
    protected function existing(?string $handle): array
    {
        $discount = $this->find($handle);

        if (! $discount) {
            return [null, null];
        }

        $type = $discount->type();

        return [$type, $discount->get($type)];
    }

    protected function prefix(): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) config('abandoned-cart.discount_prefix', 'COMEBACK')));

        return $prefix !== '' ? $prefix : 'COMEBACK';
    }

    protected function uniqueCode(): string
    {
        do {
            $code = $this->prefix().'-';

            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (CargoDiscounts::findByDiscountCode($code));

        return $code;
    }
}
