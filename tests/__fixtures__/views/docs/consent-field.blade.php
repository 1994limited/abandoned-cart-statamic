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
