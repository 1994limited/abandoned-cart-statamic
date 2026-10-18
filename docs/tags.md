# Tags

See [Templates](templates.md) for them in use, in Antlers and Blade.

## `{{ abandoned_cart:consent }}`

The consent box, for where the checkout asks for an email address. On its own, a ready-made box: a `<label>` with the checkbox and the wording, and a small script that saves the answer as soon as the box changes. The wording is escaped.

| Parameter | |
| --- | --- |
| `class` | The label's class. Defaults to `abandoned-cart-consent`. |

As a pair, the parts for your own markup. The pair doesn't save anything by itself: post to `url` (see the [HTTP API](http-api.md)), or use a field on the order blueprint (see [Consent](consent.md#an-order-blueprint-field)). Its variables are escaped.

| Variable | |
| --- | --- |
| `mode` | `opt_in` or `soft_opt_in`, from the settings. |
| `opt_in`, `soft_opt_in` | True in that mode. |
| `text` | The wording next to the box, from the settings: "Email me a reminder…" (opt-in), or "We may email you a reminder… Tick if you'd rather we didn't." (soft opt-in). |
| `checked` | Whether the box should be ticked: with opt-in, they agreed; with soft opt-in, they said no. False when they haven't answered. |
| `name` | A name for the checkbox: `abandoned_cart_consent` (opt-in) or `abandoned_cart_opt_out` (soft opt-in). |
| `url` | The address to post the answer to: `/!/abandoned-cart/consent`. |
| `csrf_token` | The CSRF token to post with it. |

The answer is for the visitor's basket. Before there's one, it waits in the session, and the box shows it.

## `{{ abandoned_cart:restored }}`

The message after the button on a reminder's page brought the basket back: "Your basket's back. Pick up where you left off.", or "Your basket's back. We took out what's no longer available: …". Empty on any other page. Shows once: it's in the session for the next page only. Escaped.

As a pair:

| Variable | |
| --- | --- |
| `message` | The message, escaped. |
| `removed` | What was taken out because it's sold out or no longer sold: `name` for each. |

On any other page, the pair shows nothing.
