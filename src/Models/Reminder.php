<?php

namespace NineteenNinetyFour\AbandonedCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * One reminder email, its discount code, and whether its link was used.
 *
 * @property string $id
 * @property int $abandoned_cart_id
 * @property int $step 1, 2 or 3: which of the reminders in the settings
 * @property string|null $email
 * @property bool $test
 * @property string|null $discount_handle
 * @property string|null $discount_code
 * @property bool $discount_generated
 * @property Carbon|null $sent_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $viewed_at
 * @property Carbon|null $clicked_at
 * @property int $clicks
 * @property-read Basket $basket
 */
class Reminder extends Model
{
    use HasUlids;

    protected $table = 'abandoned_cart_reminders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'test' => 'boolean',
            'discount_generated' => 'boolean',
            'clicks' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'viewed_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class, 'abandoned_cart_id');
    }

    /**
     * The link in the email: it opens the basket, with a button that brings
     * it back. Signed, and it stops working when the reminder expires.
     */
    public function restoreUrl(): string
    {
        return URL::temporarySignedRoute('statamic.abandoned-cart.restore', $this->expires_at ?? now()->addDays(7), ['reminder' => $this->id]);
    }

    public function restorePostUrl(): string
    {
        return URL::temporarySignedRoute('statamic.abandoned-cart.restore.post', $this->expires_at ?? now()->addDays(7), ['reminder' => $this->id]);
    }

    /**
     * Unsubscribing never expires.
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('statamic.abandoned-cart.unsubscribe', ['reminder' => $this->id]);
    }

    public function unsubscribePostUrl(): string
    {
        return URL::signedRoute('statamic.abandoned-cart.unsubscribe.post', ['reminder' => $this->id]);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
