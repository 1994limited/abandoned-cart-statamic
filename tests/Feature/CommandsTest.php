<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Discount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function send_emails_whats_due(): void
    {
        $this->cart();
        $this->travel(2)->hours();

        $this->artisan('abandoned-cart:send')->expectsOutputToContain('Sent 1 reminder.')->assertSuccessful();

        Mail::assertSent(ReminderMail::class);
    }

    #[Test]
    public function the_scheduler_runs_send_and_prune(): void
    {
        $events = collect(app(Schedule::class)->events())->map->command->implode(' ');

        $this->assertStringContainsString('abandoned-cart:send', $events);
        $this->assertStringContainsString('abandoned-cart:prune', $events);
    }

    #[Test]
    public function prune_anonymises_then_deletes_old_baskets(): void
    {
        $reminder = $this->remind($this->cart());
        $basket = $reminder->basket;

        $this->travel(91)->days();
        $this->artisan('abandoned-cart:prune')->assertSuccessful();

        $basket->refresh();
        $this->assertNull($basket->email);
        $this->assertNull($basket->name);
        $this->assertNotNull($basket->anonymised_at);
        $this->assertSame(999, $basket->total);
        $this->assertNull($reminder->refresh()->email);

        $this->travel(275)->days();
        $this->artisan('abandoned-cart:prune')->assertSuccessful();

        $this->assertSame(0, Basket::query()->count());
        $this->assertSame(0, Reminder::query()->count());
    }

    #[Test]
    public function forget_deletes_everything_about_an_address(): void
    {
        config(['abandoned-cart.reminders.0.discount' => ['type' => 'percentage_off', 'value' => 10]]);
        $reminder = $this->remind($this->cart(email: 'Reader@Example.com'));
        $this->cart(email: 'someone@example.com');

        $this->artisan('abandoned-cart:forget', ['email' => 'reader@example.com'])->expectsOutputToContain('Deleted 1 basket.');

        $this->assertSame(['someone@example.com'], Basket::query()->pluck('email')->all());
        $this->assertSame(0, Reminder::query()->count());
        $this->assertNull(Discount::find($reminder->discount_handle));
    }
}
