<?php

namespace NineteenNinetyFour\AbandonedCart\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\AbandonedCart\Discounts;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use Statamic\Console\RunsInPlease;

class Prune extends Command
{
    use RunsInPlease;

    protected $signature = 'abandoned-cart:prune';

    protected $description = 'Delete expired one-off codes, and old basket records (config/abandoned-cart.php → retention)';

    public function handle(Discounts $discounts): int
    {
        $codes = $discounts->pruneExpired();
        $anonymised = 0;
        $deleted = 0;

        if ($days = config('abandoned-cart.retention.anonymise')) {
            $ids = Basket::query()
                ->whereNull('anonymised_at')
                ->where('cart_updated_at', '<', now()->subDays((int) $days))
                ->pluck('id');

            // Everything that could lead back to the shopper goes: who, what
            // they bought, the cart and order it can be matched to, and the
            // codes. Totals, dates, counts and outcomes stay, for the reports.
            $ids->chunk(500)->each(function ($chunk) use (&$anonymised) {
                Reminder::query()->whereIn('abandoned_cart_id', $chunk)->update([
                    'email' => null,
                    'email_hash' => null,
                    'discount_code' => null,
                    'discount_handle' => null,
                ]);

                foreach ($chunk as $id) {
                    $anonymised += Basket::query()->whereKey($id)->update([
                        'cart_id' => 'anonymised-'.$id,
                        'email' => null,
                        'name' => null,
                        'user_id' => null,
                        'customer_id' => null,
                        'consent_text' => null,
                        'items' => null,
                        'order_id' => null,
                        'order_reference' => null,
                        'anonymised_at' => now(),
                    ]);
                }
            });
        }

        if ($days = config('abandoned-cart.retention.delete')) {
            $deleted = Basket::query()->where('cart_updated_at', '<', now()->subDays((int) $days))->delete();
        }

        $this->components->info("Deleted {$codes} expired codes and {$deleted} old baskets; anonymised {$anonymised}.");

        return self::SUCCESS;
    }
}
