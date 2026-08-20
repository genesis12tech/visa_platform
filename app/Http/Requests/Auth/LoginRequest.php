<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Backend_schema.md §11.3: two independent rate limits — 5 attempts per
 * email per 15 minutes, 20 attempts per IP per 15 minutes — checked before
 * any credential evaluation happens.
 */
class LoginRequest extends FormRequest
{
    private const EMAIL_MAX_ATTEMPTS = 5;

    private const IP_MAX_ATTEMPTS = 20;

    private const DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->emailThrottleKey(), self::EMAIL_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::IP_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again later.',
            ]);
        }
    }

    public function hitRateLimiters(): void
    {
        RateLimiter::hit($this->emailThrottleKey(), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipThrottleKey(), self::DECAY_SECONDS);
    }

    public function clearRateLimiters(): void
    {
        RateLimiter::clear($this->emailThrottleKey());
        RateLimiter::clear($this->ipThrottleKey());
    }

    private function emailThrottleKey(): string
    {
        return 'login-email:'.Str::lower((string) $this->string('email'));
    }

    private function ipThrottleKey(): string
    {
        return 'login-ip:'.$this->ip();
    }
}
