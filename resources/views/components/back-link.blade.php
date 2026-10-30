@props(['fallback', 'label' => 'Back', 'preserveContext' => true])
@php
    // Only retain query/filter context from the known parent, never arbitrary history.
    $previous = url()->previous();
    $target = $fallback;
    if ($preserveContext && parse_url($previous, PHP_URL_SCHEME) === parse_url($fallback, PHP_URL_SCHEME)
        && parse_url($previous, PHP_URL_HOST) === parse_url($fallback, PHP_URL_HOST)
        && parse_url($previous, PHP_URL_PORT) === parse_url($fallback, PHP_URL_PORT)
        && parse_url($previous, PHP_URL_PATH) === parse_url($fallback, PHP_URL_PATH)
        && $previous !== request()->fullUrl()) {
        $target = $previous;
    }
@endphp
<a {{ $attributes->class(['ui-back', 'no-print']) }} href="{{ $target }}"><span aria-hidden="true">←</span> {{ $label }}</a>
