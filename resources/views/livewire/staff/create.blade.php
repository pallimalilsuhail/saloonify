<?php

declare(strict_types=1);

use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Location;
use App\Modules\Staff\UseCases\CreateStaff\CreateStaff;
use AvoqadoDev\UseCase\Facades\Mediator;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Add staff')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $username = '';

    public string $password = '';

    public string $role = 'location_agent';

    /** @var array<int, string> */
    public array $locationIds = [];

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
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:business_admin,location_agent'],
            'locationIds' => ['array'],
            'locationIds.*' => [Rule::exists('locations', 'id')->where('business_id', $businessId)],
        ]);

        if ($this->email === '' && $this->username === '') {
            $this->addError('email', __('Provide an email or a username.'));

            return;
        }

        if ($this->role === UserRole::LocationAgent->value && $this->locationIds === []) {
            $this->addError('locationIds', __('Pick at least one location for a location agent.'));

            return;
        }

        Mediator::dispatch(new CreateStaff(
            businessId: $businessId,
            name: $this->name,
            email: $this->email !== '' ? $this->email : null,
            username: $this->username !== '' ? $this->username : null,
            password: $this->password,
            role: UserRole::from($this->role),
            locationIds: array_map('intval', $this->locationIds),
        ));

        Flux::toast(variant: 'success', text: __('Staff member created.'));
        $this->redirectRoute('staff.index', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Add staff') }}</flux:heading>

    <form wire:submit="save" class="flex max-w-lg flex-col gap-6">
        <flux:input wire:model="name" :label="__('Name')" required autofocus />
        <flux:input wire:model="email" :label="__('Email (optional)')" type="email" />
        <flux:input wire:model="username" :label="__('Username (optional)')" />
        <flux:text class="-mt-3 text-sm text-zinc-500">{{ __('Provide an email or a username (at least one).') }}</flux:text>
        <flux:input wire:model="password" :label="__('Password')" type="password" viewable required />

        <flux:select wire:model.live="role" :label="__('Role')">
            <flux:select.option value="location_agent">{{ __('Location agent') }}</flux:select.option>
            <flux:select.option value="business_admin">{{ __('Business admin') }}</flux:select.option>
        </flux:select>

        <flux:checkbox.group wire:model="locationIds" :label="__('Locations')">
            @foreach ($locations as $location)
                <flux:checkbox :value="(string) $location->id" :label="$location->name" />
            @endforeach
        </flux:checkbox.group>
        @error('locationIds') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            <flux:button :href="route('staff.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
