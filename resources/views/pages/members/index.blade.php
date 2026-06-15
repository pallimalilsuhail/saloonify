<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Invitation;
use App\Modules\Businesses\UseCases\InviteMember\InviteMember;
use App\Modules\Businesses\UseCases\RemoveMember\RemoveMember;
use App\Modules\Businesses\UseCases\RevokeInvite\RevokeInvite;
use App\Modules\Businesses\UseCases\UpdateMemberRole\UpdateMemberRole;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use AvoqadoDev\UseCase\Facades\Mediator;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

new #[Title('Members')] class extends Component {
    public string $email = '';

    public string $role = 'member';

    public ?string $invitationUrl = null;

    public string $activeTab = 'members';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'in:owner,member'],
        ];
    }

    public function openInvite(): void
    {
        $this->reset(['email', 'role', 'invitationUrl']);
        $this->role = 'member';
        $this->resetErrorBag();
        Flux::modal('invite-member')->show();
    }

    public function resetInvite(): void
    {
        $this->reset(['email', 'role', 'invitationUrl']);
        $this->role = 'member';
        $this->resetErrorBag();
    }

    public function invite(): void
    {
        $this->validate();

        $user = Auth::user();

        $command = app(InviteMember::class, [
            'businessId' => Id::fromString($user->business->ulid),
            'email' => app(Email::class, ['email' => $this->email]),
            'role' => UserRole::from($this->role),
            'invitedById' => Id::fromString($user->ulid),
        ]);

        try {
            $result = Mediator::dispatch($command);
        } catch (InvitationException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        $this->invitationUrl = $result->url;
        $this->reset(['email']);
        Flux::toast(variant: 'success', text: __('Invitation created.'));
    }

    public function revoke(string $invitationUlid): void
    {
        $command = app(RevokeInvite::class, [
            'invitationId' => Id::fromString($invitationUlid),
            'actorId' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            Mediator::dispatch($command);
            Flux::toast(variant: 'success', text: __('Invitation revoked.'));
        } catch (InvitationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function changeRole(string $memberUlid, string $newRole): void
    {
        $command = app(UpdateMemberRole::class, [
            'memberId' => Id::fromString($memberUlid),
            'newRole' => UserRole::from($newRole),
            'actorId' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            Mediator::dispatch($command);
            Flux::toast(variant: 'success', text: __('Role updated.'));
        } catch (InvitationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function remove(string $memberUlid): void
    {
        $command = app(RemoveMember::class, [
            'memberId' => Id::fromString($memberUlid),
            'actorId' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            Mediator::dispatch($command);
            Flux::toast(variant: 'success', text: __('Member removed.'));
        } catch (InvitationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $businessId = Id::fromString($user->business->ulid);

        $members = User::query()
            ->tap(new BelongsToBusiness($businessId))
            ->orderBy('name')
            ->get();

        $invitations = Invitation::query()
            ->tap(new BelongsToBusiness($businessId))
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return [
            'members' => $members,
            'invitations' => $invitations,
            'membersCount' => $members->count(),
            'invitationsCount' => $invitations->count(),
        ];
    }
}; ?>

<section class="flex flex-col gap-6"
         x-data="{ pendingMemberUlid: null, pendingMemberName: '', pendingNewRole: 'member', pendingInviteUlid: null }">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Members') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('People with access to your business.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="user-plus" wire:click="openInvite">
            {{ __('Invite member') }}
        </flux:button>
    </div>

    <div class="flex border-b border-zinc-200 dark:border-zinc-700">
        <button
            type="button"
            wire:click="$set('activeTab', 'members')"
            @class([
                'px-4 py-2 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                'border-accent text-accent' => $activeTab === 'members',
                'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200' => $activeTab !== 'members',
            ])>
            {{ __('Current members') }}
            <flux:badge size="sm" variant="pill">{{ $membersCount }}</flux:badge>
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'invitations')"
            @class([
                'px-4 py-2 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                'border-accent text-accent' => $activeTab === 'invitations',
                'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200' => $activeTab !== 'invitations',
            ])>
            {{ __('Pending invitations') }}
            <flux:badge size="sm" variant="pill">{{ $invitationsCount }}</flux:badge>
        </button>
    </div>

    @if ($activeTab === 'members')
            @if ($members->isEmpty())
                <flux:card class="text-center py-12">
                    <flux:icon.users class="mx-auto size-10 text-zinc-400" />
                    <flux:heading size="lg" class="mt-4">{{ __('No members yet') }}</flux:heading>
                    <flux:text class="text-zinc-500 mt-1">{{ __('Invite your first teammate.') }}</flux:text>
                    <div class="mt-6">
                        <flux:button variant="primary" icon="user-plus" wire:click="openInvite">{{ __('Invite member') }}</flux:button>
                    </div>
                </flux:card>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($members as $member)
                            <flux:table.row :key="$member->ulid">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar :name="$member->name" :initials="$member->initials()" size="sm" />
                                        <span class="font-medium">{{ $member->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $member->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$member->isOwner() ? 'amber' : 'zinc'" size="sm">
                                        {{ $member->role->value }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    @if ($member->id !== auth()->id())
                                        <div class="inline-flex items-center gap-2">
                                            @if ($member->isOwner())
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    x-on:click="pendingMemberUlid='{{ $member->ulid }}'; pendingMemberName=@js($member->name); pendingNewRole='member'; $flux.modal('confirm-change-role').show()">
                                                    {{ __('Make member') }}
                                                </flux:button>
                                            @else
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    x-on:click="pendingMemberUlid='{{ $member->ulid }}'; pendingMemberName=@js($member->name); pendingNewRole='owner'; $flux.modal('confirm-change-role').show()">
                                                    {{ __('Make owner') }}
                                                </flux:button>
                                            @endif

                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                x-on:click="pendingMemberUlid='{{ $member->ulid }}'; pendingMemberName=@js($member->name); $flux.modal('confirm-remove-member').show()">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    @else
                                        <flux:text class="text-xs text-zinc-400">{{ __('You') }}</flux:text>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
    @endif

    @if ($activeTab === 'invitations')
            @if ($invitations->isEmpty())
                <flux:card class="text-center py-12">
                    <flux:icon.envelope class="mx-auto size-10 text-zinc-400" />
                    <flux:heading size="lg" class="mt-4">{{ __('No pending invitations') }}</flux:heading>
                    <flux:text class="text-zinc-500 mt-1">{{ __('Invitations sent here will appear until they are accepted, revoked, or expire.') }}</flux:text>
                </flux:card>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Expires') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($invitations as $invitation)
                            <flux:table.row :key="$invitation->ulid">
                                <flux:table.cell class="font-medium">{{ $invitation->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$invitation->role->is(\App\Modules\Businesses\Enums\UserRole::Owner) ? 'amber' : 'zinc'" size="sm">
                                        {{ $invitation->role->value }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $invitation->expires_at->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        x-on:click="pendingInviteUlid='{{ $invitation->ulid }}'; $flux.modal('confirm-revoke-invite').show()">
                                        {{ __('Revoke') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
    @endif

    <flux:modal name="invite-member" class="md:w-[28rem]">
        @if ($invitationUrl)
            <div class="flex flex-col gap-5"
                 x-data="{
                    copied: false,
                    async copy() {
                        const text = @js($invitationUrl);
                        try {
                            if (navigator.clipboard && window.isSecureContext) {
                                await navigator.clipboard.writeText(text);
                            } else {
                                const ta = document.createElement('textarea');
                                ta.value = text;
                                ta.style.position = 'fixed';
                                ta.style.left = '-9999px';
                                document.body.appendChild(ta);
                                ta.focus();
                                ta.select();
                                document.execCommand('copy');
                                document.body.removeChild(ta);
                            }
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        } catch (e) {
                            console.error('Copy failed', e);
                        }
                    }
                 }">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-950">
                        <flux:icon.check class="size-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">{{ __('Invitation created') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Send this link to the new member. It expires in 72 hours.') }}</flux:text>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <code class="block break-all rounded-lg bg-zinc-100 dark:bg-zinc-800 px-3 py-2 text-xs">{{ $invitationUrl }}</code>
                    <div class="flex justify-end">
                        <flux:button size="sm" variant="ghost" icon="clipboard" x-on:click="copy()">
                            <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy link') }}'">{{ __('Copy link') }}</span>
                        </flux:button>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="resetInvite">{{ __('Invite another') }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="primary" wire:click="resetInvite">{{ __('Done') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @else
            <form wire:submit="invite" class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Invite member') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('They\'ll get an invite link valid for 72 hours.') }}</flux:text>
                </div>

                <flux:input wire:model="email" :label="__('Email')" type="email" required autofocus />

                <flux:select wire:model="role" :label="__('Role')">
                    <flux:select.option value="member">{{ __('Member') }}</flux:select.option>
                    <flux:select.option value="owner">{{ __('Owner') }}</flux:select.option>
                </flux:select>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="invite">
                        <span wire:loading.remove wire:target="invite">{{ __('Create invite link') }}</span>
                        <span wire:loading wire:target="invite">{{ __('Creating...') }}</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <x-confirm-modal
        name="confirm-change-role"
        :title="__('Change member role?')"
        :confirm-label="__('Change role')"
        tone="warning"
        icon="user"
        on-confirm="$wire.changeRole(pendingMemberUlid, pendingNewRole)">
        <flux:text class="mt-1">
            <span x-text="pendingMemberName" class="font-medium"></span>
            {{ __('will become a') }}
            <span x-text="pendingNewRole" class="font-medium"></span>.
            <span x-show="pendingNewRole === 'member'">{{ __('They will lose owner-only access.') }}</span>
            <span x-show="pendingNewRole === 'owner'">{{ __('They will gain full administrative access including audit log and member management.') }}</span>
        </flux:text>
    </x-confirm-modal>

    <x-confirm-modal
        name="confirm-remove-member"
        :title="__('Remove member from your business?')"
        :confirm-label="__('Remove member')"
        tone="danger"
        icon="user-minus"
        on-confirm="$wire.remove(pendingMemberUlid)">
        <flux:text class="mt-1">
            <span x-text="pendingMemberName" class="font-medium"></span>
            {{ __('will lose access immediately. Their account is preserved but no longer linked to this business.') }}
        </flux:text>
    </x-confirm-modal>

    <x-confirm-modal
        name="confirm-revoke-invite"
        :title="__('Revoke this invitation?')"
        :message="__('The invite link will stop working. The recipient will not be notified.')"
        :confirm-label="__('Revoke invitation')"
        tone="warning"
        icon="envelope"
        on-confirm="$wire.revoke(pendingInviteUlid)" />
</section>
