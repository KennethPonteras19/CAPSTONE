<div class="module-options">
    <p class="note module-help">Select a role to see its available modules.</p>
    @foreach($moduleCatalog as $roleKey => $modules)
        <fieldset data-module-role="{{ $roleKey }}" hidden disabled>
            <legend>{{ ucfirst($roleKey) }} modules</legend>
            <div class="permissions-grid">
                @foreach($modules as $key => $module)
                    <label>
                        <input type="checkbox" name="permissions[]" value="{{ $key }}">
                        <span>
                            {{ $module['label'] }}
                            @if(!empty($module['description']))
                                <small class="module-description">{{ $module['description'] }}</small>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <div class="module-selection-actions" hidden>
        <button type="button" class="btn info btn-xs" data-select-modules="all">Select all</button>
        <button type="button" class="btn warning btn-xs" data-select-modules="none">Clear all</button>
        <span class="module-count" aria-live="polite"></span>
    </div>
</div>
