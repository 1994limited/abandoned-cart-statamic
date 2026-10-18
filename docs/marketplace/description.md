**Abandoned cart recovery for Cargo. When a shopper leaves items in their basket, they get up to three reminder emails, and the link in each brings their basket back, at today's prices, with a discount code if you choose one. You see what was left, what was sent, and what came back as orders.**

## Consent built in

One tag adds the box to your checkout. Opt-in (a box to tick, which works everywhere) or UK soft opt-in (a box to say no). Each basket keeps the wording the shopper saw, and when.

## Up to three reminders

Each a set number of hours after the basket last changed: an hour, a day, three days, or whatever suits you. A basket that changes starts the clock again, and nobody gets the same reminder twice.

## The basket comes back

The link opens the basket as it is now, with a button that makes it the shopper's basket again, on the right site. Prices are checked, anything sold out is taken out, and the code is added. Mail apps' link checkers can't do it for them. A customer account's basket only comes back for that customer, signed in.

## Discount codes, if you want them

One of your Cargo discounts, or a one-off code made for each email: it works once, only for that shopper, and stops when the link expires.

## Only the right baskets

Guests, signed-in customers or both. A smallest basket, a never-email list, and a test mode that emails only you. Send yourself any reminder from the Control Panel.

## Stops when it should

An order, an empty or deleted basket, an unsubscribe: no more reminders.

## One-click unsubscribe

In the email, and in the headers mail apps use. No open-tracking pixel.

## Recovery in the Control Panel

**Store → Abandoned Carts**: baskets left, emailed, brought back and recovered, the revenue, and why a basket wasn't emailed. Each basket's reminders, codes and clicks. A dashboard widget and CSV download.

## Privacy

Email addresses and anything else that leads back to the shopper are removed after 90 days, and records deleted after a year. Forget an address with one command.

## Multisite

Emails in the language of the site the basket was on, and the basket brought back on that site.

## Yours to change

Wording, logo and colour in the Control Panel. Publish the emails and pages to change anything else. Events and a PHP API.

## Requirements

- Statamic 6.35.1 or later
- **Cargo 1.15 or later**
- PHP 8.3 or later, and a database (SQLite is fine)
- Laravel's scheduler running. No queue needed.
