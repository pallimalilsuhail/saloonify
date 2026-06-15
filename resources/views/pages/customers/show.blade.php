<?php

declare(strict_types=1);

use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\UseCases\GetCustomer\GetCustomer;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\Exceptions\CannotRevokeUploadLink;
use App\Modules\DocumentRequests\UseCases\GenerateUploadLink\GenerateUploadLink;
use App\Modules\DocumentRequests\UseCases\RegenerateUploadLink\RegenerateUploadLink;
use App\Modules\DocumentRequests\UseCases\RevokeUploadLink\RevokeUploadLink;
use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\UseCases\DeleteDocument\DeleteDocument;
use App\Modules\Documents\UseCases\ListCustomerDocuments\ListCustomerDocuments;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Title('Customer')] class extends Component {
    public string $ulid = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public bool $clearEmail = false;

    public ?string $generatedUrl = null;

    public ?string $generatedExpiresAtIso = null;

    public ?string $generatedQrSvg = null;

    public function mount(string $ulid): void
    {
        $this->ulid = $ulid;
    }

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

    public function openEdit(): void
    {
        $detail = $this->loadDetail();
        $this->name = $detail->name;
        $this->phone = $detail->phone->toE164();
        $this->email = $detail->email?->toString() ?? '';
        $this->clearEmail = false;
        $this->resetErrorBag();
        Flux::modal('edit-customer')->show();
    }

    public function update(): void
    {
        $this->validate();

        try {
            $phoneVo = new PhoneNumber($this->phone);
        } catch (\InvalidArgumentException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

        $emailValue = match (true) {
            $this->clearEmail => false,
            $this->email === '' => null,
            default => null,
        };

        if (! $this->clearEmail && $this->email !== '') {
            try {
                $emailValue = new Email($this->email);
            } catch (\InvalidArgumentException $e) {
                $this->addError('email', $e->getMessage());

                return;
            }
        }

        $command = app(UpdateCustomer::class, [
            'businessId' => Id::fromString(Auth::user()->business->ulid),
            'customerId' => Id::fromString($this->ulid),
            'name' => $this->name,
            'phone' => $phoneVo,
            'email' => $emailValue,
        ]);

        try {
            Mediator::dispatch($command);
        } catch (BusinessRuleException $e) {
            $this->addError(str_contains($e->getMessage(), 'phone') ? 'phone' : 'email', $e->getMessage());

            return;
        }

        Flux::modal('edit-customer')->close();
        Flux::toast(variant: 'success', text: __('Customer updated.'));
    }

    public function generateUploadLink(): void
    {
        $command = app(GenerateUploadLink::class, [
            'businessId' => Id::fromString(Auth::user()->business->ulid),
            'customerId' => Id::fromString($this->ulid),
            'generatedById' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            $issued = Mediator::dispatch($command);
        } catch (ModelNotFoundException) {
            Flux::toast(variant: 'danger', text: __('Customer not found.'));

            return;
        }

        $this->showIssuedLink($issued);
        Flux::toast(variant: 'success', text: __('Upload link created.'));
    }

    public function regenerateUploadLink(): void
    {
        $command = app(RegenerateUploadLink::class, [
            'businessId' => Id::fromString(Auth::user()->business->ulid),
            'customerId' => Id::fromString($this->ulid),
            'generatedById' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            $issued = Mediator::dispatch($command);
        } catch (ModelNotFoundException) {
            Flux::toast(variant: 'danger', text: __('Customer not found.'));

            return;
        }

        $this->showIssuedLink($issued);
        Flux::toast(variant: 'success', text: __('Old links revoked. New link created.'));
    }

    public function revokeUploadLink(string $sessionUlid): void
    {
        $command = app(RevokeUploadLink::class, [
            'sessionId' => Id::fromString($sessionUlid),
            'revokedById' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            Mediator::dispatch($command);
            Flux::toast(variant: 'success', text: __('Upload link revoked.'));
        } catch (CannotRevokeUploadLink $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function deleteDocument(string $documentUlid): void
    {
        $command = app(DeleteDocument::class, [
            'businessId' => Id::fromString(Auth::user()->business->ulid),
            'documentId' => Id::fromString($documentUlid),
            'actorId' => Id::fromString(Auth::user()->ulid),
        ]);

        try {
            Mediator::dispatch($command);
            Flux::toast(variant: 'success', text: __('Document deleted.'));
        } catch (DocumentAccessDenied) {
            Flux::toast(variant: 'danger', text: __('You are not allowed to delete this document.'));
        } catch (ModelNotFoundException) {
            Flux::toast(variant: 'danger', text: __('Document not found.'));
        }
    }

    private function showIssuedLink(\App\Modules\DocumentRequests\DTOs\IssuedUploadLink $issued): void
    {
        $this->generatedUrl = $issued->url;
        $this->generatedExpiresAtIso = $issued->expiresAt->toIso8601String();
        $this->generatedQrSvg = (string) QrCode::format('svg')->size(180)->margin(0)->generate($issued->url);

        Flux::modal('upload-link')->show();
    }

    private function loadDetail(): \App\Modules\Customers\DTOs\CustomerDetails
    {
        return Mediator::dispatch(app(GetCustomer::class, [
            'businessId' => Id::fromString(Auth::user()->business->ulid),
            'customerId' => Id::fromString($this->ulid),
        ]));
    }

    public function with(): array
    {
        try {
            $detail = $this->loadDetail();
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $sessions = UploadSession::query()
            ->tap(new BelongsToBusiness(Id::fromString(Auth::user()->business->ulid)))
            ->whereHas('customer', fn ($q) => $q->where('ulid', $this->ulid))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $documents = Mediator::dispatch(app(ListCustomerDocuments::class, [
            'businessId' => Id::fromString(Auth::user()->business->ulid),
            'customerId' => Id::fromString($this->ulid),
            'perPage' => 50,
        ]));

        return [
            'detail' => $detail,
            'sessions' => $sessions,
            'activeStatus' => UploadSessionStatus::Active,
            'documents' => $documents,
            'isOwner' => Auth::user()->isOwner(),
        ];
    }
}; ?>

<section class="flex flex-col gap-6"
         x-data="{ pendingSessionUlid: null, pendingDocId: null, pendingDocName: '' }">
    <div class="flex items-center gap-3">
        <flux:button :href="route('customers.index')" variant="ghost" icon="arrow-left" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <div class="min-w-0">
            <flux:heading size="xl">{{ $detail->name }}</flux:heading>
            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
                <span>{{ $detail->phone->toInternational() }}</span>
                @if ($detail->email)
                    <span>{{ $detail->email->toString() }}</span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="pencil" wire:click="openEdit">
                {{ __('Edit') }}
            </flux:button>
            <flux:button
                variant="primary"
                icon="link"
                wire:click="generateUploadLink"
                wire:loading.attr="disabled"
                wire:target="generateUploadLink">
                <span wire:loading.remove wire:target="generateUploadLink">{{ __('Generate link') }}</span>
                <span wire:loading wire:target="generateUploadLink">{{ __('Creating...') }}</span>
            </flux:button>
        </div>
    </div>

    @php
        $hasActive = $sessions->contains(fn ($s) => $s->status === $activeStatus && ! $s->isExpired());
    @endphp

    <flux:card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    {{ __('Files uploaded by this customer across all upload links.') }}
                </flux:text>
            </div>
            <flux:badge size="sm" color="zinc">{{ $documents->total() }}</flux:badge>
        </div>

        <div class="mt-6">
            @if ($documents->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 p-6 text-center text-sm text-zinc-500">
                    {{ __('No documents uploaded yet.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pr-3 font-medium">{{ __('File') }}</th>
                                <th class="py-2 px-3 font-medium">{{ __('Type') }}</th>
                                <th class="py-2 px-3 font-medium">{{ __('Size') }}</th>
                                <th class="py-2 px-3 font-medium">{{ __('Uploaded') }}</th>
                                <th class="py-2 px-3 font-medium">{{ __('Source link') }}</th>
                                <th class="py-2 pl-3 font-medium text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($documents as $doc)
                                <tr>
                                    <td class="py-3 pr-3 break-all">{{ $doc->originalName }}</td>
                                    <td class="py-3 px-3 text-xs text-zinc-500 font-mono">{{ $doc->mime }}</td>
                                    <td class="py-3 px-3 text-zinc-500">{{ number_format($doc->sizeBytes / 1024, 1) }} KB</td>
                                    <td class="py-3 px-3 text-zinc-500">
                                        {{ $doc->uploadedAt?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-zinc-500 font-mono text-xs">
                                        {{ Str::substr($doc->uploadSessionId->toString(), 0, 8) }}…
                                    </td>
                                    <td class="py-3 pl-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="eye"
                                                :href="route('documents.view', $doc->id->toString())"
                                                target="_blank"
                                                rel="noopener">
                                                {{ __('View') }}
                                            </flux:button>
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="arrow-down-tray"
                                                :href="route('documents.download', $doc->id->toString())">
                                                {{ __('Download') }}
                                            </flux:button>
                                            @if ($isOwner)
                                                <flux:button
                                                    size="xs"
                                                    variant="danger"
                                                    icon="trash"
                                                    x-on:click="pendingDocId = '{{ $doc->id->toString() }}'; pendingDocName = @js($doc->originalName); $flux.modal('confirm-delete-doc').show()">
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </flux:card>

    <flux:card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Upload links') }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    {{ __('History of one-time links sent to this customer.') }}
                </flux:text>
            </div>
            @if ($hasActive)
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="arrow-path"
                    x-on:click="$flux.modal('confirm-regenerate-link').show()">
                    {{ __('Regenerate active link') }}
                </flux:button>
            @endif
        </div>

        <div class="mt-6">
            @if ($sessions->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 p-6 text-center text-sm text-zinc-500">
                    {{ __('No upload links generated yet. Use "Generate link" above to send the customer a one-time link.') }}
                </div>
            @else
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($sessions as $session)
                        @php
                            $effectiveStatus = $session->status->is($activeStatus) && $session->isExpired()
                                ? \App\Modules\DocumentRequests\Enums\UploadSessionStatus::Expired
                                : $session->status;
                            $color = match ($effectiveStatus) {
                                \App\Modules\DocumentRequests\Enums\UploadSessionStatus::Active => 'green',
                                \App\Modules\DocumentRequests\Enums\UploadSessionStatus::Submitted => 'blue',
                                default => 'zinc',
                            };
                            $isActiveRow = $effectiveStatus->is(\App\Modules\DocumentRequests\Enums\UploadSessionStatus::Active);
                        @endphp
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="text-sm">
                                <div class="text-zinc-700 dark:text-zinc-200">{{ __('Sent') }} {{ $session->created_at->diffForHumans() }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ __('Expires') }} {{ $session->expires_at->diffForHumans() }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="$color" size="sm">{{ $effectiveStatus->value }}</flux:badge>
                                @if ($isActiveRow)
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        x-on:click="pendingSessionUlid = '{{ $session->ulid }}'; $flux.modal('confirm-revoke-session').show()">
                                        {{ __('Revoke') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </flux:card>

    <div class="text-xs text-zinc-500 flex flex-wrap gap-x-4 gap-y-1">
        <span>{{ __('Created') }} {{ $detail->createdAt->diffForHumans() }}</span>
        @if ($detail->createdByName)
            <span>{{ __('by') }} {{ $detail->createdByName }}</span>
        @endif
        <span class="font-mono">{{ $detail->id->toString() }}</span>
    </div>

    <flux:modal name="edit-customer" class="md:w-96">
        <form wire:submit="update" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Edit customer') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            <x-phone-input wire-model="phone" :label="__('Phone')" :required="true" />
            <flux:input wire:model="email" :label="__('Email')" type="email" :disabled="$clearEmail" />

            <flux:field variant="inline">
                <flux:checkbox wire:model.live="clearEmail" />
                <flux:label>{{ __('Clear email') }}</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="update">
                    <span wire:loading.remove wire:target="update">{{ __('Save changes') }}</span>
                    <span wire:loading wire:target="update">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    @php
        $businessName = Auth::user()->business?->name ?? '';
        $customerPhoneDigits = preg_replace('/\D+/', '', $detail->phone->toE164());
        $customerEmail = $detail->email?->toString() ?? '';
        $customerFirstName = trim(strtok($detail->name, ' ')) ?: $detail->name;
        $shareMessageTemplate = __(
            "Hi :name, please upload the documents we need using this secure link:\n\n:url\n\nThe link expires in 60 minutes.\n\n— :business",
            ['name' => $customerFirstName, 'url' => '__URL__', 'business' => $businessName]
        );
        $emailSubject = __('Document upload request from :business', ['business' => $businessName]);
    @endphp

    <flux:modal name="upload-link" class="md:w-[34rem]">
        <div class="flex flex-col gap-5"
             x-data="{
                expiresAt: @js($generatedExpiresAtIso),
                remaining: '',
                customerPhone: @js($customerPhoneDigits),
                customerEmail: @js($customerEmail),
                emailSubject: @js($emailSubject),
                template: @js($shareMessageTemplate),
                get url() { return $wire.generatedUrl ?? ''; },
                get message() { return this.template.replace('__URL__', this.url); },
                get whatsappHref() {
                    if (! this.url) return '#';
                    const text = encodeURIComponent(this.message);
                    return this.customerPhone
                        ? `https://wa.me/${this.customerPhone}?text=${text}`
                        : `https://wa.me/?text=${text}`;
                },
                get emailHref() {
                    if (! this.url) return '#';
                    const subject = encodeURIComponent(this.emailSubject);
                    const body = encodeURIComponent(this.message);
                    return `mailto:${this.customerEmail}?subject=${subject}&body=${body}`;
                },
                copied: false,
                async copyLink() {
                    if (! this.url) return;
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(this.url);
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = this.url;
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
                },
                tick() {
                    if (!this.expiresAt) { this.remaining = ''; return; }
                    const ms = new Date(this.expiresAt).getTime() - Date.now();
                    if (ms <= 0) { this.remaining = '{{ __('Expired') }}'; return; }
                    const totalSec = Math.floor(ms / 1000);
                    const m = Math.floor(totalSec / 60);
                    const s = totalSec % 60;
                    this.remaining = `${m}m ${s.toString().padStart(2,'0')}s`;
                }
             }"
             x-init="tick(); const i = setInterval(() => tick(), 1000); $watch('$wire.generatedExpiresAtIso', v => { expiresAt = v; tick(); }); $cleanup(() => clearInterval(i))">

            {{-- Header --}}
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-950">
                    <flux:icon.check class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">{{ __('Link ready to share') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">
                        {{ __('Pick how you want to send this to :name. Expires in', ['name' => $customerFirstName]) }}
                        <span class="font-mono font-semibold" x-text="remaining">—</span>.
                    </flux:text>
                </div>
            </div>

            {{-- QR + share buttons --}}
            <div class="grid gap-4 sm:grid-cols-[140px_1fr] sm:items-center">
                @if ($generatedQrSvg)
                    <div class="flex justify-center rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white p-3">
                        <div class="size-[120px]">
                            {!! $generatedQrSvg !!}
                        </div>
                    </div>
                @endif

                <div class="flex flex-col gap-2">
                    @if ($customerPhoneDigits)
                        <a :href="whatsappHref"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-3 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-[#1ebe5b]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.198-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            {{ __('Send via WhatsApp') }}
                        </a>
                    @else
                        <a :href="whatsappHref"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-3 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-[#1ebe5b]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.198-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            {{ __('Open WhatsApp') }}
                        </a>
                    @endif

                    @if ($customerEmail)
                        <a :href="emailHref"
                           class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-700 dark:text-white dark:hover:bg-zinc-600/75">
                            <flux:icon.envelope class="size-4" />
                            {{ __('Send via Email') }}
                        </a>
                    @endif

                    <button type="button"
                            x-on:click="copyLink()"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-700 dark:text-white dark:hover:bg-zinc-600/75">
                        <flux:icon.clipboard class="size-4" />
                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy link') }}'">{{ __('Copy link') }}</span>
                    </button>
                </div>
            </div>

            {{-- Help footnote --}}
            <div class="text-xs text-zinc-500 leading-relaxed border-t border-zinc-100 dark:border-white/10 pt-3">
                @if ($customerPhoneDigits)
                    {{ __('WhatsApp will open with :name\'s number pre-filled and the message ready. They just hit send.', ['name' => $customerFirstName]) }}
                @else
                    {{ __('Add a phone number to this customer to send via WhatsApp directly.') }}
                @endif
                <br>
                {{ __('In person? Show the QR code — they scan with their phone camera.') }}
            </div>

            {{-- Raw link for fallback / preview --}}
            <details class="rounded-lg bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2 text-xs">
                <summary class="cursor-pointer text-zinc-600 dark:text-zinc-400 font-medium">{{ __('Show full link') }}</summary>
                <code class="mt-2 block break-all text-zinc-500">{{ $generatedUrl }}</code>
            </details>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <x-confirm-modal
        name="confirm-regenerate-link"
        :title="__('Regenerate upload link?')"
        :message="__('All active links for this customer will be revoked and a new one will be issued. Anyone holding the old link will lose access.')"
        :confirm-label="__('Revoke and regenerate')"
        tone="warning"
        icon="arrow-path"
        on-confirm="$wire.regenerateUploadLink()" />

    <x-confirm-modal
        name="confirm-revoke-session"
        :title="__('Revoke this upload link?')"
        :message="__('The customer will no longer be able to upload using this link. Files already uploaded are kept.')"
        :confirm-label="__('Revoke link')"
        tone="warning"
        icon="link-slash"
        on-confirm="$wire.revokeUploadLink(pendingSessionUlid)" />

    <x-confirm-modal
        name="confirm-delete-doc"
        :title="__('Delete this document?')"
        :confirm-label="__('Delete document')"
        tone="danger"
        icon="trash"
        on-confirm="$wire.deleteDocument(pendingDocId)">
        <flux:text class="mt-1">
            <span x-text="pendingDocName" class="font-medium"></span>
            {{ __('will be permanently removed and cannot be recovered.') }}
        </flux:text>
    </x-confirm-modal>
</section>
