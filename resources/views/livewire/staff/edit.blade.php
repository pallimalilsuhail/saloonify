<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Location;
use App\Modules\Staff\Exceptions\StaffPolicyException;
use App\Modules\Staff\UseCases\UpdateStaff\UpdateStaff;
use AvoqadoDev\UseCase\Facades\Mediator;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Edit staff')] class extends Component
{
    public User $user;

    public string $name = '';

    public string $role = '';

    public string $status = '';

    /** @var array<int, string> */
    public array $locationIds = [];

    public function mount(User $user): void
    {
        abort_unless($user->business_id === auth()->user()->business_id, 404);

        $this->user = $user;
        $this->name = $user->name;
        $this->role = $user->role->value;
        $this->status = $user->status->value;
        $this->locationIds = $user->locations()->pluck('locations.id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'locations' => Location::query()
                ->where('business_id', auth()->user()->business_id)
                ->orderBy('name')
                ->get(),
        ];
    }

    public function save(): void
    {
        $businessId = auth()->user()->business_id;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:business_admin,location_agent'],
            'status' => ['required', 'in:active,on_leave,terminated'],
            'locationIds' => ['array'],
            'locationIds.*' => ['integer'],
        ]);

        if ($this->role === UserRole::LocationAgent->value && $this->locationIds === []) {
            $this->addError('locationIds', __('Pick at least one location for a location agent.'));

            return;
        }

        try {
            Mediator::dispatch(new UpdateStaff(
                userId: $this->user->id,
                name: $this->name,
                role: UserRole::from($this->role),
                status: UserStatus::from($this->status),
                locationIds: $this->role === UserRole::LocationAgent->value
                    ? array_map('intval', $this->locationIds)
                    : null,
            ));
        } catch (StaffPolicyException $e) {
            $this->addError('role', $e->getMessage());
            $this->addError('status', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Staff member updated.'));
        $this->redirectRoute('staff.index', navigate: true);
    }

    public function deactivate(): void
    {
        try {
            Mediator::dispatch(new UpdateStaff(
                userId: $this->user->id,
                status: UserStatus::Terminated,
            ));
        } catch (StaffPolicyException $e) {
            $this->addError('status', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Staff member deactivated.'));
        $this->redirectRoute('staff.index', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Edit staff') }}</flux:heading>

    <form wire:submit="save" class="flex max-w-lg flex-col gap-6">
        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:select wire:model.live="role" :label="__('Role')">
            <flux:select.option value="location_agent">{{ __('Location agent') }}</flux:select.option>
            <flux:select.option value="business_admin">{{ __('Business admin') }}</flux:select.option>
        </flux:select>
        @error('role') <flux:text class="-mt-3 text-sm text-red-500">{{ $message }}</flux:text> @enderror

        <flux:select wire:model="status" :label="__('Status')">
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="on_leave">{{ __('On leave') }}</flux:select.option>
            <flux:select.option value="terminated">{{ __('Terminated') }}</flux:select.option>
        </flux:select>
        @error('status') <flux:text class="-mt-3 text-sm text-red-500">{{ $message }}</flux:text> @enderror

        <flux:checkbox.group wire:model="locationIds" :label="__('Locations')">
            @foreach ($locations as $location)
                <flux:checkbox :value="(string) $location->id" :label="$location->name" />
            @endforeach
        </flux:checkbox.group>
        @error('locationIds') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button type="button" variant="danger" wire:click="deactivate" wire:confirm="{{ __('Deactivate this staff member?') }}">
                {{ __('Deactivate') }}
            </flux:button>
            <flux:button :href="route('staff.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
