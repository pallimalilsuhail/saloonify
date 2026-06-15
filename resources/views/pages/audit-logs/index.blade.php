<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditAction;
use App\Modules\AuditLog\UseCases\ListAuditLogs\ListAuditLogs;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use AvoqadoDev\UseCase\Facades\Mediator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Shared\ValueObjects\Id;

new #[Title('Audit log')] class extends Component {
    use WithPagination;

    #[Url(as: 'actor', except: '')]
    public string $actor = '';

    #[Url(as: 'action', except: '')]
    public string $action = '';

    #[Url(as: 'from', except: '')]
    public string $from = '';

    #[Url(as: 'to', except: '')]
    public string $to = '';

    public function updating(string $name): void
    {
        if (in_array($name, ['actor', 'action', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['actor', 'action', 'from', 'to']);
        $this->resetPage();
    }

    public function with(): array
    {
        $businessId = Id::fromString(Auth::user()->business->ulid);

        $logs = Mediator::dispatch(app(ListAuditLogs::class, [
            'businessId' => $businessId,
            'actorId' => $this->actor !== '' ? Id::fromString($this->actor) : null,
            'action' => $this->action !== '' ? $this->action : null,
            'from' => $this->from !== '' ? CarbonImmutable::parse($this->from)->startOfDay() : null,
            'to' => $this->to !== '' ? CarbonImmutable::parse($this->to)->endOfDay() : null,
            'perPage' => 25,
        ]));

        $actors = User::query()
            ->tap(new BelongsToBusiness($businessId))
            ->orderBy('name')
            ->get(['ulid', 'name', 'email']);

        return [
            'logs' => $logs,
            'actors' => $actors,
            'actions' => AuditAction::cases(),
            'exportUrl' => route('audit-logs.export', array_filter([
                'actor' => $this->actor !== '' ? $this->actor : null,
                'action' => $this->action !== '' ? $this->action : null,
                'from' => $this->from !== '' ? $this->from : null,
                'to' => $this->to !== '' ? $this->to : null,
            ])),
        ];
    }
}; ?>

<section class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Audit log') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Every sensitive action in your business, append-only.') }}</flux:text>
        </div>

        <flux:button
            variant="primary"
            icon="arrow-down-tray"
            :href="$exportUrl">
            {{ __('Export CSV') }}
        </flux:button>
    </div>

    <flux:card>
        <div class="grid gap-3 md:grid-cols-4">
            <flux:select wire:model.live="actor" :label="__('Actor')">
                <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
                @foreach ($actors as $a)
                    <flux:select.option value="{{ $a->ulid }}">{{ $a->name }} ({{ $a->email }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="action" :label="__('Action')">
                <flux:select.option value="">{{ __('Any action') }}</flux:select.option>
                @foreach ($actions as $a)
                    <flux:select.option value="{{ $a->value }}">{{ $a->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.500ms="from" type="date" :label="__('From')" />
            <flux:input wire:model.live.debounce.500ms="to" type="date" :label="__('To')" />
        </div>

        @if ($actor !== '' || $action !== '' || $from !== '' || $to !== '')
            <div class="mt-3 flex justify-end">
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            </div>
        @endif
    </flux:card>

    @if ($logs->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon.shield-check class="mx-auto size-10 text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No audit entries match these filters') }}</flux:heading>
            <flux:text class="text-zinc-500 mt-1">{{ __('Try widening the date range or clearing filters.') }}</flux:text>
        </flux:card>
    @else
        <div x-data="{ expanded: null, toggle(id) { this.expanded = this.expanded === id ? null : id } }">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-8"></flux:table.column>
                    <flux:table.column>{{ __('When') }}</flux:table.column>
                    <flux:table.column>{{ __('Who') }}</flux:table.column>
                    <flux:table.column>{{ __('Action') }}</flux:table.column>
                    <flux:table.column>{{ __('Target') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($logs as $entry)
                        @php
                            $actionEnum = \App\Modules\AuditLog\Enums\AuditAction::tryFrom($entry->action);
                            $actionLabel = $actionEnum?->label() ?? $entry->action;
                            $actionTone = $actionEnum?->tone() ?? 'zinc';
                            $hasMeta = ! empty($entry->meta);
                            $rowKey = $entry->id->toString();
                        @endphp
                        <flux:table.row :key="$rowKey">
                            <flux:table.cell>
                                @if ($hasMeta || $entry->ip || $entry->targetId)
                                    <button type="button"
                                            x-on:click="toggle('{{ $rowKey }}')"
                                            class="inline-flex size-6 items-center justify-center rounded text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        <flux:icon.chevron-right class="size-4 transition-transform" x-bind:class="expanded === '{{ $rowKey }}' ? 'rotate-90' : ''" />
                                    </button>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap" :title="$entry->createdAt->toIso8601String()">
                                {{ $entry->createdAt->diffForHumans() }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($entry->actorName)
                                    <div class="text-sm font-medium">{{ $entry->actorName }}</div>
                                    <div class="text-xs text-zinc-500">{{ $entry->actorEmail }}</div>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ $entry->actorType }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$actionTone">{{ $actionLabel }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $entry->targetType ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                        <template x-if="expanded === '{{ $rowKey }}'">
                            <flux:table.row>
                                <flux:table.cell></flux:table.cell>
                                <flux:table.cell colspan="4" class="bg-zinc-50 dark:bg-zinc-900">
                                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs py-2">
                                        <div>
                                            <dt class="text-zinc-500">{{ __('Timestamp') }}</dt>
                                            <dd class="font-mono">{{ $entry->createdAt->toIso8601String() }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-zinc-500">{{ __('Action key') }}</dt>
                                            <dd class="font-mono">{{ $entry->action }}</dd>
                                        </div>
                                        @if ($entry->targetId)
                                            <div>
                                                <dt class="text-zinc-500">{{ __('Target ID') }}</dt>
                                                <dd class="font-mono break-all">{{ $entry->targetId }}</dd>
                                            </div>
                                        @endif
                                        @if ($entry->ip)
                                            <div>
                                                <dt class="text-zinc-500">{{ __('IP address') }}</dt>
                                                <dd class="font-mono">{{ $entry->ip }}</dd>
                                            </div>
                                        @endif
                                        @if ($hasMeta)
                                            <div class="sm:col-span-2">
                                                <dt class="text-zinc-500 mb-1">{{ __('Details') }}</dt>
                                                <dd class="space-y-1">
                                                    @foreach ($entry->meta as $k => $v)
                                                        <div class="flex gap-2">
                                                            <span class="text-zinc-500 font-mono">{{ $k }}:</span>
                                                            <span class="font-mono">{{ is_scalar($v) ? (string) $v : json_encode($v) }}</span>
                                                        </div>
                                                    @endforeach
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                </flux:table.cell>
                            </flux:table.row>
                        </template>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div>
            {{ $logs->links() }}
        </div>
    @endif
</section>
