<div>
    @if ($standalone)
        <div class="max-w-xl">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">{{ __('Create Member') }}</h1>
            @include('livewire.partials.member-form-fields')
        </div>
    @else
        <x-slide-over name="member-form-modal" title="{{ $memberId ? __('Edit Member') : __('Create Member') }}">
            @include('livewire.partials.member-form-fields')
        </x-slide-over>
    @endif
</div>
