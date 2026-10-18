# Restoring a basket

The button in a reminder ("Back to my basket") brings the shopper's basket back, so they can pick up where they left off.

## The link, the page, the button

1. **The link** in the email opens a page on your site: the basket as it is now, at today's prices, with anything no longer available marked, any discount code, and a button.
2. **The button** makes it the shopper's basket again, and goes to your checkout with a message (see [Templates](templates.md#your-baskets-back)).

Why a page with a button, not just a link? Mail apps and virus checkers open the links in emails by themselves, to check them. If opening the link brought the basket back, the checker would get it, not the shopper, and the reports would count clicks nobody made. Opening the page changes nothing: only the button (a `POST`) does. The CP shows both: when the link was opened, and when the button was used.

The page is the addon's own, in the basket's language and with your brand colour. To change it, publish the views (see [Emails](emails.md#changing-the-emails)) and edit `basket.blade.php`.

## What comes back

- **The same basket.** The button makes the reminded basket the visitor's basket on that browser. **It replaces whatever basket the visitor has now**: if they'd started another one since, that one is no longer theirs on this browser (Cargo keeps it until it deletes old baskets).
- **Today's prices.** Prices are worked out again. The page shows the old price struck through next to any that changed.
- **Without what's unavailable.** Anything that's been deleted, unpublished, or doesn't have enough stock for the quantity in the basket is taken out (not reduced), and the message says what.
- **With the code.** A reminder's discount code is added, unless the basket already has one: a code the shopper entered themselves is kept. Cargo checks it like any other code.
- **On its own site.** On a multisite, Cargo keeps a basket per site (in a cookie per site: `cargo-cart`, and `cargo-cart_french` for a site called `french`). The basket comes back on the site it was left on, and the button goes to that site's checkout (`/fr/checkout`).

## Where it goes after

To `/checkout` on the basket's site. Change it with `redirect` in the [config](configuration.md): a path (on the basket's own site) or a full URL. The message is in `{{ abandoned_cart:restored }}`.

## When it can't come back

| The page says | When |
| --- | --- |
| **Link expired** | The link is older than `link_days` (7 days), or Cargo is about to delete the basket. |
| **Already ordered** | The basket was ordered since. |
| **Basket not found** | Cargo has deleted the basket. |
| **Sign in to see your basket** | It belongs to a customer account, and they aren't signed in as that customer. |

The link expires after `link_days` (7 days by default), and never later than an hour before Cargo deletes the basket (see [Reminders](reminders.md#cargo-deleting-old-baskets)). One-off codes stop working at the same time.

**Customer accounts.** A basket that belongs to a customer account only comes back for that customer, signed in, so a forwarded email can't hand it to someone else. The page has a **Sign in** button to `login_url` in the config (the home page if it's not set). Once they've signed in, the link works.

## Test reminders

A test reminder's link (see [Emails](emails.md#test-reminders)) shows the basket, with a note that it's a test, and no button. Nothing about the basket changes.

## Links are signed

The link is signed: changing it stops it working. So nobody can guess another shopper's link. The button posts to the same signed address, without a CSRF token (mail apps' scanners and the email itself can't have one); the signature is checked instead. See the [HTTP API](http-api.md).
