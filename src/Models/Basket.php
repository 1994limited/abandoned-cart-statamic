<?php

namespace NineteenNinetyFour\AbandonedCart\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use NineteenNinetyFour\AbandonedCart\Status;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;

/**
 * A Cargo cart, as the addon keeps it: who it's for, what was in it when it
 * last changed, and the reminders and order since. It outlives the cart
 * (Cargo deletes old ones), so the reports still add up.
 *
 * @property int $id
 * @property string $cart_id
 * @property string $site
 * @property string|null $locale
 * @property string|null $email
 * @property string|null $name
 * @property string|null $user_id
 * @property string|null $customer_id
 * @property string|null $consent 'given', 'refused' or null (not asked)
 * @property string|null $consent_text
 * @property Carbon|null $consent_at
 * @property array<int, array<string, mixed>>|null $items
 * @property int $items_count
 * @property int $total
 * @property string|null $currency
 * @property Carbon|null $cart_updated_at
 * @property Status $status
 * @property int $reminders_sent
 * @property Carbon|null $last_reminder_at
 * @property string|null $skip_reason
 * @property Carbon|null $claimed_at
 * @property string|null $order_id
 * @property string|null $order_reference
 * @property int|null $order_total
 * @property Carbon|null $ordered_at
 * @property Carbon|null $anonymised_at
 */
class Basket extends Model
{
    protected $table = 'abandoned_carts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'items_count' => 'integer',
            'total' => 'integer',
            'order_total' => 'integer',
            'reminders_sent' => 'integer',
            'status' => Status::class,
            'consent_at' => 'datetime',
            'cart_updated_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'claimed_at' => 'datetime',
            'ordered_at' => 'datetime',
            'anonymised_at' => 'datetime',
        ];
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'abandoned_cart_id');
    }

    public function siteObject(): SiteObject
    {
        return Site::get($this->site) ?? Site::default();
    }

    public function locale(): string
    {
        return $this->locale ?: $this->siteObject()->lang();
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
