<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Staff')] class extends Component
{
    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'staff' => User::query()
                ->where('business_id', auth()->user()->business_id)
                ->withCount('locations')
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Staff') }}</flux:heading>
        <flux:button :href="route('staff.create')" variant="primary" icon="plus" wire:navigate>
            {{ __('Add staff') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Locations') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($staff as $member)
                <flux:table.row :key="$member->ulid">
                    <flux:table.cell>{{ $member->name }}</flux:table.cell>
                    <flux:table.cell>{{ str($member->role->value)->headline() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$member->status->value === 'active' ? 'green' : ($member->status->value === 'terminated' ? 'red' : 'amber')">
                            {{ str($member->status->value)->headline() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $member->locations_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" :href="route('staff.edit', $member->ulid)" wire:navigate>
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No staff yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
