<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Requests;

use App\Http\Requests\FormRequest;
use Shared\ValueObjects\Id;

final class DownloadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->business_id !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function getBusinessId(): Id
    {
        return Id::fromString($this->user()->business->ulid);
    }

    public function getActorId(): Id
    {
        return Id::fromString($this->user()->ulid);
    }
}
