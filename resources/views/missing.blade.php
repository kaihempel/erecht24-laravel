<div {{ $attributes ?? '' }}>
    <p>This legal text is currently not available.</p>
    @if (config('app.debug'))
        <p>
            Developer hint: no stored text was found. Run <code>php artisan erecht24:sync</code> to fetch it.
        </p>
    @endif
</div>
