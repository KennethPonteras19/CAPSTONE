<section class="workspace-kpis">
    @foreach($metrics as $label => $value)
        @if($label !== 'Low Stock Items')
            <div class="workspace-kpi">
                <span>{{ $label }}</span>
                <strong>{{ $value }}</strong>
            </div>
        @endif
    @endforeach
</section>
