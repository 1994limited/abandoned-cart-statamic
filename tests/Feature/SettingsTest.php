<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Discount;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Step;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;

class SettingsTest extends TestCase
{
    #[Test]
    public function the_reminders_default_to_the_config(): void
    {
        $steps = Settings::steps();

        $this->assertSame([1, 2], array_map(fn (Step $step) => $step->number, $steps));
        $this->assertSame([1, 24], array_map(fn (Step $step) => $step->delay, $steps));
    }

    #[Test]
    public function the_control_panel_settings_win(): void
    {
        $this->saveSettings([
            'reminder_1_delay' => 3,
            'reminder_2_enabled' => false,
            'reminder_3_enabled' => true,
            'reminder_3_delay' => 48,
            'reminder_3_discount' => 'generate',
            'reminder_3_discount_type' => 'free_shipping',
        ]);

        $steps = Settings::steps();

        $this->assertSame([1, 3], array_map(fn (Step $step) => $step->number, $steps));
        $this->assertSame([3, 48], array_map(fn (Step $step) => $step->delay, $steps));
        $this->assertTrue($steps[1]->hasDiscount());
    }

    #[Test]
    public function the_first_reminder_cannot_be_switched_off_and_two_never_go_at_once(): void
    {
        $this->saveSettings(['reminder_1_enabled' => false, 'reminder_2_delay' => 1]);

        $this->assertSame([1], array_map(fn (Step $step) => $step->number, Settings::steps()));
    }

    #[Test]
    public function consent_mode_can_be_set_in_the_control_panel(): void
    {
        $this->saveSettings(['consent_mode' => 'soft_opt_in']);
        Mail::fake();

        $this->remind($this->cart(consent: null));

        Mail::assertSent(ReminderMail::class);
    }

    #[Test]
    public function the_settings_page_offers_the_shops_discounts(): void
    {
        Discount::make()->handle('come-back')->title('Come back')->type('percentage_off')->set('discount_code', 'comeback15')->set('percentage_off', 15)->save();

        $field = Addon::get(Settings::ADDON)->settingsBlueprint()->field('reminder_1_discount_handle');

        $this->assertSame(['come-back' => 'Come back (COMEBACK15)'], $field->config()['options']);
    }
}
