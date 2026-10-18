<s:abandoned_cart:consent>
    <label class="consent">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)
            data-consent-url="{{ $url }}" data-consent-mode="{{ $mode }}" data-consent-token="{{ $csrf_token }}">
        {!! $text !!}
    </label>
</s:abandoned_cart:consent>
