@props(['userId' => null, 'size' => 'md'])

{{-- Opens App\Livewire\ChangePasswordModal — for the signed-in user when
     $userId is null, otherwise for that user (Admin only; the modal
     re-checks UserPolicy::changePassword). Amber + key icon so it reads as a
     security action, distinct from the indigo Edit and red Deactivate. --}}
<button
    type="button"
    onclick="Livewire.dispatch('open-change-password', { userId: {{ $userId === null ? 'null' : (int) $userId }} })"
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 rounded-md border font-semibold transition-colors',
        'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:border-amber-400',
        'dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-1',
        'min-h-[40px] px-4 text-sm' => $size === 'md',
        'px-2.5 py-1 text-xs' => $size === 'sm',
    ]) }}
>
    <x-icon name="key" :class="$size === 'sm' ? 'h-3.5 w-3.5 shrink-0' : 'h-4 w-4 shrink-0'" aria-hidden="true" />
    {{ __('Change password') }}
</button>
