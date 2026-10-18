<?php

namespace NineteenNinetyFour\AbandonedCart\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\AbandonedCart\Mail\ReminderMail;
use NineteenNinetyFour\AbandonedCart\Sender;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Tests\TestCase;
use NineteenNinetyFour\AbandonedCart\Widgets\AbandonedCartWidget;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;

class ControlPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function the_list_needs_permission(): void
    {
        $this->actingAs($this->user('nobody@example.com', ['access cp']))
            ->getJson(cp_route('abandoned-cart.index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_list_shows_baskets_and_numbers(): void
    {
        $this->remind($this->cart());
        $this->cart(email: 'shy@example.com', consent: null);
        $this->travel(2)->hours();
        $this->send();

        $this->actingAs($this->user('viewer@example.com', ['view abandoned carts']))
            ->get(cp_route('abandoned-cart.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('abandoned-cart::Index')
                ->has('baskets', 2)
                ->where('stats.abandoned', 2)
                ->where('stats.emailed', 1)
                ->where('stats.reasons.0.label', 'No consent')
                ->where('canExport', false));
    }

    #[Test]
    public function the_list_filters_and_searches(): void
    {
        $this->remind($this->cart(email: 'emailed@example.com'));
        $this->cart(email: 'shy@example.com', consent: null);
        $this->travel(2)->hours();
        $this->send();
        $user = $this->user('viewer@example.com', ['view abandoned carts']);

        $this->actingAs($user)->get(cp_route('abandoned-cart.index', ['show' => 'not_emailed']))
            ->assertInertia(fn ($page) => $page->has('baskets', 1)->where('baskets.0.email', 'shy@example.com'));

        $this->actingAs($user)->get(cp_route('abandoned-cart.index', ['search' => 'EMAILED@']))
            ->assertInertia(fn ($page) => $page->has('baskets', 1)->where('baskets.0.email', 'emailed@example.com'));
    }

    #[Test]
    public function the_scheduler_warning_shows_until_send_runs(): void
    {
        Cache::forget(Sender::LAST_RUN);
        $user = $this->user('viewer@example.com', ['view abandoned carts']);

        $this->actingAs($user)->get(cp_route('abandoned-cart.index'))
            ->assertInertia(fn ($page) => $page->where('warning', fn ($warning) => str_contains($warning, 'schedule:run')));

        $this->send();

        $this->actingAs($user)->get(cp_route('abandoned-cart.index'))
            ->assertInertia(fn ($page) => $page->where('warning', null));

        $this->travel(20)->minutes();

        $this->actingAs($user)->get(cp_route('abandoned-cart.index'))
            ->assertInertia(fn ($page) => $page->where('warning', fn ($warning) => str_contains($warning, 'last ran')));
    }

    #[Test]
    public function a_basket_shows_its_reminders_and_consent(): void
    {
        $cart = $this->cart();
        $this->remind($cart);

        $this->actingAs($this->user('viewer@example.com', ['view abandoned carts']))
            ->get(cp_route('abandoned-cart.show', $this->basket($cart)->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('abandoned-cart::Show')
                ->where('basket.email', 'reader@example.com')
                ->where('basket.consent_label', 'Agreed')
                ->where('basket.consent_text', 'Email me a reminder if I leave items in my basket.')
                ->has('reminders', 1)
                ->where('canManage', false));
    }

    #[Test]
    public function a_test_reminder_goes_to_whoever_sent_it_and_changes_nothing(): void
    {
        $cart = $this->cart(consent: null);
        $basket = $this->basket($cart);

        $this->actingAs($this->user('manager@example.com', ['view abandoned carts', 'manage abandoned carts']))
            ->postJson(cp_route('abandoned-cart.test', $basket->id), ['step' => 2])
            ->assertOk()
            ->assertJson(['message' => 'Sent to manager@example.com.']);

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('manager@example.com') && $mail->step->number === 2 && $mail->reminder->test);
        $this->assertSame(0, $basket->refresh()->reminders_sent);
    }

    #[Test]
    public function managing_needs_permission(): void
    {
        $basket = $this->basket($this->cart());
        $viewer = $this->user('viewer@example.com', ['view abandoned carts']);

        $this->actingAs($viewer)->postJson(cp_route('abandoned-cart.test', $basket->id))->assertForbidden();
        $this->actingAs($viewer)->postJson(cp_route('abandoned-cart.stop', $basket->id))->assertForbidden();
    }

    #[Test]
    public function reminders_can_be_stopped(): void
    {
        $cart = $this->cart();

        $this->actingAs($this->user('manager@example.com', ['view abandoned carts', 'manage abandoned carts']))
            ->postJson(cp_route('abandoned-cart.stop', $this->basket($cart)->id))
            ->assertOk();

        $this->travel(2)->hours();
        $this->send();
        Mail::assertNothingSent();
        $this->assertSame('stopped', $this->basket($cart)->skip_reason);
    }

    #[Test]
    public function the_csv_needs_permission_and_is_safe_for_spreadsheets(): void
    {
        $cart = $this->cart(email: 'reader@example.com');
        $this->basket($cart)->forceFill(['name' => '=HYPERLINK("http://evil")'])->save();
        $this->travel(2)->hours();

        $this->actingAs($this->user('viewer@example.com', ['view abandoned carts']))
            ->getJson(cp_route('abandoned-cart.export'))
            ->assertForbidden();

        $csv = $this->actingAs($this->user('exporter@example.com', ['view abandoned carts', 'export abandoned carts']))
            ->get(cp_route('abandoned-cart.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('reader@example.com', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    #[Test]
    public function the_widget_shows_the_numbers_to_those_allowed(): void
    {
        $this->remind($this->cart());

        $this->actingAs($this->user('viewer@example.com', ['view abandoned carts']));
        $component = (new AbandonedCartWidget)->component()->toArray();
        $this->assertSame('abandoned-cart-widget', $component['name']);
        $this->assertSame(1, $component['props']['stats']['emailed']);

        $this->actingAs($this->user('nobody@example.com', ['access cp']));
        $this->assertNull((new AbandonedCartWidget)->component());
    }

    #[Test]
    public function the_nav_item_and_settings_are_there(): void
    {
        $this->actingAs($this->user('admin@example.com', super: true))
            ->get(cp_route('addons.settings.edit', Addon::get(Settings::ADDON)->slug()))
            ->assertOk();
    }
}
