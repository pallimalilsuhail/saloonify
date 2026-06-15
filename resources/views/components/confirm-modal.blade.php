@props([
    'name',
    'title',
    'message' => null,
    'confirmLabel' => __('Confirm'),
    'cancelLabel' => __('Cancel'),
    'tone' => 'danger',
    'icon' => 'exclamation-triangle',
    'onConfirm' => null,
])

@php
    $iconBgColor = match ($tone) {
        'danger' => 'bg-red-100 dark:bg-red-950',
        'warning' => 'bg-amber-100 dark:bg-amber-950',
        default => 'bg-zinc-100 dark:bg-zinc-800',
    };
    $iconFgColor = match ($tone) {
        'danger' => 'text-red-600 dark:text-red-400',
        'warning' => 'text-amber-600 dark:text-amber-400',
        default => 'text-zinc-600 dark:text-zinc-400',
    };
    $buttonVariant = $tone === 'warning' ? 'primary' : 'danger';

    // Append a modal-close call to whatever expression the caller passed.
    $confirmExpression = $onConfirm
        ? $onConfirm.'; $flux.modal(\''.$name.'\').close()'
        : '$flux.modal(\''.$name.'\').close()';
@endphp

<flux:modal :name="$name" class="md:w-[28rem]">
    <div class="flex flex-col gap-5">
        <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $iconBgColor }}">
                <flux:icon :name="$icon" variant="outline" class="size-5 {{ $iconFgColor }}" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                @if ($message)
                    <flux:text class="mt-1">{{ $message }}</flux:text>
                @endif
                {{ $slot }}
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
            </flux:modal.close>
            <flux:button
                :variant="$buttonVariant"
                x-on:click="{{ $confirmExpression }}">
                {{ $confirmLabel }}
            </flux:button>
        </div>
    </div>
</flux:modal>
