<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\Discount;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DiscountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function a_one_off_code_works_once_for_that_shopper_until_the_link_expires(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        $cart = $this->cart();

        $reminder = $this->remind($cart);

        $this->assertMatchesRegularExpression('/^COMEBACK-[A-HJ-NP-Z2-9]{6}$/', $reminder->discount_code);
        $discount = Discount::findByDiscountCode($reminder->discount_code);
        $this->assertNotNull($discount);
        $this->assertSame('percentage_off', $discount->type());
        $this->assertSame(10, $discount->get('percentage_off'));
        $this->assertSame(['guest::reader@example.com'], $discount->get('customers'));
        $this->assertSame(1, $discount->get('maximum_uses'));
        $this->assertSame(now()->addDays(8)->format('Y-m-d'), $discount->get('end_date'));

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->render() !== '' && str_contains($mail->render(), $reminder->discount_code) && str_contains($mail->render(), '10% off'));
    }

    #[Test]
    public function the_code_is_added_when_the_basket_comes_back(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'amount_off', 'value' => 2]]);
        $cart = $this->cart();
        $reminder = $this->remind($cart);

        $this->post($reminder->restorePostUrl());

        $cart = Cart::find($cart->id());
        $this->assertSame($reminder->discount_code, $cart->get('discount_code'));
        $this->assertSame(200, $cart->discountTotal());
    }

    #[Test]
    public function a_code_the_shopper_already_entered_is_kept(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'free_shipping']]);
        Discount::make()->handle('welcome')->title('Welcome')->type('percentage_off')->set('discount_code', 'WELCOME')->set('percentage_off', 5)->save();
        $cart = $this->cart();
        $cart = Cart::find($cart->id());
        $cart->set('discount_code', 'WELCOME')->save();
        $reminder = $this->remind($cart);

        $this->post($reminder->restorePostUrl());

        $this->assertSame('WELCOME', Cart::find($cart->id())->get('discount_code'));
    }

    #[Test]
    public function an_existing_discount_gives_everyone_its_code(): void
    {
        Discount::make()->handle('come-back')->title('Come back')->type('percentage_off')->set('discount_code', 'COMEBACK15')->set('percentage_off', 15)->save();
        config(['abandoned-cart.reminders.0.discount' => 'come-back']);
        $cart = $this->cart();

        $reminder = $this->remind($cart);

        $this->assertSame('COMEBACK15', $reminder->discount_code);
        $this->assertFalse($reminder->discount_generated);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => str_contains($mail->render(), '15% off'));
    }

    #[Test]
    public function the_settings_choose_the_discount(): void
    {
        $this->saveSettings(['reminder_1_discount' => 'generate', 'reminder_1_discount_type' => 'amount_off', 'reminder_1_discount_value' => 5]);
        $cart = $this->cart();

        $reminder = $this->remind($cart);

        $this->assertSame(500, Discount::findByDiscountCode($reminder->discount_code)->get('amount_off'));
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => str_contains($mail->render(), '£5.00 off'));
    }

    #[Test]
    public function unused_codes_are_deleted_once_they_expire(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        $reminder = $this->remind($this->cart());
        $handle = $reminder->discount_handle;

        $this->travel(7)->days();
        Artisan::call('abandoned-cart:prune');
        $this->assertNotNull(Discount::find($handle));

        $this->travel(2)->days();
        Artisan::call('abandoned-cart:prune');
        $this->assertNull(Discount::find($handle));
    }
}
