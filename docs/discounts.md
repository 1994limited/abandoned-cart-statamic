# Discounts

Each reminder can carry a discount code: none (the default), one of your Cargo discounts, or a one-off code made for each email. Choose for each reminder in Tools → Addons → Abandoned Cart → Settings → Reminders, or with `reminders` in the [config](configuration.md).

Reminder 3 is off by default, with 10% off. Think twice before putting a discount in the first reminder: shoppers learn to leave their basket and wait for one.

## An existing discount

Choose **An existing discount**, then the discount. The list has your Cargo discounts (Store → Discounts) that have a code. Everyone gets the same code, and the discount's own rules apply: its dates, its products, its uses.

A discount without a code, or one that's been deleted, sends the reminder without a code.

## A one-off code

Choose **A one-off code**, then the type:

- **Percentage off**: the amount is the percentage (`10` for 10% off).
- **Amount off**: the amount is in the site's currency (`5` for £5.00 off).
- **Free shipping**: no amount.

Each reminder gets its own code, like `COMEBACK-7KQ2XM` (the prefix is `discount_prefix` in the config; the letters and numbers can't be mistaken for each other). It's a Cargo discount, listed in Store → Discounts as "Abandoned cart reminder (COMEBACK-7KQ2XM)", and it:

- works once;
- works only for that shopper: the basket's customer (a guest's email address, or a customer account);
- works until the end of the day the link in the reminder expires.

Unused one-off codes are deleted by `abandoned-cart:prune` a day after they expire. Used ones are kept.

If a code can't be made, the reminder goes without one (and the reason is logged): better a reminder without a code than none. If the email can't be sent, its code is deleted.

## In the email

The code is in a box under the basket, with the wording from **Under the code**: "That's 10% off, added when your basket comes back. It works until 25 October 2026." In the wording, `:code` is the code, `:discount` says what it's worth ("10% off", "£5.00 off", "free delivery"), and `:expires` is the day the link stops working. See [Emails](emails.md).

## When the basket comes back

The code is added to the basket by the button on the reminder's page, so the shopper doesn't type it. If the basket already has a code (one they entered themselves), that one is kept. See [Restoring a basket](restoring.md).

Test reminders show `COMEBACK-TEST`. No discount is made for them.
