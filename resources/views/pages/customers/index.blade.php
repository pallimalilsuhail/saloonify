<?php

declare(strict_types=1);

use App\Modules\Customers\UseCases\CreateCustomer\CreateCustomer;
use App\Modules\Customers\UseCases\ListCustomers\ListCustomers;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

new #[Title('Customers')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:6', 'max:32'],
            'email' => ['nullable', 'email'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'phone', 'email']);
        $this->resetErrorBag();
        Flux::modal('create-customer')->show();
    }

    public function create(): void
    {
        $this->validate();

        try {
            $phoneVo = new PhoneNumber($this->phone);
        } catch (\InvalidArgumentException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

        $emailVo = null;
        if ($this->email !== '') {
            try {
                $emailVo = new Email($this->email);
            } catch (\InvalidArgumentException $e) {
                $this->addError('email', $e->getMessage());

                return;
            }
        }

        $user = Auth::user();
        $command = app(CreateCustomer::class, [
            'businessId' => Id::fromString($user->business->ulid),
            'name' => $this->name,
            'phone' => $phoneVo,
            'email' => $emailVo,
            'createdById' => Id::fromString($user->ulid),
        ]);

        try {
            Mediator::dispatch($command);
        } catch (BusinessRuleException $e) {
            $this->addError(str_contains($e->getMessage(), 'phone') ? 'phone' : 'email', $e->getMessage());

            return;
        }

        $this->reset(['name', 'phone', 'email']);
        Flux::modal('create-customer')->close();
        Flux::toast(variant: 'success', text: __('Customer created.'));
    }

    public function with(): array
    {
        $user = Auth::user();
        $page = Mediator::dispatch(app(ListCustomers::class, [
            'businessId' => Id::fromString($user->business->ulid),
            'search' => $this->search !== '' ? $this->search : null,
            'perPage' => 25,
        ]));

        return [
            'customers' => $page,
        ];
    }
}; ?>

<section class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('People your business is collecting documents from.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="user-plus" wire:click="openCreate">
            {{ __('New customer') }}
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        type="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name, phone, or email...')"
        class="max-w-md" />

    @if ($customers->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon.users class="mx-auto size-10 text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No customers yet') }}</flux:heading>
            <flux:text class="text-zinc-500 mt-1">{{ __('Add your first customer to start collecting documents.') }}</flux:text>
            <div class="mt-6">
                <flux:button variant="primary" icon="user-plus" wire:click="openCreate">{{ __('New customer') }}</flux:button>
            </div>
        </flux:card>
    @else
        <flux:table :paginate="$customers">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Created') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($customers as $customer)
                    <flux:table.row :key="$customer->id->toString()">
                        <flux:table.cell class="font-medium">{{ $customer->name }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $customer->phone->toInternational() }}</flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">{{ $customer->email?->toString() ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">{{ $customer->createdAt->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                :href="route('customers.show', ['ulid' => $customer->id->toString()])"
                                wire:navigate>
                                {{ __('View') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="create-customer" class="md:w-96">
        <form wire:submit="create" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('New customer') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Phone is required for sending the document upload link.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            <x-phone-input wire-model="phone" :label="__('Phone')" :required="true" />
            <flux:input wire:model="email" :label="__('Email (optional)')" type="email" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="create">
                    <span wire:loading.remove wire:target="create">{{ __('Create customer') }}</span>
                    <span wire:loading wire:target="create">{{ __('Creating...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
