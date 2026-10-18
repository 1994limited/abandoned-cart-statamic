<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    |
    | Up to three reminder emails, each this many hours after the basket was
    | last changed. A basket that changes in between starts the clock again,
    | and carries on from the next reminder: nobody gets the same one twice.
    |
    | discount: none (null), the handle of one of your Cargo discounts (its
    | code goes in the email), or a one-off code made for each email:
    |
    |   ['type' => 'percentage_off', 'value' => 10]   10% off
    |   ['type' => 'amount_off', 'value' => 5]        5.00 off (the site's currency)
    |   ['type' => 'free_shipping']
    |
    | One-off codes work once, only for that shopper, until the link in the
    | email expires (link_days below).
    |
    | All of this can be changed in the Control Panel:
    | Tools → Addons → Abandoned Cart → Settings.
    |
    */

    'reminders' => [
        ['enabled' => true, 'delay' => 1, 'discount' => null],
        ['enabled' => true, 'delay' => 24, 'discount' => null],
        ['enabled' => false, 'delay' => 72, 'discount' => ['type' => 'percentage_off', 'value' => 10]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent
    |--------------------------------------------------------------------------
    |
    | Reminder emails are usually marketing, so they need the shopper's say-so.
    | Put {{ abandoned_cart:consent }} where the email address is asked for
    | (the first step of the checkout).
    |
    |   'opt_in'      – a tick box, unticked. Only baskets where it was ticked
    |                   are emailed. Works everywhere.
    |   'soft_opt_in' – a notice, and a box to tick to say no. Baskets are
    |                   emailed unless it was ticked. UK "soft opt-in" (PECR):
    |                   check it fits your shop before choosing it.
    |
    */

    'consent' => 'opt_in',

    /*
    |--------------------------------------------------------------------------
    | Who's emailed
    |--------------------------------------------------------------------------
    |
    | audience: 'all', 'guests' (no account) or 'customers' (signed in).
    | minimum_total: baskets worth less than this (in the site's currency,
    | 25 for 25.00) aren't emailed. Null for any amount.
    | exclude: addresses or domains never emailed ('staff@shop.com',
    | 'shop.com').
    |
    */

    'audience' => 'all',

    'minimum_total' => null,

    'exclude' => [],

    /*
    |--------------------------------------------------------------------------
    | The link in the emails
    |--------------------------------------------------------------------------
    |
    | link_days: how long the link (and any one-off code) works. Never past
    | the day Cargo deletes the basket (statamic.cargo.carts.
    | purge_abandoned_carts_after).
    |
    | redirect: where the shopper goes once their basket is back.
    |
    | login_url: carts belonging to a customer account are only brought back
    | for that customer, signed in. This is where they're sent to sign in;
    | null links to the home page.
    |
    */

    'link_days' => 7,

    'redirect' => '/checkout',

    'login_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Recovered orders
    |--------------------------------------------------------------------------
    |
    | An order from the basket after a reminder counts as recovered. So does
    | an order from the same email address within this many days of clicking
    | a reminder (signing in can move the basket into the account's own).
    |
    */

    'attribution_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | One-off discount codes
    |--------------------------------------------------------------------------
    |
    | Codes look like COMEBACK-7KQ2XM. They're Cargo discounts, listed in
    | Store → Discounts; unused ones are deleted once they've expired.
    |
    */

    'discount_prefix' => 'COMEBACK',

    /*
    |--------------------------------------------------------------------------
    | Test mode
    |--------------------------------------------------------------------------
    |
    | While on, only baskets with these email addresses are emailed; the rest
    | are skipped (and aren't emailed later either).
    |
    */

    'test_mode' => false,

    'test_recipients' => [],

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    |
    | per_minute: the most reminder emails sent a minute, for mail providers
    | with a limit. The rest go next time.
    |
    | bcc: an address that gets a copy of every reminder.
    |
    */

    'per_minute' => 120,

    'email' => [
        'bcc' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | Reminders are sent by `php please abandoned-cart:send`, every five
    | minutes, and old records tidied by `abandoned-cart:prune`, daily. Both
    | need Laravel's scheduler running (a cron entry for schedule:run). Set
    | false to schedule them yourself.
    |
    */

    'schedule' => true,

    /*
    |--------------------------------------------------------------------------
    | Keeping data
    |--------------------------------------------------------------------------
    |
    | anonymise: days after the basket's last change when the email address
    | and name are removed. The totals stay, for the reports.
    | delete: days after which the record goes altogether.
    |
    */

    'retention' => [
        'anonymise' => 90,
        'delete' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Publish the addon's views (php artisan vendor:publish
    | --tag=abandoned-cart-views) and edit them, or point these at your own.
    |
    */

    'views' => [
        'reminder' => 'abandoned-cart::emails.reminder',
        'reminder_text' => 'abandoned-cart::emails.reminder-text',
        'basket' => 'abandoned-cart::basket',
        'page' => 'abandoned-cart::page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Baskets and reminders are kept in database tables, whichever way Cargo
    | keeps its own carts: sending needs a database's locks.
    |
    */

    'database' => [
        'connection' => null,
    ],

];
