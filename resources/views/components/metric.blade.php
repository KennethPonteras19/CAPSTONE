@props(['label', 'value', 'tone' => 'info', 'icon' => 'fa-chart-simple', 'hint' => null, 'valueId' => null])
<article {{ $attributes->class(['ui-metric', 'ui-metric--'.$tone]) }}>
    <div class="ui-metric-heading">
        <span class="ui-metric-label">{{ $label }}</span>
        <i class="ui-metric-icon fa-solid {{ $icon }}" aria-hidden="true"></i>
    </div>
    <strong class="ui-metric-value" @if($valueId) id="{{ $valueId }}" @endif>{{ $value }}</strong>
    @if($hint)<p class="ui-metric-hint">{{ $hint }}</p>@endif
</article>
