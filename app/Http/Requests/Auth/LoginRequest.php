<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Keep the old email field working for API clients and existing forms.
        if (! $this->filled('loginname') && $this->filled('email')) {
            $this->merge(['loginname' => $this->input('email')]);
        }
    }

    public function rules(): array
    {
        return [
            'loginname' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where(function ($query) {
            $query->where('email', $this->loginname)
                ->orWhere('no_telp', $this->loginname)
                ->orWhere('username', $this->loginname);
        })->first();
        if (! $user || ! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'loginname' => trans('auth.failed'),
            ]);
        }
        // All successful logins use Laravel's remember-token cookie so users
        // remain authenticated across browser restarts.
        Auth::login($user, true);

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'loginname' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $identifier = $this->input('loginname') ?: $this->input('email');

        return Str::transliterate(Str::lower((string) $identifier).'|'.$this->ip());
    }
}
