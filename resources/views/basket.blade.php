{{--
    The page the link in a reminder opens: what's in the basket now, and a
    button that brings it back (a link alone can't: mail apps and virus
    checkers open links by themselves). Publish to edit:
    php artisan vendor:publish --tag=abandoned-cart-views
--}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} – {{ $siteName }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f4f6; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1f2937; }
        main { box-sizing: border-box; width: calc(100% - 32px); max-width: 520px; margin: 32px 16px; padding: 32px 28px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; }
        h1 { margin: 0 0 12px; font-size: 22px; line-height: 1.3; color: #111827; }
        p { margin: 0 0 20px; font-size: 16px; line-height: 1.5; overflow-wrap: anywhere; }
        ul { list-style: none; margin: 0 0 20px; padding: 0; border: 1px solid #e5e7eb; border-radius: 8px; }
        li { display: flex; justify-content: space-between; gap: 16px; padding: 12px 16px; }
        li + li { border-top: 1px solid #e5e7eb; }
        .name { font-weight: bold; color: #111827; }
        .meta { display: block; font-size: 14px; color: #4b5563; }
        .price { white-space: nowrap; text-align: right; }
        .was { display: block; font-size: 13px; color: #6b7280; text-decoration: line-through; }
        .gone { color: #6b7280; }
        .gone .meta { color: #b45309; }
        .code { margin: 0 0 20px; padding: 12px 16px; background: #f9fafb; border: 1px dashed #9ca3af; border-radius: 8px; font-size: 14px; }
        .code strong { display: block; font-size: 18px; letter-spacing: 1px; font-family: Menlo, Consolas, monospace; color: #111827; }
        button { padding: 12px 18px; border: 0; border-radius: 6px; background: {{ $colour }}; color: {{ $onColour }}; font: inherit; font-weight: bold; cursor: pointer; }
        button:focus-visible, a:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }
        a { color: #374151; }
        .note { font-size: 14px; color: #4b5563; }
        .back { margin: 0; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    <p>{{ $intro }}</p>

    <ul>
        @foreach($lines as $line)
            <li class="{{ $line['available'] ? '' : 'gone' }}">
                <span>
                    <span class="name">{{ $line['title'] }}</span>
                    <span class="meta">
                        @if($line['variant']){{ $line['variant'] }} · @endif{{ __('abandoned-cart::messages.email.quantity', ['count' => $line['quantity']]) }}
                        @unless($line['available']) · {{ __('abandoned-cart::messages.basket.unavailable') }}@endunless
                    </span>
                </span>
                <span class="price">
                    {{ $line['price'] }}
                    @if($line['was'])<span class="was">{{ $line['was'] }}</span>@endif
                </span>
            </li>
        @endforeach
    </ul>

    @if($code)
        <p class="code"><strong>{{ $code }}</strong>{{ __('abandoned-cart::messages.basket.code') }}</p>
    @endif

    @if($hasUnavailable)
        <p class="note">{{ __('abandoned-cart::messages.basket.removed_note') }}</p>
    @endif

    @if($test)
        <p class="note">{{ __('abandoned-cart::messages.basket.test') }}</p>
    @else
        <form method="POST" action="{{ $action }}">
            <p><button type="submit">{{ $button }}</button></p>
        </form>
    @endif

    <p class="back"><a href="{{ $siteUrl }}">{{ __('abandoned-cart::messages.page.back', ['site' => $siteName]) }}</a></p>
</main>
</body>
</html>
