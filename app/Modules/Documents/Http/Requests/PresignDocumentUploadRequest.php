<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Requests;

use App\Http\Requests\FormRequest;

final class PresignDocumentUploadRequest extends FormRequest
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
            'filename' => ['required', 'string', 'min:1', 'max:255'],
            'mime' => ['required', 'string', 'max:128'],
            'size' => ['required', 'integer', 'min:1'],
        ];
    }
}
