@include('erecht24::components.legal-text', [
    'content' => $content,
    'type' => $type,
    'lang' => $lang,
    'seal' => $seal ?? null,
    'attributes' => $attributes,
])
