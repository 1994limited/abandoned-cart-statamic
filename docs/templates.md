# Templates

Abandoned Cart needs two things in your templates: the consent box where the checkout asks for an email address, and, if you like, the "Your basket's back" message where the link in a reminder lands. Everything else (the emails, and the page the link opens) comes with the addon.

> **Escaping.** `{{ abandoned_cart:consent }}` and `{{ abandoned_cart:restored }}` escape what they print. Used as a pair, their variables come as they are, so escape them yourself: `{{ message }}` in Antlers, `{!! $message !!}` in Blade.

## Antlers or Blade

Every example below is Antlers, with the same thing in Blade under **Blade**: click it to open. Inside a tag pair, the tag's variables are Blade variables (`$text`, `$checked`, `$message`…).

## The box in Cargo's prebuilt checkout

If you use Cargo's prebuilt checkout, publish its views so you can edit them:

```bash
php please vendor:publish --tag=cargo-prebuilt-checkout
```

Then open `resources/views/checkout/steps/_customer.antlers.html`, and put the tag under the email field:

```antlers
{{ partial:checkout/components/input
    name="email"
    label="Email address"
    type="email"
    placeholder="john.doe@example.com"
    xBindValue="cart.customer?.email"
    autocomplete="email"
    required="true"
}}

{{ abandoned_cart:consent }}
```

<details>
<summary>Blade</summary>

In a Blade checkout of your own, next to the email field:

```blade
<label for="email">Email address</label>
<input type="email" id="email" name="email" autocomplete="email" required>

<s:abandoned_cart:consent />
```

</details>

What happens:

1. The shopper sees the box and the wording from the [settings](configuration.md). With opt-in it's unticked; with soft opt-in it's the box to say no.
2. When they tick or untick it, it's saved for their basket straight away (a small script posts to `/!/abandoned-cart/consent`). They don't need to send the form.
3. When they come back to the page, the box shows what they chose.

The box is a `<label class="abandoned-cart-consent">` with the checkbox and a `<span>` of wording inside. Style it with that class, or give your own: `{{ abandoned_cart:consent class="consent" }}`.

## Signed-in customers

Cargo's prebuilt customer step shows only to guests: a signed-in customer's name and email come from their account, so they skip it. Put the box on your basket page for them:

```antlers
{{ if logged_in }}
    {{ abandoned_cart:consent }}
{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@auth
    <s:abandoned_cart:consent />
@endauth
```

</details>

If they tick it before there's a basket, the answer waits for one.

## Your own markup

As a pair, the tag gives you the parts, and the markup is yours:

```antlers
{{ abandoned_cart:consent }}
    <label class="consent">
        <input type="checkbox" name="{{ name }}" value="1" {{ if checked }}checked{{ /if }}
            data-consent-url="{{ url }}" data-consent-mode="{{ mode }}" data-consent-token="{{ csrf_token }}">
        {{ text }}
    </label>
{{ /abandoned_cart:consent }}
```

<details>
<summary>Blade</summary>

```blade
<s:abandoned_cart:consent>
    <label class="consent">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)
            data-consent-url="{{ $url }}" data-consent-mode="{{ $mode }}" data-consent-token="{{ $csrf_token }}">
        {!! $text !!}
    </label>
</s:abandoned_cart:consent>
```

</details>

The pair doesn't save anything by itself. Post the answer when the box changes:

```js
document.querySelectorAll('[data-consent-url]').forEach((box) => {
    box.addEventListener('change', () => {
        const body = new FormData();
        // Opt-in: ticked means yes. Soft opt-in: ticked means no.
        body.append('consent', (box.dataset.consentMode === 'opt_in') === box.checked ? '1' : '0');
        body.append('_token', box.dataset.consentToken);
        fetch(box.dataset.consentUrl, { method: 'POST', body, headers: { Accept: 'application/json' } });
    });
});
```

Or save it with your checkout form, through a field on Cargo's order blueprint (see [Consent](consent.md#an-order-blueprint-field)).

## "Your basket's back"

The button on the page a reminder's link opens brings the basket back, then goes to your checkout (`redirect` in the [config](configuration.md)), with a message: "Your basket's back. Pick up where you left off." If anything had sold out, it says what was taken out. Show it on your checkout and basket pages:

```antlers
{{ if {abandoned_cart:restored} }}
    <p class="flash">{{ abandoned_cart:restored }}</p>
{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@if ($restored = Statamic::tag('abandoned_cart:restored')->fetch())
    <p class="flash">{!! $restored !!}</p>
@endif
```

</details>

It shows once, on the page after the button.

As a pair, `{{ removed }}` lists what was taken out (`{{ name }}` for each), so you can style the message differently. The pair shows nothing when there's no message:

```antlers
{{ abandoned_cart:restored }}
    <div class="flash{{ if removed }} flash--warning{{ /if }}">{{ message }}</div>
{{ /abandoned_cart:restored }}
```

<details>
<summary>Blade</summary>

```blade
<s:abandoned_cart:restored>
    <div class="flash{{ $removed ? ' flash--warning' : '' }}">{!! $message !!}</div>
</s:abandoned_cart:restored>
```

</details>

## Static caching

Checkout and basket pages are personal, so don't cache them statically. `{{ abandoned_cart:consent }}` shows the visitor's own answer, and posts with a CSRF token from the page; `{{ abandoned_cart:restored }}` reads the session. If one of these tags is on a page that is cached, wrap it in `{{ nocache }}`.
