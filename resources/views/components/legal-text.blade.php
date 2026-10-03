{{--
    Security note: $content is HTML delivered by the trusted eRecht24 API and
    stored locally by this package. It is intentionally output unescaped and
    NOT sanitized. Do not pass user-supplied input into this view.

    Variables: $content (string), $type (LegalTextType), $lang (string).
--}}
<div {{ $attributes->merge(['lang' => $lang]) }}>
    {!! $content !!}
</div>
