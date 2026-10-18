{{--
    A reminder email. Publish to edit: php artisan vendor:publish --tag=abandoned-cart-views
    $step is 1, 2 or 3. Each of $items has its product's entry as $item['entry'],
    so its other fields (an image, say) are $item['entry']?->value('handle').
--}}
@extends('abandoned-cart::emails.layout')

@section('body')
    <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3; color:#111827;">{{ $heading }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.5;">{{ $intro }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; border:1px solid #e5e7eb; border-radius:8px;">
        @foreach($items as $item)
            <tr>
                <td style="padding:12px 16px; {{ $loop->first ? '' : 'border-top:1px solid #e5e7eb;' }}">
                    <p style="margin:0; font-size:16px; font-weight:bold; color:#111827;">{{ $item['title'] }}</p>
                    <p style="margin:2px 0 0; font-size:14px; color:#4b5563;">@if($item['variant']){{ $item['variant'] }} · @endif{{ __('abandoned-cart::messages.email.quantity', ['count' => $item['quantity']]) }}</p>
                </td>
                <td align="right" style="padding:12px 16px; font-size:16px; white-space:nowrap; color:#111827; {{ $loop->first ? '' : 'border-top:1px solid #e5e7eb;' }}">{{ $item['total'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td style="padding:12px 16px; border-top:1px solid #e5e7eb; font-size:16px; font-weight:bold;">{{ __('abandoned-cart::messages.email.total') }}</td>
            <td align="right" style="padding:12px 16px; border-top:1px solid #e5e7eb; font-size:16px; font-weight:bold; white-space:nowrap;">{{ $total }}</td>
        </tr>
    </table>

    @if($discountText)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background:#f9fafb; border:1px dashed #9ca3af; border-radius:8px;">
            <tr>
                <td style="padding:14px 16px;">
                    <p style="margin:0 0 6px; font-size:20px; font-weight:bold; letter-spacing:1px; color:#111827; font-family:Menlo, Consolas, monospace;">{{ $code }}</p>
                    <p style="margin:0; font-size:14px; line-height:1.5; color:#4b5563;">{{ $discountText }}</p>
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="border-radius:6px; background:{{ $colour }};">
                <a href="{{ $restoreUrl }}" style="display:inline-block; padding:12px 18px; color:{{ $onColour }}; font-size:15px; font-weight:bold; text-decoration:none;">{{ $button }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0; font-size:13px; line-height:1.5; color:#6b7280;">{{ $footer }}</p>
@endsection

@section('after')
    <p style="margin:16px 0 0; font-size:12px; color:#9ca3af;"><a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">{{ __('abandoned-cart::messages.email.unsubscribe') }}</a></p>
@endsection
