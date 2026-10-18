<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Models\Suppression;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UnsubscribeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function the_link_opens_a_page_with_a_button(): void
    {
        $reminder = $this->remind($this->cart());

        $this->get($reminder->unsubscribeUrl())->assertOk()->assertSee('Stop basket reminders to reader@example.com?')->assertSee('Unsubscribe');

        $this->assertFalse(Suppression::blocks('reader@example.com'));
    }

    #[Test]
    public function the_button_stops_every_reminder_to_that_address(): void
    {
        $other = $this->cart();
        $this->travel(5)->minutes();
        $reminder = $this->remind($this->cart());

        $this->post($reminder->unsubscribePostUrl())->assertOk()->assertSee('You’re unsubscribed');

        $this->assertTrue(Suppression::blocks('reader@example.com'));
        $this->assertSame('unsubscribed', $this->basket($other)->skip_reason);
        $this->assertSame(0, Suppression::query()->where('email_hash', 'reader@example.com')->count());
    }

    #[Test]
    public function mail_apps_one_click_unsubscribe_works(): void
    {
        $reminder = $this->remind($this->cart());

        $this->post($reminder->unsubscribePostUrl(), ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertContent('');

        $this->assertTrue(Suppression::blocks('reader@example.com'));
    }

    #[Test]
    public function every_reminder_carries_the_one_click_headers(): void
    {
        $reminder = $this->remind($this->cart());

        Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($reminder) {
            $headers = $mail->headers()->text;

            return $headers['List-Unsubscribe'] === '<'.$reminder->unsubscribePostUrl().'>'
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
    }

    #[Test]
    public function the_link_must_be_signed(): void
    {
        $reminder = $this->remind($this->cart());

        $this->get('/!/abandoned-cart/unsubscribe/'.$reminder->id)->assertForbidden();
        $this->post('/!/abandoned-cart/unsubscribe/'.$reminder->id)->assertForbidden();
    }
}
