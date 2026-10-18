@if ($restored = Statamic::tag('abandoned_cart:restored')->fetch())
    <p class="flash">{!! $restored !!}</p>
@endif
