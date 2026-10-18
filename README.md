# Abandoned Cart for Statamic

Abandoned cart recovery for [Cargo](https://builtwithcargo.dev). When a shopper leaves items in their basket, they get up to three reminder emails. The link in each brings their basket back, at today's prices, with a discount code if you choose one. You see what was left, what was sent, and what came back as orders.

## Features

- **Up to three reminders.** Each a set number of hours after the basket last changed. A basket that changes starts the clock again, and nobody gets the same reminder twice.
- **Consent built in.** One tag adds the box to your checkout: opt-in (a box to tick), or UK soft opt-in (a box to say no). Each basket keeps the wording the shopper saw, and when.
- **The basket comes back.** The link opens the basket as it is now, with a button that makes it the shopper's basket again. Prices are checked, anything sold out is taken out, and the code is added.
- **Discount codes, if you want them.** One of your Cargo discounts, or a one-off code made for each email: it works once, only for that shopper, until the link expires.
- **Only the right baskets.** Guests, signed-in customers or both. A smallest basket, a never-email list, and a test mode that emails only you.
- **Stops when it should.** An order, a basket emptied or deleted, an unsubscribe: no more reminders.
- **One-click unsubscribe**, in the email and in the headers mail apps use. No open-tracking pixel.
- **Recovery in the Control Panel.** Store → Abandoned Carts: baskets left, emailed, brought back and recovered, the revenue, and why a basket wasn't emailed. A dashboard widget and CSV download.
- **Privacy.** Email addresses are removed after 90 days and records deleted after a year. Forget an address with one command. No IP addresses kept.
- **Multisite.** Emails in the language of the site the basket was on, and the basket brought back on that site.
- **Yours to change.** Wording, logo and colour in the Control Panel. Publish the emails to change anything else. Events and a PHP API.

## Requirements

- Statamic 6.35.1 or later, with Cargo 1.15 or later.
- PHP 8.3 or later, and a database (SQLite is fine).
- Laravel's scheduler running. No queue needed.

## Installation

```bash
composer require 1994/abandoned-cart-statamic
php artisan migrate
```

**Run the scheduler.** Reminders are sent by `abandoned-cart:send`, every minute. If your site doesn't run Laravel's scheduler yet, add this cron entry:

```bash
* * * * * cd /path-to-your-site && php artisan schedule:run >> /dev/null 2>&1
```

The Control Panel shows a warning when `abandoned-cart:send` hasn't run for 15 minutes.

**Ask for consent.** Add the box to your checkout's customer step, next to the email field:

```antlers
{{ abandoned_cart:consent }}
```

<details>
<summary>Blade</summary>

```blade
<s:abandoned_cart:consent />
```

</details>

That's it. Baskets left with an email address and the box ticked get their first reminder an hour later. See [Templates](docs/templates.md) for Cargo's prebuilt checkout, signed-in customers and the "Your basket's back" message, and [Consent](docs/consent.md) for soft opt-in.

## Documentation

Everything else is in [the documentation](docs/README.md):

- [Installation](docs/installation.md) and [configuration](docs/configuration.md)
- [Consent](docs/consent.md), [templates](docs/templates.md), [tags](docs/tags.md) and [the demo shop](docs/demo.md)
- [Reminders](docs/reminders.md), [restoring a basket](docs/restoring.md), [discounts](docs/discounts.md), [emails](docs/emails.md), [the Control Panel](docs/control-panel.md) and [privacy](docs/privacy.md)
- [Events](docs/events.md), [PHP API](docs/php-api.md), [HTTP API](docs/http-api.md) and [troubleshooting](docs/troubleshooting.md)

## Licence

Abandoned Cart is commercial software, sold through the [Statamic Marketplace](https://statamic.com/marketplace). Try it free on a local or development site. Each public site needs a licence. See [LICENSE.md](LICENSE.md).

## Support

Report bugs and ask questions through the addon's page on the Statamic Marketplace, or email [hello@1994.co.uk](mailto:hello@1994.co.uk).

## Development

```bash
composer install && npm install
vendor/bin/phpunit   # tracking, sending, consent, restoring, discounts, emails, the Control Panel, and every example in the docs
npm run build        # the Control Panel pages
```
