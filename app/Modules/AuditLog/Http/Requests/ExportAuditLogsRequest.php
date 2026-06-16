<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Http\Requests;

use App\Http\Requests\FormRequest;
use App\Modules\AuditLog\Enums\AuditAction;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

final class ExportAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->business_id !== null && $user->isBusinessAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'string', 'size:26'],
            'action' => ['nullable', 'string', 'in:'.implode(',', AuditAction::values())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    public function getBusinessId(): Id
    {
        return Id::fromString($this->user()->business->ulid);
    }

    public function getActorId(): ?Id
    {
        $actor = $this->query('actor');

        return is_string($actor) && $actor !== '' ? Id::fromString($actor) : null;
    }

    public function getAction(): ?string
    {
        $action = $this->query('action');

        return is_string($action) && $action !== '' ? $action : null;
    }

    public function getFrom(): ?CarbonImmutable
    {
        $from = $this->query('from');

        return is_string($from) && $from !== '' ? CarbonImmutable::parse($from)->startOfDay() : null;
    }

    public function getTo(): ?CarbonImmutable
    {
        $to = $this->query('to');

        return is_string($to) && $to !== '' ? CarbonImmutable::parse($to)->endOfDay() : null;
    }
}
