<section class="ui-metrics">
    @foreach($metrics as $label => $value)
        @if($label !== 'Low Stock Items')
            @php
                [$tone, $icon] = match ($label) {
                    "Today's Sales", 'Sales Today' => ['success', 'fa-peso-sign'],
                    'Completed Orders', 'Deliveries Completed Today', 'Available Products' => ['success', 'fa-circle-check'],
                    'Pending Orders', 'Outstanding Balance', 'Low Stock Products' => ['warning', 'fa-clock'],
                    'Out of Stock Products' => ['danger', 'fa-circle-exclamation'],
                    'Saved Drafts' => ['neutral', 'fa-file-lines'],
                    'Ongoing Delivery' => ['info', 'fa-truck'],
                    'Total Products', 'Items Sold Today' => ['info', 'fa-box'],
                    default => ['info', 'fa-receipt'],
                };
            @endphp
            <x-metric :label="$label" :value="$value" :tone="$tone" :icon="$icon" />
        @endif
    @endforeach
</section>
