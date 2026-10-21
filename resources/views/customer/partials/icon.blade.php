<svg class="customer-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('home')
            <path d="m3 10 9-7 9 7v10H3zM9 20v-7h6v7"/>
            @break
        @case('order')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>
            <rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('cart')
            <path d="M2 3h3l3 12h11l3-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>
    @endswitch
</svg>
