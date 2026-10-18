# PHP API

The `AbandonedCart` facade records consent from a checkout of your own, and unsubscribes, stops and forgets.

```php
use NineteenNinetyFour\AbandonedCart\Facades\AbandonedCart;
use NineteenNinetyFour\AbandonedCart\Models\Basket;

// Consent for the visitor's basket, with the wording in the settings (see Consent).
AbandonedCart::consent(true);

AbandonedCart::unsubscribe('reader@example.com');    // no more reminders to this address
AbandonedCart::isUnsubscribed('reader@example.com'); // true
AbandonedCart::lift('reader@example.com');           // takes it off the unsubscribed list

// No more reminders for a basket, until it changes.
$basket = Basket::query()->where('email', 'reader@example.com')->latest('cart_updated_at')->first();
AbandonedCart::stop($basket);

AbandonedCart::forget('reader@example.com');         // deletes everything kept about it
```

| Method | |
| --- | --- |
| `consent(bool $given, ?Cart $cart = null, ?string $text = null)` | Records whether the shopper agreed to reminders, for a Cargo cart (the visitor's own if none is given), with the wording they saw (the settings' if none is given). Returns the `Basket`, or null when there's no cart. See [Consent](consent.md#the-php-api). |
| `unsubscribe(string $email)` | Never email this address again, unless they tick the box to ask. Its waiting baskets are marked "Unsubscribed". |
| `lift(string $email)` | Takes an address off the unsubscribed list. |
| `isUnsubscribed(string $email)` | Whether it's on the list. |
| `stop(Basket $basket)` | No more reminders for this basket, unless it changes. As **Stop reminders** in the Control Panel. |
| `forget(string $email)` | Deletes its baskets, their reminders, and unused one-off codes. The unsubscribed list keeps its hash. Returns how many baskets were deleted. |

The models are Eloquent, in `NineteenNinetyFour\AbandonedCart\Models`:

- `Basket`: a Cargo cart, as the addon keeps it (`cart_id`, `site`, `email`, `name`, `user_id`, `consent`, `items`, `total`, `cart_updated_at`, `status`, `reminders_sent`, `skip_reason`, `order_id`…). Amounts are in the currency's smallest unit. `->reminders()`.
- `Reminder`: one reminder email (`step`, `email`, `test`, `discount_code`, `sent_at`, `expires_at`, `viewed_at`, `clicked_at`, `clicks`). `->basket`.
- `Suppression`: an unsubscribed address, as a hash.

`status` is a `NineteenNinetyFour\AbandonedCart\Status`: `Open`, `Recovered`, `Ordered`, `Cancelled` or `Deleted`. `skip_reason` is one of the [reasons](reminders.md#why-a-basket-wasnt-emailed): `empty`, `no_email`, `unsubscribed`, `no_consent`, `audience`, `below_minimum`, `excluded`, `test_mode`, `expiring`, `ordered_since` or `stopped`.

```php
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Status;

Basket::query()->where('status', Status::Recovered)->sum('order_total');
```

## Commands

| Command | |
| --- | --- |
| `php please abandoned-cart:send` | Emails the reminders that are due. Scheduled every minute. |
| `php please abandoned-cart:prune` | Deletes expired one-off codes, and anonymises and deletes old baskets. Scheduled daily. |
| `php please abandoned-cart:forget {email}` | Deletes everything kept about an address. |
