<?php

declare(strict_types=1);

use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\UseCases\CreateBusiness\CreateBusiness;
use App\Modules\Businesses\UseCases\InviteMember\InviteMember;
use App\Modules\Businesses\UseCases\SuspendBusiness\SuspendBusiness;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

new #[Title('Businesses')] class extends Component {
    use WithPagination;

    public string $name = '';

    public string $slug = '';

    public string $search = '';

    public string $inviteBusinessUlid = '';

    public string $inviteBusinessName = '';

    public string $inviteEmail = '';

    public ?string $invitationUrl = null;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'inviteEmail' => ['required', 'email'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->validateOnly('name');
        $this->validateOnly('slug');

        $command = app(CreateBusiness::class, [
            'name' => $this->name,
            'slug' => $this->slug !== '' ? $this->slug : null,
        ]);

        try {
            Mediator::dispatch($command);
        } catch (BusinessRuleException $e) {
            $this->addError('slug', $e->getMessage());

            return;
        }

        $this->reset(['name', 'slug']);
        Flux::modal('create-business')->close();
        Flux::toast(variant: 'success', text: __('Business created.'));
    }

    public function suspend(string $ulid): void
    {
        $command = app(SuspendBusiness::class, [
            'businessId' => Id::fromString($ulid),
        ]);

        Mediator::dispatch($command);
        Flux::toast(variant: 'success', text: __('Business suspended.'));
    }

    public function openInviteOwner(string $ulid, string $name): void
    {
        $this->inviteBusinessUlid = $ulid;
        $this->inviteBusinessName = $name;
        $this->reset(['inviteEmail', 'invitationUrl']);
        $this->resetErrorBag('inviteEmail');
        Flux::modal('invite-owner')->show();
    }

    public function sendOwnerInvite(): void
    {
        $this->validateOnly('inviteEmail');

        $command = app(InviteMember::class, [
            'businessId' => Id::fromString($this->inviteBusinessUlid),
            'email' => app(Email::class, ['email' => $this->inviteEmail]),
            'role' => UserRole::Owner,
            'invitedById' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            $result = Mediator::dispatch($command);
        } catch (InvitationException $e) {
            $this->addError('inviteEmail', $e->getMessage());

            return;
        }

        $this->invitationUrl = $result->url;
        $this->reset('inviteEmail');
        Flux::toast(variant: 'success', text: __('Owner invitation created.'));
    }

    public function with(): array
    {
        return [
            'businesses' => Business::query()
                ->when($this->search, fn ($q, $term) => $q->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%");
                }))
                ->orderByDesc('created_at')
                ->paginate(15),
        ];
    }
}; ?>

<section class="flex flex-col gap-6"
         x-data="{ pendingSuspendUlid: null, pendingSuspendName: '' }">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Businesses') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Create and manage businesses on the platform.') }}</flux:text>
        </div>

        <flux:modal.trigger name="create-business">
            <flux:button variant="primary" icon="plus">{{ __('New business') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        type="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name or slug...')"
        class="max-w-md" />

    @if ($businesses->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon.building-office class="mx-auto size-10 text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No businesses yet') }}</flux:heading>
            <flux:text class="text-zinc-500 mt-1">{{ __('Get started by creating your first business.') }}</flux:text>
            <div class="mt-6">
                <flux:modal.trigger name="create-business">
                    <flux:button variant="primary" icon="plus">{{ __('New business') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </flux:card>
    @else
        <flux:table :paginate="$businesses">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Slug') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Created') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($businesses as $business)
                    <flux:table.row :key="$business->ulid">
                        <flux:table.cell>
                            <div class="font-medium">{{ $business->name }}</div>
                            <div class="text-xs text-zinc-500 font-mono">{{ $business->ulid }}</div>
                        </flux:table.cell>
                        <flux:table.cell><code class="text-xs">{{ $business->slug }}</code></flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$business->isActive() ? 'green' : 'red'" size="sm">
                                {{ $business->status->value }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">{{ $business->created_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="inline-flex items-center gap-2">
                                @if ($business->isActive())
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="user-plus"
                                        wire:click="openInviteOwner('{{ $business->ulid }}', '{{ addslashes($business->name) }}')">
                                        {{ __('Invite owner') }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        x-on:click="pendingSuspendUlid='{{ $business->ulid }}'; pendingSuspendName=@js($business->name); $flux.modal('confirm-suspend-business').show()">
                                        {{ __('Suspend') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="create-business" class="md:w-96">
        <form wire:submit="create" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('New business') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create a business and invite an owner afterwards.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            <flux:input wire:model="slug" :label="__('Slug (optional)')" :description="__('Auto-generated from name if blank.')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create business') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="invite-owner" class="md:w-96">
        <form wire:submit="sendOwnerInvite" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('Invite owner') }}</flux:heading>
                @if ($inviteBusinessName)
                    <flux:text class="mt-1">{{ __('Business:') }} <span class="font-medium">{{ $inviteBusinessName }}</span></flux:text>
                @endif
            </div>

            @if ($invitationUrl)
                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 p-3">
                    <flux:text class="text-sm text-green-800 dark:text-green-200 mb-2">{{ __('Invite link (valid 72 hours). Share with the new owner:') }}</flux:text>
                    <code class="block break-all rounded bg-white dark:bg-zinc-900 px-2 py-1.5 text-xs">{{ $invitationUrl }}</code>
                </div>
            @endif

            <flux:input wire:model="inviteEmail" :label="__('Email')" type="email" required autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Send invite') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-confirm-modal
        name="confirm-suspend-business"
        :title="__('Suspend this business?')"
        :confirm-label="__('Suspend business')"
        tone="danger"
        icon="no-symbol"
        on-confirm="$wire.suspend(pendingSuspendUlid)">
        <flux:text class="mt-1">
            <span x-text="pendingSuspendName" class="font-medium"></span>
            {{ __('will lose all access immediately. Members cannot sign in. Data is preserved.') }}
        </flux:text>
    </x-confirm-modal>
</section>
