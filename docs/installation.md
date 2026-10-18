# Installation

## Requirements

- PHP 8.3 or later
- Statamic 6.35.1 or later
- Cargo 1.15 or later, before 2.0
- A database: baskets and reminders are kept in tables, whichever way Cargo keeps its own carts (sending needs a database's locks)
- Laravel's scheduler running. No queue is needed: reminders are sent by a scheduled command.

## Install

Install and set up [Cargo](https://builtwithcargo.dev/docs/installation) first (`php please cargo:install`). Then:

```bash
composer require 1994/abandoned-cart-statamic
php artisan migrate
```

The migration comes with the addon, so there's nothing to publish: `php artisan migrate` runs it with your own. It adds three tables, all starting `abandoned_cart`. To use another database connection, set it before migrating (see [Configuration](configuration.md)). To keep the migration in your own app, publish it:

```bash
php artisan vendor:publish --tag=abandoned-cart-migrations
```

From then on, every Cargo cart with something in it is followed: who it's for, what's in it, and when it last changed. Carts from before the addon was installed are picked up the next time they change.

## The scheduler

Laravel's scheduler has to be running. Abandoned Cart schedules two commands:

- `abandoned-cart:send`, every minute: emails the reminders that are due.
- `abandoned-cart:prune`, daily: deletes expired one-off codes, and anonymises and deletes old records (see [Privacy](privacy.md)).

If your site doesn't run the scheduler yet, add the cron entry from [Laravel's docs](https://laravel.com/docs/scheduling#running-the-scheduler):

```bash
* * * * * cd /path-to-your-site && php artisan schedule:run >> /dev/null 2>&1
```

Store → Abandoned Carts and the dashboard widget show a warning when `abandoned-cart:send` has never run, or hasn't run for 15 minutes.

To schedule them yourself instead, set `'schedule' => false` in the config.

**No queue needed.** Reminders are sent by `abandoned-cart:send` itself, so they never slow down a page. A queue doesn't change anything.

## Emails

Abandoned Cart sends with your site's mailer (`MAIL_MAILER`). Set a real "from" address (`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`): the reminders come from your shop.

## Consent

Reminders go only to baskets the shopper agreed to (or, with soft opt-in, didn't say no to). Add `{{ abandoned_cart:consent }}` to your checkout: see [Templates](templates.md) and [Consent](consent.md). With opt-in, the default, nobody is emailed until you do.

## Publishing

| Tag | What |
| --- | --- |
| `abandoned-cart-config` | `config/abandoned-cart.php` |
| `abandoned-cart-views` | The emails and the pages the links open, to `resources/views/vendor/abandoned-cart` |
| `abandoned-cart-translations` | The wording, to `lang/vendor/abandoned-cart` |
| `abandoned-cart-migrations` | The migration |

```bash
php artisan vendor:publish --tag=abandoned-cart-config
```

## Licensing

Abandoned Cart is commercial software, sold on the [Statamic Marketplace](https://statamic.com/addons). It's free to try on local and staging sites. Buy a licence for each live site, and add it in the [Statamic Marketplace](https://statamic.com/account) as you would any addon. See [LICENSE.md](../LICENSE.md).

## Updating

```bash
composer update 1994/abandoned-cart-statamic
php artisan migrate
```

Read the [changelog](../CHANGELOG.md) first.
