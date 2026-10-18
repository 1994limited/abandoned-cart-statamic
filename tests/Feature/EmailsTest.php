<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EmailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function a_reminder_shows_the_basket_and_links_back_to_it(): void
    {
        $this->editions();
        $reminder = $this->remind($this->cart([['product' => 'lantern-ledger', 'quantity' => 2], ['product' => 'coral-arithmetic', 'variant' => 'Paperback']]));

        Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($reminder) {
            $html = $mail->render();
            $mail->assertHasSubject('You left something in your basket');

            return str_contains($html, 'Still thinking it over?')
                && str_contains($html, 'Hi Ada, we’ve saved your basket at Dog-ear Books.')
                && str_contains($html, 'The Lantern Ledger')
                && str_contains($html, 'Qty 2')
                && str_contains($html, '£19.98')
                && str_contains($html, 'Paperback')
                && str_contains($html, '£32.98')
                && str_contains($html, e($reminder->restoreUrl()))
                && str_contains($html, e($reminder->unsubscribeUrl()));
        });
    }

    #[Test]
    public function the_plain_text_version_has_the_links_unescaped(): void
    {
        $reminder = $this->remind($this->cart());

        Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($reminder) {
            $text = (fn () => $this->renderForAssertions()[1])->call($mail);

            return str_contains($text, 'Back to my basket: '.$reminder->restoreUrl());
        });
    }

    #[Test]
    public function the_wording_logo_and_colour_come_from_the_settings(): void
    {
        $this->saveSettings([
            'reminder_1_subject' => ':name, your books are waiting',
            'reminder_1_heading' => 'Back for more?',
            'button' => 'Finish my order',
            'accent_colour' => '#ffcc00',
        ]);

        $this->remind($this->cart());

        Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) {
            $html = $mail->render();
            $mail->assertHasSubject('Ada, your books are waiting');

            return str_contains($html, 'Back for more?')
                && str_contains($html, 'Finish my order')
                && str_contains($html, 'background:#ffcc00')
                // Dark text reads better on yellow.
                && str_contains($html, 'color:#111827');
        });
    }

    #[Test]
    public function the_second_reminder_has_its_own_wording(): void
    {
        $cart = $this->cart();
        $this->remind($cart);
        $this->travel(23)->hours();
        $this->send();

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->step->number === 2 && $mail->hasSubject('Your basket at Dog-ear Books is waiting'));
    }

    #[Test]
    public function shoppers_names_are_escaped(): void
    {
        $cart = $this->cart();
        $basket = $this->basket($cart);
        $basket->forceFill(['name' => '<b>Ada</b> Reader'])->save();
        $this->travel(2)->hours();
        $this->send();

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => str_contains($mail->render(), 'Hi &lt;b&gt;Ada&lt;/b&gt;'));
    }

    #[Test]
    public function a_bcc_gets_a_copy(): void
    {
        config(['abandoned-cart.email.bcc' => 'copies@dogear.test']);

        $this->remind($this->cart());

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasBcc('copies@dogear.test'));
    }
}
