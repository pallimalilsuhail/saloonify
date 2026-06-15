<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;

final class OnboardBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $login = (string) $this->input('admin.login');
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'trn' => ['required', 'digits:15'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.login' => $isEmail
                ? ['required', 'email', 'max:255', Rule::unique('users', 'email')]
                : ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'admin.password' => ['required', 'string', 'min:8'],
        ];
    }

    public function name(): string
    {
        return $this->asString('name');
    }

    public function trn(): string
    {
        return $this->asString('trn');
    }

    public function adminName(): string
    {
        return $this->asString('admin.name');
    }

    public function login(): string
    {
        return $this->asString('admin.login');
    }

    public function password(): string
    {
        return $this->asString('admin.password');
    }
}
