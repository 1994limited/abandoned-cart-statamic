# Changelog

## 1.0.0 (2026-10-18)

The first release.

### Added
- **Baskets followed** from Cargo's carts: the shopper, what's in it, the total, the consent answer and when it last changed. Empty carts aren't kept. Records outlive Cargo deleting the cart, for the reports.
- **Up to three reminder emails**, each a set number of hours after the basket last changed (1, 24 and 72 by default; the third off). In order, once each. A change starts the clock again without repeating a reminder. Sent by `abandoned-cart:send` every minute, no queue needed, claimed so two runs can't both send one, no more than 120 a minute, and failed emails tried again.
- **Consent** (`{{ abandoned_cart:consent }}`): opt-in, or UK soft opt-in. The box saves itself as soon as it changes, before there's a basket too. A pair for your own markup. The wording kept with each basket. Consent from your own checkout through an `abandoned_cart_consent` order field, or `AbandonedCart::consent()`.
- **Who's emailed**: guests, customers or both, a smallest basket, a never-email list of addresses and domains, and a test mode. The reason a basket wasn't emailed is kept and shown.
- **Restoring the basket**: the link opens the basket as it is now, with a button that makes it the shopper's again, so link checkers can't. Today's prices, anything unavailable taken out, the reminder's code added. Customer accounts' baskets only for that customer, signed in. On its own site on a multisite. Signed links that expire with the reminder, never after Cargo deletes the basket. `{{ abandoned_cart:restored }}` for the message.
- **Discount codes**: an existing Cargo discount, or a one-off code per email (percentage off, amount off or free shipping) that works once, only for that shopper, until the link expires. Unused codes deleted once expired.
- **The reminder email**, HTML and plain text, with the basket, any code, and wording, logo and colour from the settings, in the basket's language. One-click unsubscribe (`List-Unsubscribe`, RFC 8058). No tracking pixel. Test reminders from a basket's page.
- **Recovered orders**: from the reminded basket, or from the same address within 7 days of using a reminder's button. Cancelled and returned orders no longer count. Other baskets from the same address stop when they order.
- **Control Panel**: Store → Abandoned Carts, with the period's baskets abandoned, emailed, brought back and recovered, the revenue, why baskets weren't emailed, a search, filters and CSV. Each basket's items, reminders, consent and order, with a test reminder and "Stop reminders". A warning when the scheduler isn't running. An "Abandoned carts" dashboard widget. Permissions.
- **Settings** in the Control Panel: the reminders, their discounts and wording, consent, who's emailed, the emails' look, the link's days, and test mode.
- **Privacy**: addresses, and anything else that leads back to the shopper, removed after 90 days and records deleted after 365 (`abandoned-cart:prune`, daily), `abandoned-cart:forget` to delete an address, unsubscribed addresses kept only as a hash. Unsubscribe links keep working after that.
- **Events** (`ReminderSent`, `CartRestored`, `CartRecovered`) and a **PHP API** (the `AbandonedCart` facade).
