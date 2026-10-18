<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Events\DiscountSaved;
use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\Discount;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Models\Suppression;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Site;

/**
 * Fixes from an independent (Codex) review of 1.0.
 */
class ReviewFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function unsubscribing_works_after_the_address_is_removed_and_after_the_records_go(): void
    {
        $reminder = $this->remind($this->cart());
        $url = $reminder->unsubscribePostUrl();
        $page = $reminder->unsubscribeUrl();

        $this->travel(91)->days();
        Artisan::call('abandoned-cart:prune');
        $this->assertNull($reminder->refresh()->email);

        $this->get($page)->assertOk()->assertSee('Stop basket reminders to this email address?');
        $this->post($url)->assertSee('You’re unsubscribed');
        $this->assertTrue(Suppression::blocks('reader@example.com'));

        Suppression::lift('reader@example.com');
        $this->travel(300)->days();
        Artisan::call('abandoned-cart:prune');
        $this->assertSame(0, Reminder::query()->count());

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertTrue(Suppression::blocks('reader@example.com'));
    }

    #[Test]
    public function the_hash_in_the_link_cannot_be_changed(): void
    {
        $reminder = $this->remind($this->cart());

        $tampered = preg_replace('/h=[0-9a-f]{64}/', 'h='.Suppression::hash('someone@example.com'), $reminder->unsubscribePostUrl());

        $this->post($tampered)->assertForbidden();
        $this->assertFalse(Suppression::blocks('someone@example.com'));
    }

    #[Test]
    public function a_basket_comes_back_on_its_own_site_from_the_addons_page(): void
    {
        config(['statamic.system.multisite' => true]);
        Site::setSites([
            'default' => ['name' => 'Dog-ear Books', 'url' => 'http://localhost/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']],
            'french' => ['name' => 'Livres Corne', 'url' => 'http://localhost/fr/', 'locale' => 'fr_FR', 'attributes' => ['currency' => 'EUR']],
        ])->save();

        $cart = Cart::make()->site('french')->customer(['name' => 'Ada', 'email' => 'ada@example.com'])->set('abandoned_cart_consent', true);
        $cart->lineItems()->create(['product' => $this->book()->id(), 'quantity' => 1]);
        $cart->save();
        Cart::forgetCurrentCart();
        $reminder = $this->remind($cart);

        // As a browser sends it: from the basket page, on the default site's address.
        $this->withHeader('Referer', $reminder->restoreUrl())
            ->post($reminder->restorePostUrl())
            ->assertRedirect('http://localhost/fr/checkout')
            ->assertCookie('cargo-cart_french', $cart->id());
    }

    #[Test]
    public function a_basket_that_changes_while_its_reminder_is_being_made_is_not_emailed(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        $cart = $this->cart();
        $this->travel(2)->hours();

        // The shopper comes back just as the code is made.
        Event::listen(DiscountSaved::class, fn () => Basket::query()->where('cart_id', $cart->id())->update(['cart_updated_at' => now()]));

        $this->assertSame(0, $this->send());

        Mail::assertNothingSent();
        $basket = $this->basket($cart);
        $this->assertSame(0, $basket->reminders()->count());
        $this->assertNull($basket->claimed_at);
        $this->assertSame(0, $basket->reminders_sent);
        $this->assertCount(0, Discount::all());
    }

    #[Test]
    public function changing_the_reminders_never_skips_or_repeats_one(): void
    {
        config(['abandoned-cart.reminders.2.enabled' => false]);
        $cart = $this->cart();
        $this->remind($cart);
        $this->travel(23)->hours();
        $this->send();
        Mail::assertSentCount(2);

        // Reminder 2 off, reminder 3 on: the basket still gets 3.
        $this->saveSettings(['reminder_2_enabled' => false, 'reminder_3_enabled' => true]);
        $this->travel(2)->days();
        $this->send();

        Mail::assertSentCount(3);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->step->number === 3);
    }

    #[Test]
    public function reordering_the_reminders_does_not_send_one_twice(): void
    {
        $cart = $this->cart();
        $this->remind($cart);

        // Reminder 2 now comes before reminder 1.
        $this->saveSettings(['reminder_1_delay' => 30, 'reminder_2_delay' => 3]);
        $this->travel(2)->days();
        $this->send();
        $this->send();

        Mail::assertSentCount(2);
        $this->assertSame(1, $this->basket($cart)->reminders()->where('step', 1)->count());
        $this->assertSame(1, $this->basket($cart)->reminders()->where('step', 2)->count());
    }

    #[Test]
    public function a_one_off_code_stops_working_when_its_link_expires(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        $reminder = $this->remind($this->cart());

        $this->travelTo($reminder->expires_at->copy()->addMinute());
        $this->send();

        $this->assertNull(Discount::find($reminder->discount_handle));
    }

    #[Test]
    public function a_code_in_the_basket_that_no_longer_works_is_replaced_by_the_reminders(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        Discount::make()->handle('summer')->title('Summer')->type('percentage_off')->set('discount_code', 'SUMMER')->set('percentage_off', 5)->set('end_date', now()->addDay()->format('Y-m-d'))->save();
        $cart = Cart::find($this->cart()->id());
        $cart->set('discount_code', 'SUMMER')->save();
        $reminder = $this->remind($cart, hours: 50);

        $this->post($reminder->restorePostUrl());

        $this->assertSame($reminder->discount_code, Cart::find($cart->id())->get('discount_code'));
    }

    #[Test]
    public function anonymising_removes_everything_that_leads_back_to_the_shopper(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        $cart = $this->cart();
        $reminder = $this->remind($cart);
        $this->checkout($cart);

        $this->travel(91)->days();
        Artisan::call('abandoned-cart:prune');

        $basket = Basket::query()->find($reminder->abandoned_cart_id);
        $this->assertStringStartsWith('anonymised-', $basket->cart_id);
        foreach (['email', 'name', 'customer_id', 'consent_text', 'items', 'order_id', 'order_reference'] as $field) {
            $this->assertNull($basket->{$field}, $field);
        }
        $this->assertSame(999, $basket->total);
        $this->assertNotNull($basket->order_total);
        $reminder->refresh();
        $this->assertNull($reminder->discount_code);
        $this->assertNull($reminder->email_hash);
    }

    #[Test]
    public function later_reminders_are_not_held_up_by_first_ones(): void
    {
        config(['abandoned-cart.per_minute' => 1]);
        $older = $this->cart(email: 'older@example.com');
        $this->remind($older);
        $this->travel(22)->hours();
        $this->cart(email: 'newer@example.com');
        $this->travel(2)->hours();

        $this->send();

        Mail::assertSentCount(2);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('older@example.com') && $mail->step->number === 2);
    }
}
