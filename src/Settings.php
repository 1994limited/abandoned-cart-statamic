<?php

namespace NineteenNinetyFour\AbandonedCart;

use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Throwable;

/**
 * The addon's settings, edited in the Control Panel (Tools → Addons →
 * Abandoned Cart → Settings). Each falls back to config/abandoned-cart.php,
 * or to the wording in lang/, so an empty setting means "the default".
 * Placeholders are written :like_this.
 */
class Settings
{
    public const ADDON = '1994/abandoned-cart-statamic';

    public const DISCOUNT_TYPES = ['percentage_off', 'amount_off', 'free_shipping'];

    public static function get(string $key, mixed $default = null): mixed
    {
        // Saved values only: Statamic fills in the blueprint's defaults,
        // which would hide the config's.
        try {
            $value = Addon::get(self::ADDON)?->settings()->raw()[$key] ?? null;
        } catch (Throwable) {
            $value = null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' || $value === null ? $default : $value;
    }

    /**
     * The reminders that are switched on, soonest first.
     *
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        $configured = array_values((array) config('abandoned-cart.reminders', []));
        $steps = [];

        foreach ([1, 2, 3] as $number) {
            $config = (array) ($configured[$number - 1] ?? []);
            $default = $config['enabled'] ?? false;

            // The first reminder is always on: switching them all off is
            // uninstalling.
            $enabled = $number === 1 ? true : self::bool("reminder_{$number}_enabled", (bool) $default);

            if (! $enabled) {
                continue;
            }

            $delay = max(1, (int) self::get("reminder_{$number}_delay", $config['delay'] ?? [1, 24, 72][$number - 1]));

            [$mode, $handle, $type, $value] = self::discount($number, $config['discount'] ?? null);

            $steps[] = new Step($number, $delay, $mode, $handle, $type, $value);
        }

        // Soonest first, and no two at the same time.
        usort($steps, fn (Step $a, Step $b) => [$a->delay, $a->number] <=> [$b->delay, $b->number]);

        return array_values(array_filter($steps, function (Step $step) use (&$last) {
            $keep = $last === null || $step->delay > $last;
            $last = $keep ? $step->delay : $last;

            return $keep;
        }));
    }

    /**
     * A reminder's discount: [mode, handle, type, value].
     *
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: float|null}
     */
    protected static function discount(int $number, mixed $config): array
    {
        $mode = self::get("reminder_{$number}_discount");

        // Not set in the Control Panel: the config's.
        if ($mode === null) {
            if (is_string($config) && $config !== '') {
                return ['existing', $config, null, null];
            }

            if (is_array($config) && in_array($config['type'] ?? null, self::DISCOUNT_TYPES, true)) {
                return ['generate', null, $config['type'], isset($config['value']) ? (float) $config['value'] : null];
            }

            return [null, null, null, null];
        }

        return match ($mode) {
            'existing' => ['existing', self::get("reminder_{$number}_discount_handle"), null, null],
            'generate' => [
                'generate',
                null,
                in_array($type = self::get("reminder_{$number}_discount_type", 'percentage_off'), self::DISCOUNT_TYPES, true) ? $type : 'percentage_off',
                is_numeric($value = self::get("reminder_{$number}_discount_value")) ? (float) $value : null,
            ],
            default => [null, null, null, null],
        };
    }

    /**
     * 'opt_in' or 'soft_opt_in'.
     */
    public static function consentMode(): string
    {
        return self::get('consent_mode', config('abandoned-cart.consent', 'opt_in')) === 'soft_opt_in' ? 'soft_opt_in' : 'opt_in';
    }

    /**
     * 'all', 'guests' or 'customers'.
     */
    public static function audience(): string
    {
        $audience = self::get('audience', config('abandoned-cart.audience', 'all'));

        return in_array($audience, ['guests', 'customers'], true) ? $audience : 'all';
    }

    /**
     * The smallest basket emailed, in the currency's minor unit (pence).
     */
    public static function minimumTotal(): ?int
    {
        $minimum = self::get('minimum_total', config('abandoned-cart.minimum_total'));

        return is_numeric($minimum) && $minimum > 0 ? (int) round($minimum * 100) : null;
    }

    /**
     * Addresses and domains never emailed, lower case.
     *
     * @return array<int, string>
     */
    public static function excluded(): array
    {
        return self::list('exclude', (array) config('abandoned-cart.exclude', []));
    }

    public static function testMode(): bool
    {
        return self::bool('test_mode', (bool) config('abandoned-cart.test_mode', false));
    }

    /**
     * @return array<int, string>
     */
    public static function testRecipients(): array
    {
        return self::list('test_recipients', (array) config('abandoned-cart.test_recipients', []));
    }

    public static function linkDays(): int
    {
        return max(1, (int) self::get('link_days', config('abandoned-cart.link_days', 7)));
    }

    public static function perMinute(): int
    {
        return max(1, (int) config('abandoned-cart.per_minute', 120));
    }

    /**
     * Days before Cargo deletes a cart that hasn't changed.
     */
    public static function purgeDays(): int
    {
        return max(1, (int) config('statamic.cargo.carts.purge_abandoned_carts_after', 30));
    }

    /**
     * A piece of wording, with its placeholders filled in. In the basket's
     * language when the setting is left empty.
     *
     * @param  array<string, string|null>  $replace
     */
    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $text = self::get($key) ?? (string) __('abandoned-cart::messages.defaults.'.$key, [], $locale);

        $replace += ['site' => Site::current()?->name() ?? config('app.name')];

        // Longest first, so :site_name wouldn't be read as :site.
        uksort($replace, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * The brand colour, as a CSS hex colour.
     */
    public static function colour(): string
    {
        $colour = self::get('accent_colour');

        return is_string($colour) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $colour) ? $colour : '#1f2937';
    }

    /**
     * Dark or white text, whichever reads better on the brand colour.
     */
    public static function onColour(): string
    {
        $hex = ltrim(self::colour(), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        [$r, $g, $b] = array_map(function (string $pair) {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.0607) ? '#ffffff' : '#111827';
    }

    public static function logo(): ?Asset
    {
        try {
            $addon = Addon::get(self::ADDON);
            $value = $addon?->settings()->raw()['logo'] ?? null;

            if (! $value || ! $field = $addon->settingsBlueprint()?->field('logo')) {
                return null;
            }

            $asset = $field->setValue($value)->augment()->value();
            $asset = is_iterable($asset) ? collect($asset)->first() : $asset;

            return $asset instanceof Asset ? $asset : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function bool(string $key, bool $default): bool
    {
        $value = self::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * A list setting: one a line (or commas) in the Control Panel, or an
     * array in the config.
     *
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    protected static function list(string $key, array $default): array
    {
        $value = self::get($key);
        $items = is_string($value) ? preg_split('/[\s,;]+/', $value) : (is_array($value) ? $value : $default);

        return array_values(array_unique(array_filter(array_map(
            fn ($item) => is_string($item) ? mb_strtolower(trim($item)) : '',
            $items,
        ))));
    }
}
