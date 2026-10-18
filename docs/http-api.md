# HTTP API

The consent box and the links in the emails use these. They're under Statamic's action route, `/!/` by default.

## `POST /!/abandoned-cart/consent`

Records the consent answer for the visitor's basket. `{{ abandoned_cart:consent }}` posts here when its box changes. If there's no basket yet, the answer waits in the session for one.

| Field | |
| --- | --- |
| `consent` | `1` when they agreed (or, with soft opt-in, didn't say no), `0` when they said no. |
| `_token` | The CSRF token. |

```js
const body = new FormData();
body.append('consent', '1');
body.append('_token', csrfToken);

const response = await fetch('/!/abandoned-cart/consent', {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body,
});

const answer = await response.json();
// { success: true, consent: true, saved: true }
// saved is false when there's no basket yet: the answer waits for one.
```

Without `Accept: application/json`, it redirects back. Statuses: `200`; `419` without a valid CSRF token; `429` after 30 posts in a minute from one visitor.

## `GET /!/abandoned-cart/basket/{reminder}`

The link in a reminder. Opens the page with the basket as it is now and the button. Changes nothing about the basket. See [Restoring a basket](restoring.md).

## `POST /!/abandoned-cart/basket/{reminder}`

The button. Makes the basket the visitor's, and redirects to `redirect` in the config (`/checkout` on the basket's site), with the message for `{{ abandoned_cart:restored }}`.

Both are signed links that expire with the reminder: changing them stops them working (`403`), and once expired they show "Link expired" (`410`). A basket Cargo has deleted shows "Basket not found" (`410`). The `POST` needs no CSRF token: the signature is checked instead.

## `GET /!/abandoned-cart/unsubscribe/{reminder}`

The unsubscribe link in a reminder. Opens a page with an **Unsubscribe** button.

## `POST /!/abandoned-cart/unsubscribe/{reminder}`

The button, and mail apps' one-click unsubscribe (`List-Unsubscribe=One-Click` in the body, as in RFC 8058), which gets an empty `200`. Unsubscribes the address the reminder went to.

Both are signed links that never expire: changing them stops them working (`403`). The `POST` needs no CSRF token.
