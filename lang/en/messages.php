<?php

return [

    // The front end.
    'restored' => 'Your basket’s back. Pick up where you left off.',
    'restored_removed' => 'Your basket’s back. We took out what’s no longer available: :items.',
    'unsubscribed' => 'You’re unsubscribed. We won’t send you any more basket reminders.',
    'already_unsubscribed' => 'You’re already unsubscribed.',

    'status' => [
        'open' => 'Open',
        'recovered' => 'Recovered',
        'ordered' => 'Ordered',
        'cancelled' => 'Cancelled',
        'deleted' => 'Deleted',
    ],

    // Why a basket wasn't emailed.
    'reasons' => [
        'empty' => 'Empty',
        'no_email' => 'No email address',
        'no_consent' => 'No consent',
        'unsubscribed' => 'Unsubscribed',
        'audience' => 'Not in the audience',
        'below_minimum' => 'Below the smallest basket',
        'excluded' => 'On the never-email list',
        'test_mode' => 'Test mode',
        'expiring' => 'Too old',
        'ordered_since' => 'Ordered since',
        'stopped' => 'Stopped in the Control Panel',
    ],

    // Defaults for the settings (Tools → Addons → Abandoned Cart → Settings).
    // Placeholders: :name (first name), :site, :code, :discount, :expires.
    'defaults' => [
        'consent_text' => 'Email me a reminder if I leave items in my basket.',
        'opt_out_text' => 'We may email you a reminder if you leave items in your basket. Tick if you’d rather we didn’t.',
        'reminder_1_subject' => 'You left something in your basket',
        'reminder_1_heading' => 'Still thinking it over?',
        'reminder_1_intro' => 'Hi :name, we’ve saved your basket at :site. Pick up where you left off whenever you’re ready.',
        'reminder_2_subject' => 'Your basket at :site is waiting',
        'reminder_2_heading' => 'Your basket’s still here',
        'reminder_2_intro' => 'We’ve kept it for you, but nothing’s reserved: things can sell out.',
        'reminder_3_subject' => 'A little something for your basket at :site',
        'reminder_3_heading' => 'A little something to help',
        'reminder_3_intro' => 'Your basket’s still waiting, with :discount if you’d like it.',
        'button' => 'Back to my basket',
        'discount_text' => 'That’s :discount, added when your basket comes back. It works until :expires.',
        'footer' => 'You’re getting this because you left items in your basket at :site.',
    ],

    'discount' => [
        'percentage_off' => ':value% off',
        'amount_off' => ':value off',
        'free_shipping' => 'free delivery',
    ],

    'discount_title' => 'Abandoned cart reminder (:code)',

    'email' => [
        'there' => 'there',
        'quantity' => 'Qty :count',
        'total' => 'Total',
        'unsubscribe' => 'Unsubscribe from basket reminders',
    ],

    'basket' => [
        'title' => 'Your basket',
        'intro' => 'Here’s what you left at :site, at today’s prices.',
        'unavailable' => 'no longer available',
        'removed_note' => 'Anything no longer available is taken out when your basket comes back.',
        'code' => 'Added to your basket for you.',
        'test' => 'This is a test reminder, sent from the Control Panel: the button is switched off.',
        'expired' => 'That link has expired. Your basket may still be on our site if you shop with the same browser.',
        'gone' => 'That basket isn’t around any more.',
        'ordered' => 'You’ve already ordered this basket. Thank you!',
        'sign_in' => 'This basket belongs to an account. Sign in, and it’ll be waiting.',
        'sign_in_button' => 'Sign in',
    ],

    'page' => [
        'unsubscribe_title' => 'Unsubscribe?',
        'unsubscribe_text' => 'Stop basket reminders to :email?',
        'unsubscribe_button' => 'Unsubscribe',
        'unsubscribed_title' => 'Unsubscribed',
        'expired_title' => 'Link expired',
        'gone_title' => 'Basket not found',
        'ordered_title' => 'Already ordered',
        'sign_in_title' => 'Sign in to see your basket',
        'test_title' => 'Test reminder',
        'back' => 'Back to :site',
    ],

    // The Control Panel.
    'cp' => [
        'title' => 'Abandoned Carts',
        'widget' => 'Abandoned carts',
        'reminder' => 'Reminder :number',
        'test_sent' => 'Sent to :email.',
        'test_failed' => 'The test email couldn’t be sent. Check the mail settings and the log.',
        'stopped' => 'No more reminders for this basket, unless it changes.',
        'scheduler_never' => 'Reminders haven’t been sent yet: abandoned-cart:send hasn’t run. Laravel’s scheduler needs a cron entry for schedule:run.',
        'scheduler_stale' => 'abandoned-cart:send last ran :ago. Check Laravel’s scheduler is running (a cron entry for schedule:run).',
        'statuses' => [
            'waiting' => 'Waiting',
            'emailed' => 'Emailed',
            'not_emailed' => 'Not emailed',
            'recovered' => 'Recovered',
            'ordered' => 'Ordered',
            'cancelled' => 'Order cancelled',
            'deleted' => 'Deleted',
        ],
        'consent' => [
            'given' => 'Agreed',
            'refused' => 'Said no',
            'none' => 'Not asked',
            'not_emailed' => 'not emailed',
        ],
    ],

    'permissions' => [
        'group' => 'Abandoned Cart',
        'view' => 'View abandoned carts',
        'manage' => 'Send test reminders and stop reminders',
        'export' => 'Download abandoned carts',
    ],

];
