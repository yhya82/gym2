@props(['name'])

@php
    // Kept deliberately simple (basic shapes: circles/rects/lines/short
    // polylines) rather than reproducing a third-party icon set's exact
    // multi-curve path data from memory.
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'members' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c0-4.1 3.4-7.5 7.5-7.5s7.5 3.4 7.5 7.5"/>',
        'plans' => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/>',
        'payments' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
        'users' => '<path d="M12 3l7 3.5v5c0 4.5-3 8.5-7 9.5-4-1-7-5-7-9.5v-5L12 3z"/>',
        'settings' => '<line x1="4" y1="6" x2="20" y2="6"/><circle cx="9" cy="6" r="2"/><line x1="4" y1="12" x2="20" y2="12"/><circle cx="15" cy="12" r="2"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="7" cy="18" r="2"/>',
        'revenue' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.75"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5 5-6"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/>',
        'audit' => '<rect x="4" y="3" width="13" height="18" rx="1.5"/><line x1="7" y1="7.5" x2="14" y2="7.5"/><line x1="7" y1="11" x2="14" y2="11"/><circle cx="17" cy="17" r="3.5"/><line x1="19.5" y1="19.5" x2="21.5" y2="21.5"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'h-5 w-5', 'fill' => 'none', 'viewBox' => '0 0 24 24', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
    {!! $paths[$name] ?? '' !!}
</svg>
