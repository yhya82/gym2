<div>
    @if ($standalone)
        <div class="max-w-xl">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">{{ __('Create Member') }}</h1>
            @include('livewire.partials.member-form-fields')
        </div>
    @else
        <x-modal name="member-form-modal" focusable>
            <div class="p-6 bg-white dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">{{ __('Edit Member') }}</h2>
                @include('livewire.partials.member-form-fields')
            </div>
        </x-modal>
    @endif
</div>
