# Consent

Reminder emails are usually marketing: they try to win back a sale, and some carry a discount. So they need the shopper's say-so. Abandoned Cart asks with a box at the checkout, keeps what each shopper said, and emails only the baskets it's allowed to.

This isn't legal advice. Check what fits your shop, and what your privacy notice says.

## Two modes

Choose one in Tools → Addons → Abandoned Cart → Settings → Who's emailed, or with `consent` in the [config](configuration.md).

**Opt-in** (`opt_in`, the default): a box to tick, unticked. Only baskets where it was ticked are emailed. The default wording: "Email me a reminder if I leave items in my basket." This works everywhere, including the EU.

**Soft opt-in** (`soft_opt_in`): a notice, with a box to tick to say no. Baskets are emailed unless it was ticked. The default wording: "We may email you a reminder if you leave items in your basket. Tick if you'd rather we didn't."

With soft opt-in, a basket with no answer at all is emailed. That includes a basket whose shopper never saw the notice, so put the box everywhere the checkout asks for an email address.

Each basket keeps what the shopper said (agreed, or said no), the wording next to the box at the time, and when. You see it on the basket's page in Store → Abandoned Carts.

## UK soft opt-in, plainly

In the UK, the Privacy and Electronic Communications Regulations (PECR) let a business email marketing to someone without ticking a box first, if all of these are true:

1. **You got their email address while they were buying**, or in "negotiations for a sale": they were in the middle of buying something from you.
2. **You're emailing about your own similar products.** A reminder about the basket they left is that.
3. **They were given a simple way to say no when you got their address.** That's the box, next to the email field.
4. **Every email gives them a simple way to say no again.** Every reminder has an unsubscribe link, and mail apps' one-click unsubscribe.

Whether a basket left at the checkout counts as "negotiations for a sale" is a judgement call. Many UK shops take the view that it does, as the shopper gave their address to buy. If you're not sure, choose opt-in.

Soft opt-in is a UK rule. Many EU countries don't have it, or read it more narrowly. If you sell to the EU, choose opt-in.

## The box

Put `{{ abandoned_cart:consent }}` where the checkout asks for an email address. It shows the box and the wording, and saves the answer as soon as the box changes, so it counts even if the shopper never sends the form. If there's no basket yet, the answer waits for one (in the session).

For Cargo's prebuilt checkout, signed-in customers, and your own markup, see [Templates](templates.md). The tag's variables are in [Tags](tags.md).

## Recording consent from your own checkout

If your checkout is your own, you can record the answer with the rest of the form, or from your own code, instead of with the ready-made box. The ready-made box and a field can be used together: whichever changed last counts.

### An order blueprint field

Add a Toggle field with the handle `abandoned_cart_consent` to Cargo's order blueprint, which carts share. Cargo's `{{ cart:update }}` form then saves it on the basket, and the addon reads it: true when they agreed, false when they said no.

```yaml
-
  handle: abandoned_cart_consent
  field:
    type: toggle
    display: 'Reminder emails'
```

Then add it to your checkout's customer step. The hidden field sends `0` when the box isn't ticked, so unticking it counts:

```antlers
{{ cart:update }}
    <input type="email" name="email" value="{{ customer:email }}" aria-label="Email address" required>

    <input type="hidden" name="abandoned_cart_consent" value="0">
    {{ abandoned_cart:consent }}
        <label>
            <input type="checkbox" name="abandoned_cart_consent" value="1" {{ if checked }}checked{{ /if }}>
            {{ text }}
        </label>
    {{ /abandoned_cart:consent }}

    <button type="submit">Continue</button>
{{ /cart:update }}
```

<details>
<summary>Blade</summary>

```blade
<s:cart:update>
    <input type="email" name="email" value="{{ $customer['email'] ?? '' }}" aria-label="Email address" required>

    <input type="hidden" name="abandoned_cart_consent" value="0">
    <s:abandoned_cart:consent>
        <label>
            <input type="checkbox" name="abandoned_cart_consent" value="1" @checked($checked)>
            {!! $text !!}
        </label>
    </s:abandoned_cart:consent>

    <button type="submit">Continue</button>
</s:cart:update>
```

</details>

`{{ abandoned_cart:consent }}` as a pair gives the wording from the settings (`{{ text }}`), and whether the box was ticked before (`{{ checked }}`). The wording in the settings is what's saved with the answer.

That example is for opt-in. For soft opt-in, swap the values: the hidden field `1`, and the box (the one to say no) `0`.

### The PHP API

From your own code, once the shopper has a basket:

```php
use NineteenNinetyFour\AbandonedCart\Facades\AbandonedCart;

// The visitor's own basket, with the wording in the settings.
AbandonedCart::consent(true);

// Or a basket of your choosing, with the wording they saw.
AbandonedCart::consent(false, $cart, 'Email me a reminder if I leave items in my basket.');
```

`$cart` is a Cargo cart. It returns the addon's record of the basket, or null when there's no basket.

## Unsubscribing

Every reminder has an unsubscribe link, and the headers mail apps use for their own unsubscribe button (see [Emails](emails.md#unsubscribing)). Unsubscribing stops every reminder to that address, for every basket: those waiting now are marked "Unsubscribed", and later ones aren't emailed either.

The address goes on a suppression list, kept as a one-way hash (SHA-256), not the address itself. It stays there when everything else about the address is deleted (see [Privacy](privacy.md)), so it still isn't emailed.

## Ticking the box again

Someone who unsubscribed and later ticks the opt-in box is asking again: that basket is emailed, as is any other they tick the box for. A basket they agreed to before unsubscribing isn't, unless they tick the box for it again.

With soft opt-in there's no box to ask again with: not saying no isn't asking. To take an address off the list (they asked you to), use `AbandonedCart::lift('reader@example.com')` (see the [PHP API](php-api.md)).
