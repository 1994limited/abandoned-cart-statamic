<?php

namespace NineteenNinetyFour\AbandonedCart;

use DuncanMcClean\Cargo\Contracts\Cart\Cart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use NineteenNinetyFour\AbandonedCart\Cargo\Carts;
use NineteenNinetyFour\AbandonedCart\Events\ReminderSent;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Models\Suppression;
use Throwable;

/**
 * Sending the reminders that are due: each basket's next one, once, no
 * more a minute than the limit, and only while the basket's still there,
 * unchanged, unordered and allowed to be emailed.
 */
class Sender
{
    public const RATE_KEY = 'abandoned-cart:emails';

    /** When abandoned-cart:send last ran, for the Control Panel's warning. */
    public const LAST_RUN = 'abandoned-cart:last-run';

    /** The most baskets one run looks at; the rest go in the next. */
    public const BATCH = 500;

    /** The longest wait between two reminders to the same basket, in hours. */
    public const MAX_GAP = 12;

    public function __construct(protected Carts $carts, protected Tracker $tracker, protected Discounts $discounts) {}

    /**
     * Send everything due. Returns how many were emailed.
     */
    public function sendDue(): int
    {
        Cache::forever(self::LAST_RUN, now()->timestamp);

        $this->releaseStuck();
        $this->skipExpiring();

        $steps = Settings::steps();
        $sent = 0;
        $looked = 0;

        foreach ($steps as $index => $step) {
            $previous = $index > 0 ? $steps[$index - 1]->delay : 0;
            $gap = max(1, min(self::MAX_GAP, $step->delay - $previous));

            $baskets = $this->due($index, $step, $gap)->limit(self::BATCH - $looked)->get();

            foreach ($baskets as $basket) {
                $looked++;

                if (RateLimiter::tooManyAttempts(self::RATE_KEY, Settings::perMinute())) {
                    return $sent;
                }

                if ($this->send($basket, $step) === true) {
                    $sent++;
                }
            }

            if ($looked >= self::BATCH) {
                break;
            }
        }

        return $sent;
    }

