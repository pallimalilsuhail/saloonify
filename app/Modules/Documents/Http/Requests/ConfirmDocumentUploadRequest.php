<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Requests;

use App\Http\Requests\FormRequest;

final class ConfirmDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['string'],
        ];
    }
}
