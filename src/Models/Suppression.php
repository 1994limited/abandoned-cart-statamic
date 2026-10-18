<?php

namespace NineteenNinetyFour\AbandonedCart\Models;

/**
 * An address that unsubscribed. Only a hash of it is kept.
 *
 * @property string $email_hash
 */
class Suppression extends Model
{
    protected $table = 'abandoned_cart_suppressions';

    protected $primaryKey = 'email_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $guarded = [];

    public static function hash(string $email): string
    {
        return hash('sha256', Basket::normaliseEmail($email));
    }

    public static function add(string $email): void
    {
        self::addHash(self::hash($email));
    }

    public static function addHash(string $hash): void
    {
        static::query()->firstOrCreate(['email_hash' => $hash]);
    }

    public static function lift(string $email): void
    {
        static::query()->whereKey(self::hash($email))->delete();
    }

    /**
     * Whether an address unsubscribed. Ticking the box to ask for reminders
     * afterwards ($consentAt) is asking again, so that one isn't blocked.
     */
    public static function blocks(?string $email, ?\DateTimeInterface $consentAt = null): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        $suppression = static::query()->find(self::hash($email));

        return $suppression !== null
            && ($consentAt === null || $suppression->created_at === null || $consentAt <= $suppression->created_at);
    }
}
