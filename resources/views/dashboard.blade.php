<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                @if (auth()->user()->isSuperAdmin())
                    {{ __('You are signed in as a super admin.') }}
                @else
                    {{ __('You are signed in to :business.', ['business' => auth()->user()->business?->name ?? '—']) }}
                @endif
            </flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:card>
                <flux:heading size="lg">{{ __('Settings') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 mt-1">{{ __('Update your profile and appearance.') }}</flux:text>
                <div class="mt-4">
                    <flux:button :href="route('profile.edit')" variant="ghost" size="sm" wire:navigate>
                        {{ __('Open') }}
                    </flux:button>
                </div>
            </flux:card>
        </div>
    </div>
</x-layouts::app>
