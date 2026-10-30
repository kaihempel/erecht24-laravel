{{--
    Security note: $content is HTML delivered by the trusted eRecht24 API and
    stored locally by this package. It is intentionally output unescaped and
    NOT sanitized. Do not pass user-supplied input into this view.

    Variables: $content (string), $type (LegalTextType), $lang (string),
    $seal (?LegalTextSeal, optional).
--}}
<div {{ $attributes->merge(['lang' => $lang]) }}>
    {!! $content !!}
@if (! empty($seal))
    <div class="erecht24-seal" align="center">
        <img src="{{ $seal->url }}" alt="{{ $seal->alt }}" width="{{ $seal->displayWidth }}" height="{{ (int) round($seal->displayWidth * $seal->height / $seal->width) }}" loading="lazy">
    </div>
@endif
</div>
