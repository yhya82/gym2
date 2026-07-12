<x-app-layout>
    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
        <livewire:admin-dashboard />
    @else
        <livewire:receptionist-dashboard />
    @endif
</x-app-layout>
