<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    #[Validate('required|string')]
    public string $login = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        $input = trim($this->login);
        $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL) !== false;

        $user = $isEmail
            ? User::query()->where('email', $input)->first()
            : User::query()->where('username', $input)->first();

        $terminated = $user !== null && $user->status === UserStatus::Terminated;

        if ($user === null || $terminated || ! Auth::attempt(
            ['email' => $user->email, 'password' => $this->password],
            $this->remember,
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col items-center gap-2 text-center">
        <flux:heading size="lg">{{ __('Log in to your account') }}</flux:heading>
        <flux:text>{{ __('Enter your email or username and password below to log in') }}</flux:text>
    </div>

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input
            wire:model="login"
            :label="__('Email or username')"
            type="text"
            required
            autofocus
            autocomplete="username"
            :placeholder="__('email@example.com')"
        />

        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="current-password"
            :placeholder="__('Password')"
            viewable
        />

        <flux:checkbox wire:model="remember" :label="__('Remember me')" />

        <flux:button variant="primary" type="submit" class="w-full">
            {{ __('Log in') }}
        </flux:button>
    </form>
</div>
