**Abandoned cart recovery for [Cargo](https://builtwithcargo.dev). When a shopper leaves items in their basket, they get up to three reminder emails. The link in each brings their basket back, at today's prices, with a discount code if you choose one. You see what was left, what was sent, and what came back as orders.**

## Up to three reminders

Each a set number of hours after the basket last changed. A basket that changes starts the clock again, and nobody gets the same reminder twice.

## Consent built in

One tag adds the box to your checkout: opt-in (a box to tick), or UK soft opt-in (a box to say no). Each basket keeps the wording the shopper saw, and when.

## The basket comes back

The link opens the basket as it is now, with a button that makes it the shopper's basket again. Prices are checked, anything sold out is taken out, and the code is added.

## Discount codes, if you want them

One of your Cargo discounts, or a one-off code made for each email: it works once, only for that shopper, until the link expires.

## Only the right baskets

Guests, signed-in customers or both. A smallest basket, a never-email list, and a test mode that emails only you.

## Stops when it should

An order, a basket emptied or deleted, an unsubscribe: no more reminders.

## One-click unsubscribe

In the email and in the headers mail apps use. No open-tracking pixel.

## Recovery in the Control Panel

**Store → Abandoned Carts**: baskets left, emailed, brought back and recovered, the revenue, and why a basket wasn't emailed. A dashboard widget and CSV download.

## Privacy

Email addresses are removed after 90 days and records deleted after a year. Forget an address with one command. No IP addresses kept.

## Multisite

Emails in the language of the site the basket was on, and the basket brought back on that site.

## Yours to change

Wording, logo and colour in the Control Panel. Publish the emails to change anything else. Events and a PHP API.

## Requirements

- Statamic 6.35.1 or later
- **Cargo 1.15 or later**
- PHP 8.3 or later, and a database (SQLite is fine)
- Laravel's scheduler running. No queue needed.
