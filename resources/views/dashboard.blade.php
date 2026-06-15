<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                @if (auth()->user()->isSuperAdmin())
                    {{ __('You are signed in as a super admin. Manage businesses from the sidebar.') }}
                @elseif (auth()->user()->isOwner())
                    {{ __('You are an owner of :business. Manage members from the sidebar.', ['business' => auth()->user()->business?->name ?? '—']) }}
                @else
                    {{ __('You are a member of :business.', ['business' => auth()->user()->business?->name ?? '—']) }}
                @endif
            </flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @if (auth()->user()->isSuperAdmin())
                <flux:card>
                    <flux:heading size="lg">{{ __('Businesses') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 mt-1">{{ __('Create + manage businesses on the platform.') }}</flux:text>
                    <div class="mt-4">
                        <flux:button :href="route('admin.businesses.index')" variant="primary" size="sm" wire:navigate>
                            {{ __('Open') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if (auth()->user()->isOwner())
                <flux:card>
                    <flux:heading size="lg">{{ __('Members') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 mt-1">{{ __('Invite + manage members of your business.') }}</flux:text>
                    <div class="mt-4">
                        <flux:button :href="route('members.index')" variant="primary" size="sm" wire:navigate>
                            {{ __('Open') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif

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
