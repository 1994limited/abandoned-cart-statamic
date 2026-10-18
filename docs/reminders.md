# Reminders

## Following baskets

Every time Cargo saves a cart with something in it, Abandoned Cart keeps a record of it: the site, the shopper's email address and name, whether they're a guest or signed in, what's in it, the total, the consent answer, and when it last changed. Empty carts aren't kept. The record follows the cart until it's ordered or Cargo deletes it, and stays after that for the reports.

The email address comes from the cart: what the shopper typed at the checkout's customer step, or a signed-in customer's account.

## Which baskets are emailed

`abandoned-cart:send` runs every minute. A basket gets a reminder when it's due (see [When](#when)) and:

- it still has something in it;
- it has an email address;
- the address hasn't [unsubscribed](consent.md#unsubscribing);
- the shopper agreed (opt-in), or didn't say no (soft opt-in): see [Consent](consent.md);
- it's from the right shoppers: everyone, guests only, or signed-in customers only;
- it's worth at least the smallest basket, if you set one;
- the address, or its domain, isn't on the never-email list;
- in test mode, the address is one of the test addresses;
- it hasn't been ordered, and the shopper hasn't ordered since;
- Cargo isn't about to delete it.

Just before each email, the cart is looked at again: if it's been ordered, deleted, or changed in a way the addon didn't hear about, nothing is sent.

## Why a basket wasn't emailed

A basket that can't be emailed is marked with the first reason that applies. Store → Abandoned Carts shows it under the basket's status, and counts them above the list:

| Reason | |
| --- | --- |
| Empty | Nothing in it. |
| No email address | The shopper hadn't given one. |
| Unsubscribed | The address unsubscribed from basket reminders. |
| No consent | Opt-in, and the box wasn't ticked; or soft opt-in, and the shopper said no. |
| Not in the audience | A guest when only signed-in customers are emailed, or the other way round. |
| Below the smallest basket | Worth less than the smallest basket in the settings. |
| On the never-email list | The address or its domain is on the list. |
| Test mode | Test mode is on, and it isn't a test address. |
| Too old | Within a day of Cargo deleting it. |
| Ordered since | The same address placed an order after leaving it. |
| Stopped in the Control Panel | Someone clicked **Stop reminders** on its page. |

A reason stands until the basket changes. When the shopper comes back and changes it (adds an email address, say, or something else to the basket), it's looked at afresh. Ticking the consent box clears it too. So a basket skipped in test mode isn't emailed when test mode is turned off, unless it changes.

## When

Each reminder goes a set number of hours after the basket **last changed**: 1, 24 and 72 by default, with the third switched off. Change them in the [settings](configuration.md). Hours are whole hours, 1 or more.

- **In order, once each.** A basket gets reminder 1, then reminder 2, then reminder 3, soonest first (if you give a later reminder fewer hours, it goes earlier). Each goes once.
- **A change starts the clock again.** A shopper who comes back and changes their basket after the first reminder doesn't get the first one again: the next one is timed from the change. Leave a basket at 9:00, get reminder 1 at 10:00, add a book at 11:00, and reminder 2 comes at 11:00 the next day.
- **Never two close together.** Between two reminders to the same basket there's at least the gap between their hours (23 hours between 1 and 24), up to 12 hours, and never less than an hour.
- **Two at the same hour**: only the first is sent. Reminder 1 can't be switched off.
- **Up to a minute late**, as `abandoned-cart:send` runs every minute.

The wording follows the reminder's number: reminder 2's subject, heading and opening line are reminder 2's.

## Test mode

Try the emails on a live shop without emailing shoppers. In Tools → Addons → Abandoned Cart → Settings → Testing, turn on **Test mode** and list your **Test addresses**. Then only baskets with those addresses are emailed (upper or lower case doesn't matter). Everyone else's are marked "Test mode", and aren't emailed later either, when test mode is turned off, unless they change. Store → Abandoned Carts shows a notice while it's on.

To see a reminder without waiting, send yourself one from any basket's page: see [Emails](emails.md#test-reminders).

## How many at once

No more than `per_minute` reminders (120) go in a minute, for mail providers with a limit. When a run reaches it, it stops, and the rest go with the next run, a minute later. Each run also looks at no more than 500 baskets.

If an email can't be sent (the mail server is down), nothing is recorded, any one-off code is deleted, and it's tried again on the next run. A run that stopped half way (the server restarted) leaves its baskets for an hour, then they're tried again. Two runs at once can't email the same basket.

## Orders

When an order is placed from a basket:

- after a reminder was sent, the basket is **recovered**;
- before any reminder, it was just **ordered**. No reminders go, and it isn't counted as abandoned.

Either way, the same address's other baskets get no more reminders ("Ordered since").

A shopper who brings their basket back with a reminder's button, then signs in, may order from another basket: Cargo can merge their basket into their account's. So an order from the same email address within 7 days of using the button counts as recovered too (`attribution_days` in the [config](configuration.md)).

If a recovered order is cancelled or returned, it no longer counts: the basket shows "Order cancelled".

Test reminders sent from the Control Panel never make a basket recovered.

## Cargo deleting old baskets

Cargo deletes carts that haven't changed for a while: 30 days by default, `carts.purge_abandoned_carts_after` in `config/statamic/cargo.php`. Abandoned Cart works around it:

- A basket within a day of being deleted isn't emailed ("Too old").
- The link in a reminder stops working an hour before Cargo would delete the basket, even if `link_days` is longer. So does a one-off code.
- When Cargo deletes a basket, its record stays, for the reports: shown as "Deleted", or "Emailed" if it was. Nothing more is sent.

Keep Cargo's setting comfortably longer than your last reminder plus `link_days`. With the defaults (72 hours, and 7 days), 30 days is plenty. Set it to 2 days, and reminders after the first day are never sent.
