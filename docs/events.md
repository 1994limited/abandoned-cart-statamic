# Events

Listen to these to post recovered orders to Slack, sync to your analytics, or tag customers. All are in `NineteenNinetyFour\AbandonedCart\Events`.

| Event | When | Properties |
| --- | --- | --- |
| `ReminderSent` | A reminder was emailed to a shopper (not a test) | `$reminder` |
| `CartRestored` | A shopper brought their basket back with the button on a reminder's page | `$reminder` |
| `CartRecovered` | A basket that was reminded became an order | `$basket`, `$order` |

`$reminder` is a `NineteenNinetyFour\AbandonedCart\Models\Reminder`: `step` (1, 2 or 3), `email`, `discount_code`, and the times (`sent_at`, `expires_at`, `viewed_at`, `clicked_at`), with `clicks`. `$reminder->basket` is its basket.

`$basket` is a `NineteenNinetyFour\AbandonedCart\Models\Basket`: `cart_id`, `site`, `email`, `name`, `user_id`, `items`, `total`, `currency`, `reminders_sent`, `status`, and the order (`order_id`, `order_reference`, `order_total`, `ordered_at`). Amounts are in the currency's smallest unit (pence). See the [PHP API](php-api.md).

`$order` is Cargo's order. `CartRecovered` fires once the order is saved. It also fires for an order from another basket, when the shopper used a reminder's button in the last 7 days (see [Reminders](reminders.md#orders)).

```php
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\AbandonedCart\Events\CartRecovered;

Event::listen(function (CartRecovered $event) {
    // $event->order is the order, $event->basket the basket it recovered.
});
```
