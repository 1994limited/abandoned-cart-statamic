# Abandoned Cart documentation

Abandoned Cart adds basket reminder emails to [Cargo](https://builtwithcargo.dev), Statamic's e-commerce addon. When a shopper leaves items in their basket, with an email address and their consent, they get up to three reminders. The link in each brings their basket back, at today's prices, with a discount code if you choose one. You see what was left and what was recovered in the Control Panel.

Cargo calls it a cart. These docs call it a basket where shoppers see it, as the emails and pages do.

## Setting up

1. [Installation](installation.md): requirements, installing, the scheduler, licensing, updating.
2. [Configuration](configuration.md): the settings in the Control Panel, and every option in `config/abandoned-cart.php`.
3. [Consent](consent.md): opt-in or soft opt-in, the box, recording consent from your own checkout, and unsubscribing.

## Building the front end

4. [Templates](templates.md): the box in Cargo's prebuilt checkout, signed-in customers, your own markup, and the "Your basket's back" message. Start here.
5. [Tags](tags.md): every `{{ abandoned_cart }}` tag, its parameters and variables.
6. [The demo shop](demo.md): Dog-ear Books, a shop built with Abandoned Cart.

## Running a shop

7. [Reminders](reminders.md): which baskets are emailed, when, and why one wasn't.
8. [Restoring a basket](restoring.md): what happens when a shopper uses the link.
9. [Discounts](discounts.md): an existing Cargo discount, or a one-off code per email.
10. [Emails](emails.md): wording, languages, changing the emails, test reminders and unsubscribing.
11. [The Control Panel](control-panel.md): Store → Abandoned Carts, what the numbers mean, the dashboard widget, and permissions.
12. [Privacy](privacy.md): what's kept and for how long, and answering requests for someone's data.

## Reference

13. [Events](events.md)
14. [PHP API](php-api.md)
15. [HTTP API](http-api.md): the `/!/abandoned-cart/…` endpoints.
16. [Troubleshooting](troubleshooting.md)

## Requirements

- PHP 8.3 or later.
- Statamic 6.35.1 or later, and Cargo 1.15 or later (before 2.0).
- A database for the addon's records (SQLite, MySQL, PostgreSQL or SQL Server), whichever way Cargo keeps its own carts.
- Laravel's scheduler running. No queue needed.
