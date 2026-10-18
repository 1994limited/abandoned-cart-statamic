<?php

namespace NineteenNinetyFour\AbandonedCart;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\AbandonedCart\Cargo\Carts;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use Statamic\Facades\Site;

/**
 * The numbers in the Control Panel and the dashboard widget.
 */
class Reports
{
    public function __construct(protected Carts $carts) {}

    /**
     * Baskets left long enough for a first reminder (whether or not one
     * could be sent), since a date.
     */
    public function abandoned(?Carbon $since = null): Builder
    {
        $first = Settings::steps()[0]->delay ?? 1;

        return Basket::query()
            ->where('items_count', '>', 0)
            ->where('status', '!=', Status::Ordered->value)
            ->when($since, fn ($query) => $query->where('cart_updated_at', '>=', $since))
            ->where(fn ($query) => $query
                ->where('reminders_sent', '>', 0)
                ->orWhere('cart_updated_at', '<=', now()->subHours($first)));
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(?Carbon $since = null): array
    {
        $abandoned = $this->abandoned($since)->count();
        $emailed = $this->abandoned($since)->where('reminders_sent', '>', 0)->count();

        $clicked = $this->abandoned($since)
            ->whereHas('reminders', fn ($query) => $query->where('test', false)->whereNotNull('clicked_at'))
            ->count();

        $recovered = Basket::query()
            ->where('status', Status::Recovered->value)
            ->when($since, fn ($query) => $query->where('ordered_at', '>=', $since));

        $reminders = Reminder::query()
            ->where('test', false)
            ->when($since, fn ($query) => $query->where('sent_at', '>=', $since))
            ->count();

        $revenue = (clone $recovered)
            ->selectRaw('site, currency, sum(order_total) as revenue')
            ->groupBy('site', 'currency')
            ->get()
            ->groupBy(fn ($row) => $row->currency ?: $row->site)
            ->map(function ($rows) {
                $site = Site::get($rows->first()->site) ?? Site::default();

                return $this->carts->format((int) $rows->sum('revenue'), $site);
            })
            ->values()
            ->all();

        $recoveredCount = $recovered->count();

        return [
            'abandoned' => $abandoned,
            'emailed' => $emailed,
            'not_emailed' => $abandoned - $emailed,
            'reminders' => $reminders,
            'clicked' => $clicked,
            'click_rate' => $this->percent($clicked, $emailed),
            'recovered' => $recoveredCount,
            'recovery_rate' => $this->percent($recoveredCount, $emailed),
            'revenue' => $revenue,
            'reasons' => $this->abandoned($since)
                ->where('reminders_sent', 0)
                ->whereNotNull('skip_reason')
                ->selectRaw('skip_reason, count(*) as total')
                ->groupBy('skip_reason')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['reason' => $row->skip_reason, 'label' => self::reason($row->skip_reason), 'total' => (int) $row->total])
                ->all(),
        ];
    }

    /**
     * A warning when the scheduler doesn't seem to be running the send
     * command, or null.
     */
    public function schedulerWarning(): ?string
    {
        $last = Cache::get(Sender::LAST_RUN);

        if (! $last) {
            return __('abandoned-cart::messages.cp.scheduler_never');
        }

        if ((int) $last < now()->subMinutes(15)->timestamp) {
            return __('abandoned-cart::messages.cp.scheduler_stale', ['ago' => Carbon::createFromTimestamp((int) $last)->diffForHumans()]);
        }

        return null;
    }

    public static function reason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $key = 'abandoned-cart::messages.reasons.'.$reason;
        $label = __($key);

        return $label === $key ? $reason : $label;
    }

    protected function percent(int $part, int $whole): ?string
    {
        return $whole > 0 ? round($part / $whole * 100).'%' : null;
    }
}
