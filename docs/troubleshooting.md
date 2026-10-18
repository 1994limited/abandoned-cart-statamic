# Troubleshooting

**Nobody gets a reminder.**

- Is the scheduler running? Store → Abandoned Carts shows a warning if `abandoned-cart:send` hasn't run for 15 minutes. Run `php please abandoned-cart:send` to see what it sends. See [Installation](installation.md#the-scheduler).
- Is the box on the checkout? With opt-in, only baskets where it was ticked are emailed. Store → Abandoned Carts → a basket shows its consent.
- Did the shopper give an email address? Baskets without one are "No email address" until they do.
- Has it been long enough? The first reminder goes an hour after the basket **last changed**, and `abandoned-cart:send` runs every minute.
- Is test mode on? Only the test addresses are emailed.
- Store → Abandoned Carts says why each basket wasn't emailed. See [Reminders](reminders.md#why-a-basket-wasnt-emailed).
- Check `storage/logs/laravel.log` for mail errors. Reminders that couldn't be sent are tried again on the next run.

**The warning says `abandoned-cart:send` hasn't run, but it has.** When it last ran is kept in Laravel's cache. If the command line and the website use different cache stores, or the cache was cleared, the warning shows until the next run. Check `CACHE_STORE` is the same for both, and not `array`.

**The box doesn't show for signed-in customers.** Cargo's prebuilt customer step is only for guests. Put the box on your basket page for signed-in customers. See [Templates](templates.md#signed-in-customers).

**Ticking the box doesn't stick.** The box saves itself with a small script, posting to `/!/abandoned-cart/consent`: check the browser's console for errors. A `419` means the page's CSRF token was out of date: don't cache checkout and basket pages statically. With your own markup (the tag as a pair), you post it yourself. See [Templates](templates.md#your-own-markup).

**Unticking the box doesn't stick.** If your order blueprint has an `abandoned_cart_consent` field, the field wins whenever the basket is saved. Don't use it with the ready-made `{{ abandoned_cart:consent }}` box, and send `0` with the field when the box isn't ticked. See [Consent](consent.md#an-order-blueprint-field).

**Someone who unsubscribed isn't emailed after ticking the box.** Their baskets from before they unsubscribed stay unsubscribed. A basket they tick the box for after unsubscribing is emailed. With soft opt-in, there's no box to ask again with. See [Consent](consent.md#ticking-the-box-again).

**419 Page Expired from the button on the basket page, or a mail app's unsubscribe.** These post without a CSRF token, and Abandoned Cart exempts them (their signed link is checked instead). If you list your own CSRF exceptions in `bootstrap/app.php` with `validateCsrfTokens()` or `preventRequestForgery()`, that's fine: the lists are merged. If you've replaced Laravel's CSRF middleware with one that ignores its exception list, add `!/abandoned-cart/basket/*` and `!/abandoned-cart/unsubscribe/*` to it.

**The link says it's expired.** Links work for `link_days` (7 days), and never later than an hour before Cargo deletes the basket. See [Restoring a basket](restoring.md#when-it-cant-come-back).

**"Sign in to see your basket".** The basket belongs to a customer account, and only comes back for that customer, signed in. Set `login_url` in the config to your sign-in page.

**Something's missing from the basket that came back.** Anything deleted, unpublished, or without enough stock is taken out, and the message says what. See [Restoring a basket](restoring.md#what-comes-back).

**The discount code didn't apply.** A code already in the basket is kept, not replaced. A one-off code works only for that shopper: if they changed their email address at the checkout, or signed in to a different account, it won't apply. It works once, until the link expires.

**Reminders stop after the first day.** Cargo deletes old baskets after `carts.purge_abandoned_carts_after` days, and the addon doesn't email baskets about to go. Keep it longer than your last reminder plus `link_days`. See [Reminders](reminders.md#cargo-deleting-old-baskets).

**Emails go out in the wrong language.** They use the language of the site the basket was on, from `lang/`. Wording typed in the settings is used for every site: leave it empty to use translations. See [Emails](emails.md#languages).

**Fewer reminders than expected went out.** No more than `per_minute` (120) go in a minute; the rest go with the next run, a minute later. See [Reminders](reminders.md#how-many-at-once).

Still stuck? Email hello@1994.co.uk.
