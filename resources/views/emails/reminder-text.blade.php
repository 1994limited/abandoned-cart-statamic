{{ $heading }}

{{ $intro }}

@foreach($items as $item)
{{ $item['title'] }}@if($item['variant']) ({{ $item['variant'] }})@endif × {{ $item['quantity'] }} – {{ $item['total'] }}
@endforeach
{{ __('abandoned-cart::messages.email.total') }}: {{ $total }}

@if($discountText)
{{ $code }}: {{ $discountText }}

@endif
{{ $button }}: {!! $restoreUrl !!}

{{ $footer }}

{{ __('abandoned-cart::messages.email.unsubscribe') }}: {!! $unsubscribeUrl !!}

{{ $siteName }}
