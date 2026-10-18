<?php

namespace NineteenNinetyFour\AbandonedCart\Tags;

use DuncanMcClean\Cargo\Facades\Cart;
use NineteenNinetyFour\AbandonedCart\Consent;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Tracker;
use Statamic\Tags\Tags;
use Throwable;

/**
 * {{ abandoned_cart:consent }}: the box that asks (or lets people say no
 * to) reminder emails. {{ abandoned_cart:restored }}: the message after
 * a basket comes back.
 */
class AbandonedCartTags extends Tags
{
    protected static $handle = 'abandoned_cart';

    /** Boxes on the page so far, for their ids. */
    protected static int $boxes = 0;

    /**
     * {{ abandoned_cart:consent }}: a ready-made box that saves itself when
     * it's ticked. As a pair, the parts for your own markup:
     * {{ abandoned_cart:consent }}...{{ /abandoned_cart:consent }}.
     */
    public function consent(): string|array
    {
        $data = $this->consentData();

        if (! $this->isPair) {
            return $this->consentHtml($data);
        }

        return $data;
    }

    /**
     * {{ abandoned_cart:restored }}: "Your basket's back", once, after the
     * button in a reminder. As a pair it also has {{ removed }}: anything
     * taken out because it's sold out or gone.
     */
    public function restored(): string|array
    {
        $message = session('abandoned_cart_restored');

        // Nothing to say: a pair given an empty array would still show its
        // contents once, so it's given nothing at all.
        if (! is_string($message) || $message === '') {
            return '';
        }

        if (! $this->isPair) {
            return e($message);
        }

        return [
            'message' => e($message),
            'removed' => collect((array) session('abandoned_cart_removed', []))->map(fn ($name) => ['name' => e((string) $name)])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function consentData(): array
    {
        $mode = Consent::mode();
        $optIn = $mode === 'opt_in';
        $given = $this->currentConsent();

        return [
            'mode' => $mode,
            'opt_in' => $optIn,
            'soft_opt_in' => ! $optIn,
            'text' => e(Consent::text()),
            // Opt-in: ticked means yes. Soft opt-in: ticked means no.
            'checked' => $optIn ? $given === true : $given === false,
            'name' => $optIn ? 'abandoned_cart_consent' : 'abandoned_cart_opt_out',
            'url' => route('statamic.abandoned-cart.consent'),
            'csrf_token' => csrf_token(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function consentHtml(array $data): string
    {
        $class = e($this->params->get('class', 'abandoned-cart-consent'));
        $id = 'abandoned-cart-consent-'.++self::$boxes;

        $input = sprintf(
            '<input type="checkbox" id="%s" name="%s" value="1" data-abandoned-cart-consent="%s" data-mode="%s" data-token="%s"%s>',
            $id,
            e($data['name']),
            e($data['url']),
            e($data['mode']),
            e($data['csrf_token']),
            $data['checked'] ? ' checked' : '',
        );

        // Saves the choice as soon as the box changes, so it counts even if
        // the checkout form is never sent.
        $script = <<<JS
<script>
(function () {
    var box = document.getElementById('{$id}');
    if (!box) return;
    box.addEventListener('change', function () {
        var given = box.dataset.mode === 'opt_in' ? box.checked : !box.checked;
        var body = new FormData();
        body.append('consent', given ? '1' : '0');
        body.append('_token', box.dataset.token);
        fetch(box.dataset.abandonedCartConsent, { method: 'POST', body: body, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
    });
})();
</script>
JS;

        return sprintf('<label class="%s" for="%s">%s <span>%s</span></label>', $class, $id, $input, $data['text']).$script;
    }

    /**
     * What the visitor said for their cart: true, false or null (nothing).
     */
    protected function currentConsent(): ?bool
    {
        try {
            if (Cart::hasCurrentCart() && ($id = Cart::current()->id())) {
                $consent = Basket::query()->where('cart_id', (string) $id)->value('consent');

                if ($consent !== null) {
                    return $consent === 'given';
                }
            }
        } catch (Throwable) {
        }

        $pending = session(Tracker::SESSION_KEY);

        return is_array($pending) && isset($pending['given']) ? (bool) $pending['given'] : null;
    }
}
