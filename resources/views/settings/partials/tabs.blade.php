{{-- General | Security Center — shared by both Settings pages.
     $current is passed in rather than read from request()->routeIs(): both
     pages include this from a Livewire component, and on a Livewire
     re-render the current route is Livewire's update endpoint. --}}
<nav aria-label="{{ __('Settings sections') }}" class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
    @foreach ([
        ['route' => 'settings.show', 'label' => __('General')],
        ['route' => 'settings.security', 'label' => __('Security Center')],
    ] as $item)
        @php $active = $item['route'] === $current; @endphp
        <a
            href="{{ route($item['route']) }}"
            wire:navigate
            @if ($active) aria-current="page" @endif
            @class([
                '-mb-px px-4 py-3 text-sm border-b-2',
                'border-indigo-600 font-semibold text-indigo-700 dark:text-indigo-400' => $active,
                'border-transparent font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100' => ! $active,
            ])
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