    /**
     * Baskets due their reminder at this place in the sequence.
     */
    protected function due(int $index, Step $step, int $gap): Builder
    {
        return Basket::query()
            ->where('status', Status::Open->value)
            ->whereNull('skip_reason')
            ->whereNull('claimed_at')
            ->where('items_count', '>', 0)
            ->where('reminders_sent', $index)
            ->where('cart_updated_at', '<=', now()->subHours($step->delay))
            ->where(fn ($query) => $query->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subHours($gap)))
            ->orderBy('cart_updated_at');
    }

    /**
     * Send one basket its reminder. True if emailed, false if skipped, null
     * if the email couldn't be sent (it's tried again next time).
     */
    public function send(Basket $basket, Step $step): ?bool
    {
        // Claim it, so two runs at once can't both email it.
        $claimed = Basket::query()
            ->whereKey($basket->id)
            ->where('status', Status::Open->value)
            ->whereNull('claimed_at')
            ->where('reminders_sent', $basket->reminders_sent)
            ->update(['claimed_at' => now()]);

        if (! $claimed) {
            return false;
        }

        $basket->refresh();
        $cart = $this->carts->find($basket->cart_id);

        if (! $cart) {
            $basket->forceFill(['status' => Status::Deleted, 'claimed_at' => null])->save();

            return false;
        }

        if ($order = $cart->order()) {
            $basket->forceFill(['claimed_at' => null])->save();
            $this->tracker->orderPlaced($order);

            return false;
        }

        // Changed since it was recorded (a save the addon didn't hear
        // about): catch up, and wait for it to be left again.
        if ($this->carts->updatedAt($cart)->timestamp > ($basket->cart_updated_at?->timestamp ?? 0)) {
            $basket->forceFill(['claimed_at' => null])->save();
            $this->tracker->track($cart);

            return false;
        }

        if ($reason = $this->reason($basket, $cart)) {
            $basket->forceFill(['skip_reason' => $reason, 'claimed_at' => null])->save();

            return false;
        }

        $reminder = $this->email($basket, $step);

        if (! $reminder) {
            $basket->forceFill(['claimed_at' => null])->save();

            return null;
        }

        $basket->forceFill([
            'reminders_sent' => $basket->reminders_sent + 1,
            'last_reminder_at' => now(),
            'claimed_at' => null,
        ])->save();

        ReminderSent::dispatch($reminder);

        return true;
    }

    /**
     * Email a reminder to someone in the shop (it's marked as a test, and
     * changes nothing): what the shopper would get.
     */
    public function sendTest(Basket $basket, Step $step, string $to): ?Reminder
    {
        return $this->email($basket, $step, $to);
    }

    /**
     * Why a basket can't be emailed, or null if it can.
     */
    public function reason(Basket $basket, ?Cart $cart = null): ?string
    {
        $email = $basket->email;

        return match (true) {
            $cart && $cart->lineItems()->isEmpty(), $basket->items_count < 1 => 'empty',
            $email === null || $email === '' => 'no_email',
            Suppression::blocks($email, $basket->consent === 'given' ? $basket->consent_at : null) => 'unsubscribed',
            ! Consent::allows($basket) => 'no_consent',
            Settings::audience() === 'guests' && ! $basket->isGuest(),
            Settings::audience() === 'customers' && $basket->isGuest() => 'audience',
            ($minimum = Settings::minimumTotal()) !== null && $basket->total < $minimum => 'below_minimum',
            $this->excluded($email) => 'excluded',
            Settings::testMode() && ! in_array(Basket::normaliseEmail($email), Settings::testRecipients(), true) => 'test_mode',
            default => null,
        };
    }

    protected function email(Basket $basket, Step $step, ?string $to = null): ?Reminder
    {
        $test = $to !== null;
        $expires = $this->expiry($basket);
        $discount = null;

        try {
            $discount = $this->discounts->forReminder($step, $basket, $expires, $test);
        } catch (Throwable $e) {
            // Better a reminder without a code than none.
            Log::warning("Abandoned Cart couldn't make a discount for basket {$basket->id}: {$e->getMessage()}");
            report($e);
        }

        $reminder = $basket->reminders()->create([
            'step' => $step->number,
            'email' => $test ? null : $basket->email,
            'test' => $test,
            'discount_handle' => $discount[0] ?? null ?: null,
            'discount_code' => $discount[1] ?? null,
            'discount_generated' => (bool) ($discount[2] ?? false),
            'sent_at' => now(),
            'expires_at' => $expires,
        ]);

        if (! $test) {
            RateLimiter::hit(self::RATE_KEY, 60);
        }

        try {
            $mail = Mail::to($to ?? $basket->email)->locale($basket->locale());

            if (! $test && $bcc = config('abandoned-cart.email.bcc')) {
                $mail->bcc($bcc);
            }

            $mail->send(new ReminderMail($reminder, $basket, $step));
        } catch (Throwable $e) {
            $this->discounts->deleteUnused($reminder->discount_generated ? $reminder->discount_handle : null);
            $reminder->delete();
            Log::warning("Abandoned Cart couldn't email basket {$basket->id}: {$e->getMessage()}");
            report($e);

            return null;
        }

        return $reminder;
    }

    /**
     * The link lasts link_days, but never past the day Cargo deletes the cart.
     */
    protected function expiry(Basket $basket): Carbon
    {
        $expires = now()->addDays(Settings::linkDays());
        $purged = ($basket->cart_updated_at ?? now())->copy()->addDays(Settings::purgeDays())->subHour();

        return $expires->min($purged)->max(now()->addHour());
    }

    protected function excluded(string $email): bool
    {
        $email = Basket::normaliseEmail($email);
        $domain = substr((string) strrchr($email, '@'), 1);

        foreach (Settings::excluded() as $entry) {
            $entry = ltrim($entry, '@');

            if ($entry === $email || ($domain !== '' && ($entry === $domain || str_ends_with($domain, '.'.$entry)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Baskets about to be deleted by Cargo: too late to remind.
     */
    protected function skipExpiring(): void
    {
        Basket::query()
            ->where('status', Status::Open->value)
            ->whereNull('skip_reason')
            ->whereNull('claimed_at')
            ->where('cart_updated_at', '<', now()->subDays(Settings::purgeDays())->addDay())
            ->update(['skip_reason' => 'expiring']);
    }

    /**
     * Baskets claimed by a run that never finished go back after an hour.
     */
    public function releaseStuck(): int
    {
        return Basket::query()
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', now()->subHour())
            ->update(['claimed_at' => null]);
    }
}
