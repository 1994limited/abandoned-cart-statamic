<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Events\ReminderSent;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Suppression;
use NineteenNinetyFour\AbandonedCart\Status;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SendingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function the_first_reminder_goes_an_hour_after_the_basket_was_left(): void
    {
        Event::fake([ReminderSent::class]);
        $cart = $this->cart();

        $this->travel(59)->minutes();
        $this->assertSame(0, $this->send());

        $this->travel(2)->minutes();
        $this->assertSame(1, $this->send());

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('reader@example.com') && $mail->step->number === 1);
        $this->assertSame(1, $this->basket($cart)->reminders_sent);
        Event::assertDispatched(ReminderSent::class);
    }

    #[Test]
    public function each_reminder_goes_once_and_then_the_next(): void
    {
        $cart = $this->cart();

        $this->travel(2)->hours();
        $this->send();
        $this->send();
        Mail::assertSentCount(1);

        $this->travel(21)->hours();
        $this->send();
        Mail::assertSentCount(1);

        $this->travel(2)->hours();
        $this->send();
        Mail::assertSentCount(2);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->step->number === 2);

        // The third is off by default.
        $this->travel(5)->days();
        $this->send();
        Mail::assertSentCount(2);
        $this->assertSame(2, $this->basket($cart)->reminders_sent);
    }

    #[Test]
    public function the_third_reminder_goes_when_it_is_switched_on(): void
    {
        config(['abandoned-cart.reminders.2.enabled' => true]);
        $this->cart();

        foreach ([2, 23, 49] as $hours) {
            $this->travel($hours)->hours();
            $this->send();
        }

        Mail::assertSentCount(3);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->step->number === 3);
    }

    #[Test]
    public function a_change_to_the_basket_starts_the_clock_again_without_repeating_a_reminder(): void
    {
        $cart = $this->cart();
        $this->travel(2)->hours();
        $this->send();

        // Back to shop, then left again.
        $this->travel(1)->hours();
        $cart = Cart::find($cart->id());
        $cart->lineItems()->create(['product' => 'lantern-ledger', 'quantity' => 1]);
        $cart->save();

        $this->travel(2)->hours();
        $this->send();
        Mail::assertSentCount(1);

        $this->travel(23)->hours();
        $this->send();
        Mail::assertSentCount(2);
        Mail::assertNotSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->step->number === 1 && $mail->reminder->sent_at->isAfter(now()->subHours(3)));
    }

    #[Test]
    public function opt_in_needs_the_box_ticked(): void
    {
        $cart = $this->cart(consent: null);
        $this->travel(2)->hours();

        $this->send();

        Mail::assertNothingSent();
        $this->assertSame('no_consent', $this->basket($cart)->skip_reason);
    }

    #[Test]
    public function soft_opt_in_emails_unless_the_shopper_said_no(): void
    {
        config(['abandoned-cart.consent' => 'soft_opt_in']);
        $this->cart(consent: null, email: 'yes@example.com');
        $this->cart(consent: false, email: 'no@example.com');
        $this->travel(2)->hours();

        $this->send();

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('yes@example.com'));
        Mail::assertNotSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('no@example.com'));
    }

    #[Test]
    public function a_basket_without_an_email_address_is_emailed_once_one_is_added(): void
    {
        $cart = $this->cart(email: null);
        $this->travel(2)->hours();
        $this->send();
        $this->assertSame('no_email', $this->basket($cart)->skip_reason);

        $cart = Cart::find($cart->id());
        $cart->customer(['name' => 'Ada Reader', 'email' => 'reader@example.com'])->save();
        $this->travel(2)->hours();
        $this->send();

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('reader@example.com'));
    }

    #[Test]
    public function unsubscribed_addresses_are_not_emailed_unless_they_tick_the_box_again(): void
    {
        $this->travel(-1)->minutes();
        Suppression::add('reader@example.com');
        $this->travel(1)->minutes();

        $old = $this->cart();
        Basket::query()->whereKey($this->basket($old)->id)->update(['consent_at' => now()->subDay()]);
        $this->travel(2)->hours();
        $this->send();
        Mail::assertNothingSent();
        $this->assertSame('unsubscribed', $this->basket($old)->skip_reason);

        // Ticking the box after unsubscribing is asking again.
        $this->cart();
        $this->travel(2)->hours();
        $this->send();
        Mail::assertSentCount(1);
    }

    #[Test]
    public function addresses_domains_small_baskets_and_audiences_can_be_left_out(): void
    {
        config(['abandoned-cart.exclude' => ['staff@dogear.test', 'example.org'], 'abandoned-cart.minimum_total' => 5]);

        $this->cart(email: 'staff@dogear.test');
        $this->cart(email: 'someone@mail.example.org');
        $this->book('pamphlet', data: ['title' => 'A Pamphlet', 'price' => 299]);
        $small = $this->cart([['product' => 'pamphlet']], email: 'small@example.com');
        $this->cart(email: 'reader@example.com');
        $this->travel(2)->hours();

        $this->send();

        Mail::assertSentCount(1);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('reader@example.com'));
        $this->assertSame('below_minimum', $this->basket($small)->skip_reason);

        config(['abandoned-cart.audience' => 'customers']);
        $guest = $this->cart(email: 'guest@example.com');
        $this->travel(2)->hours();
        $this->send();
        $this->assertSame('audience', $this->basket($guest)->skip_reason);
    }

    #[Test]
    public function test_mode_only_emails_the_test_addresses(): void
    {
        config(['abandoned-cart.test_mode' => true, 'abandoned-cart.test_recipients' => ['me@dogear.test']]);
        $shopper = $this->cart(email: 'reader@example.com');
        $this->cart(email: 'Me@Dogear.test');
        $this->travel(2)->hours();

        $this->send();

        Mail::assertSentCount(1);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('me@dogear.test'));
        $this->assertSame('test_mode', $this->basket($shopper)->skip_reason);

        // Turning it off doesn't email the backlog.
        config(['abandoned-cart.test_mode' => false]);
        $this->send();
        Mail::assertSentCount(1);
    }

    #[Test]
    public function an_order_stops_the_reminders_for_that_basket_and_earlier_ones(): void
    {
        $left = $this->cart();
        $this->travel(10)->minutes();
        $ordered = $this->cart();
        $this->checkout($ordered);
        $this->travel(2)->hours();

        $this->send();

        Mail::assertNothingSent();
        $this->assertSame(Status::Ordered, $this->basket($ordered)->status);
        $this->assertSame('ordered_since', $this->basket($left)->skip_reason);
    }

    #[Test]
    public function deleted_and_nearly_purged_baskets_are_not_emailed(): void
    {
        $deleted = $this->cart(email: 'deleted@example.com');
        Cart::find($deleted->id())->delete();

        config(['statamic.cargo.carts.purge_abandoned_carts_after' => 2]);
        $old = $this->cart(email: 'old@example.com');
        $this->travel(36)->hours();

        $this->send();

        Mail::assertNothingSent();
        $this->assertSame(Status::Deleted, $this->basket($deleted)->status);
        $this->assertSame('expiring', $this->basket($old)->skip_reason);
    }

    #[Test]
    public function a_change_the_addon_did_not_hear_about_is_caught_up_before_sending(): void
    {
        $cart = $this->cart();
        $this->travel(2)->hours();

        // Changed without the event (an import, say).
        $cart = Cart::find($cart->id());
        $cart->set('updated_at', now()->timestamp);
        Event::fakeFor(fn () => $cart->saveWithoutRecalculating());

        $this->send();

        Mail::assertNothingSent();
        $this->assertSame(now()->timestamp, $this->basket($cart)->cart_updated_at->timestamp);
    }

    #[Test]
    public function no_more_than_the_limit_a_minute(): void
    {
        config(['abandoned-cart.per_minute' => 2]);
        foreach (['a', 'b', 'c'] as $name) {
            $this->cart(email: "{$name}@example.com");
        }
        $this->travel(2)->hours();

        $this->send();
        Mail::assertSentCount(2);

        $this->travel(2)->minutes();
        $this->send();
        Mail::assertSentCount(3);
    }

    #[Test]
    public function an_email_that_fails_is_tried_again_next_time(): void
    {
        // A mail server that isn't there.
        config(['mail.mailers.down' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1], 'mail.default' => 'down']);
        Mail::swap(new MailManager(app()));
        $cart = $this->cart();
        $this->travel(2)->hours();

        $this->assertSame(0, $this->send());
        $this->assertSame(0, $this->basket($cart)->reminders_sent);
        $this->assertSame(0, $this->basket($cart)->reminders()->count());
        $this->assertNull($this->basket($cart)->claimed_at);
    }

    #[Test]
    public function a_claimed_basket_is_not_emailed_twice(): void
    {
        $cart = $this->cart();
        $this->travel(2)->hours();
        Basket::query()->whereKey($this->basket($cart)->id)->update(['claimed_at' => now()]);

        $this->send();
        Mail::assertNothingSent();

        // A run that died: released after an hour.
        $this->travel(61)->minutes();
        $this->send();
        Mail::assertSentCount(1);
    }
}
