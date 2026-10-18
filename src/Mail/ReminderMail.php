<?php

namespace NineteenNinetyFour\AbandonedCart\Mail;

use DuncanMcClean\Cargo\Facades\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use NineteenNinetyFour\AbandonedCart\Cargo\Carts;
use NineteenNinetyFour\AbandonedCart\Discounts;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Step;
use Throwable;

/**
 * A reminder: what's in the basket, any discount code, a link that brings
 * the basket back, and a one-click unsubscribe (RFC 8058).
 */
class ReminderMail extends Mailable
{
    use Queueable;

    public function __construct(public Reminder $reminder, public Basket $basket, public Step $step) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->wording('subject'),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->reminder->unsubscribePostUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $site = $this->basket->siteObject();
        $carts = app(Carts::class);
        $logo = Settings::logo();

        return new Content(
            html: config('abandoned-cart.views.reminder', 'abandoned-cart::emails.reminder'),
            text: config('abandoned-cart.views.reminder_text', 'abandoned-cart::emails.reminder-text'),
            with: [
                'reminder' => $this->reminder,
                'basket' => $this->basket,
                'step' => $this->step->number,
                'heading' => $this->wording('heading'),
                'intro' => $this->wording('intro'),
                'discountText' => $this->reminder->discount_code ? $this->wording('discount_text', shared: true) : null,
                'button' => $this->wording('button', shared: true),
                'footer' => $this->wording('footer', shared: true),
                'items' => $this->items(),
                'total' => $carts->format($this->basket->total, $site),
                'code' => $this->reminder->discount_code,
                'restoreUrl' => $this->reminder->restoreUrl(),
                'unsubscribeUrl' => $this->reminder->unsubscribeUrl(),
                'locale' => str_replace('_', '-', $site->locale()),
                'siteName' => $site->name(),
                'siteUrl' => $site->absoluteUrl(),
                'colour' => Settings::colour(),
                'onColour' => Settings::onColour(),
                'logo' => $logo?->absoluteUrl(),
            ],
        );
    }

    /**
     * The basket's lines, with each product's entry for views that want
     * more of it (an image, say: $item['entry']?->value('image')).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function items(): array
    {
        $site = $this->basket->siteObject();
        $carts = app(Carts::class);

        return collect($this->basket->items ?? [])->map(function (array $item) use ($site, $carts) {
            try {
                $entry = Product::find($item['product'] ?? '');
            } catch (Throwable) {
                $entry = null;
            }

            return [
                'title' => $item['title'] ?? '',
                'variant' => $item['variant_name'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'total' => $carts->format((int) ($item['total'] ?? 0), $site),
                'url' => $entry?->absoluteUrl(),
                'entry' => $entry,
            ];
        })->all();
    }

    /**
     * Wording from the settings: the reminder's own (reminder_2_subject),
     * or the one shared by all three (button).
     */
    protected function wording(string $key, bool $shared = false): string
    {
        $locale = $this->locale ?? $this->basket->locale();

        return Settings::text($shared ? $key : "reminder_{$this->step->number}_{$key}", $this->replacements(), $locale);
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(): array
    {
        $name = trim((string) $this->basket->name);

        return [
            'name' => $name !== '' ? strtok($name, ' ') : (string) __('abandoned-cart::messages.email.there', [], $this->basket->locale()),
            'site' => $this->basket->siteObject()->name(),
            'code' => (string) $this->reminder->discount_code,
            'discount' => (string) app(Discounts::class)->describe($this->step, $this->basket),
            'expires' => $this->reminder->expires_at?->locale($this->basket->locale())->isoFormat('LL') ?? '',
        ];
    }
}
