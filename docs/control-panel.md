# The Control Panel

## Store → Abandoned Carts

The baskets left, latest first: who left it (their email address, name, and whether they're a guest or a customer), its total and number of items, when it was left, the reminders sent (and "clicked" if the button in one was used), and its status. A basket that wasn't emailed shows why; an ordered one shows its order number.

At the top, the numbers for the period: the last 7, 30 (the default) or 90 days, or all time, by when the basket last changed. Under them, why baskets weren't emailed, with how many of each.

Search by email address or name. **Show** filters the list:

| Show | |
| --- | --- |
| All abandoned | Every abandoned basket (see below). |
| Waiting for a reminder | Not emailed yet, and nothing stopping it. |
| Emailed | Sent at least one reminder. |
| Not emailed | Not sent one, with a reason. |
| Recovered | Ordered after a reminder (orders since cancelled show under All abandoned, as "Order cancelled"). |
| Ordered without a reminder | Ordered before any reminder went. These aren't counted as abandoned. |

**Download CSV** downloads the baskets the list is showing (the period, search and filter), all of them, not just the page: email, name, site, items, total, currency, last changed, reminders sent, status, why it wasn't emailed, and the order's number, total and date.

A warning shows when `abandoned-cart:send` has never run, or hasn't run for 15 minutes: check Laravel's scheduler is running (see [Installation](installation.md#the-scheduler)). A notice shows while [test mode](reminders.md#test-mode) is on.

## What the numbers mean

| Number | |
| --- | --- |
| **Abandoned** | Baskets with something in them, left in the period, that were left long enough for the first reminder (1 hour by default) or were sent one. Whether or not they could be emailed. Baskets ordered before any reminder aren't counted; baskets recovered later, or deleted by Cargo, are. |
| **… not emailed** | Abandoned, less emailed: baskets that couldn't be emailed, and any due a first reminder in the next few minutes. |
| **Emailed** | Abandoned baskets sent at least one reminder. |
| **… reminders** | Reminder emails sent in the period, by when they were sent. |
| **Came back** | Abandoned baskets brought back with the button in a reminder. The percentage is of emailed. |
| **Recovered** | Baskets ordered after a reminder, with the order placed in the period (by when it was ordered, not when the basket was left). Orders since cancelled or returned aren't counted. The percentage is of emailed. |
| **Revenue** | The recovered orders' totals, one for each currency. |
| **Not emailed: …** | Abandoned baskets with no reminder sent and a reason, by reason. See [Reminders](reminders.md#why-a-basket-wasnt-emailed). |

Test reminders sent from the Control Panel aren't counted anywhere.

Because recovered orders are counted by order date, and emailed baskets by when they were left, a short period can show a recovery rate that looks high or low: an order today can be for a basket left last month. Over 30 or 90 days it evens out.

## A basket

Click a basket to see:

- **Basket**: what was in it when it last changed, and the total.
- **Reminders**: each one sent, when, its code, when the link was opened ("Opened the link"), and when the button was used ("Brought the basket back", with how many times). Test reminders are marked "test".
- **Details**: its status (and why it wasn't emailed), the order (linked to it in Store → Orders), the shopper (with a link to their account, if they have one), their consent (agreed, said no, or not asked; with the wording they saw, and when), when it last changed, and the site. Once the address has been removed after the [retention period](privacy.md), it says so.
- **Send yourself a test**: email yourself one of the reminders for this basket. See [Emails](emails.md#test-reminders).
- **Stop reminders**: no more reminders for this basket, unless it changes. Shown while it's waiting or emailed.

"Opened the link" can be a mail app's link checker, not the shopper. "Brought the basket back" is a person.

The statuses:

| Status | |
| --- | --- |
| Waiting | Not emailed yet. |
| Emailed | Sent at least one reminder. |
| Not emailed | Couldn't be emailed: the reason is under it. |
| Recovered | Ordered after a reminder. |
| Ordered | Ordered before any reminder. |
| Order cancelled | Recovered, then the order was cancelled or returned. |
| Deleted | Deleted by Cargo before any reminder went. |

## The dashboard widget

"Abandoned carts": the last 30 days' baskets abandoned, emailed, brought back and recovered, and the revenue. It shows the scheduler warning too. Add it to `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'abandoned_cart', 'width' => 50],
],
```

Options: `title`, `days` (30). Only people who can view abandoned carts see it.

## Settings

Tools → Addons → Abandoned Cart → Settings: the reminders, who's emailed, the emails, and test mode. See [Configuration](configuration.md).

## Permissions

| Permission | Lets them |
| --- | --- |
| View abandoned carts | See Store → Abandoned Carts, each basket, and the widget |
| Send test reminders and stop reminders | Send a test from a basket's page, and stop a basket's reminders |
| Download abandoned carts | Download CSV |

The last two come under the first. Super users can do everything.
