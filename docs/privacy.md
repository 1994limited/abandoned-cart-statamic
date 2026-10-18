# Privacy

Abandoned Cart keeps what it needs to send reminders and add up what they recovered, and no more. This isn't legal advice: check what your own privacy notice says.

## Consent

Reminders go only to baskets the shopper agreed to (opt-in) or didn't say no to (UK soft opt-in). Each basket keeps the answer, the wording next to the box, and when. Every reminder can be unsubscribed from in one click. See [Consent](consent.md).

## What's kept

For each basket with something in it:

- the shopper's email address and name, their customer account's id (if they're signed in), and Cargo's id for them;
- the site and its language;
- the consent answer, the wording they saw, and when;
- what was in it (each product's id, title, variant, quantity and total), the number of items, the total and the currency, and when it last changed;
- its status, the reminders sent and why one wasn't, and the order (its id, number, total and date), if there is one.

For each reminder: the address it went to, which reminder it was, its code, and when it was sent, when it expires, when its link was opened, and when (and how many times) its button was used.

No IP addresses, no tracking pixel, and nothing about what people do on the site apart from their basket.

Addresses that unsubscribe are kept as a one-way hash (SHA-256), with the date, so they aren't emailed again.

Cargo keeps its own carts and orders, with the same address, as before. The addon doesn't change that.

## How long

`abandoned-cart:prune` runs daily:

| | After the basket last changed |
| --- | --- |
| The email address, name and account ids are removed, from the basket and its reminders. What was in it, the totals and the order stay, for the reports. | 90 days |
| The basket and its reminders are deleted. | 365 days |

Change these with `retention` in the [config](configuration.md). `null` keeps them.

Unused one-off discount codes are deleted a day after they expire (see [Discounts](discounts.md)).

## Someone asks what you keep

In Store → Abandoned Carts, choose **All time**, search for their email address, and **Download CSV**. To include baskets they ordered without a reminder, show **Ordered without a reminder** and download that too. A basket's page shows the rest: the reminders, and the consent wording.

## Someone asks to be forgotten

```bash
php please abandoned-cart:forget someone@example.com
```

This deletes all their baskets, their reminders, and any one-off codes they didn't use. The unsubscribed hash stays, if there is one, so they still aren't emailed. From your own code: `AbandonedCart::forget('someone@example.com')` (see the [PHP API](php-api.md)).

Remember Cargo's own carts and orders: forgetting someone in Abandoned Cart doesn't delete those.

## Unsubscribing

Anyone can stop basket reminders with the link in each one, or their mail app's unsubscribe button. See [Emails](emails.md#unsubscribing).
