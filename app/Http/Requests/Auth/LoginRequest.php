<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 3;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Named "email" for password managers; it takes an email or a username.
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('email'));
        $field = str_contains($login, '@') ? 'email' : 'username';
        $credentials = [$field => $field === 'username' ? Str::lower($login) : $login, 'password' => $this->string('password')->toString()];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            if (RateLimiter::remaining($this->throttleKey(), self::MAX_ATTEMPTS) === 0) {
                $this->ensureIsNotRateLimited();
            }

            // An invited account has no password of its own yet, so say where to get one.
            $pending = User::where($field, $credentials[$field])->whereNull('password_set_at')->exists();

            throw ValidationException::withMessages([
                'email' => $pending
                    ? 'This account isn\'t set up yet. Open the invite in your email to choose a password, or ask the owner to send it again.'
                    : trans('auth.failed').' '.trans_choice(':count attempt left.|:count attempts left.', RateLimiter::remaining($this->throttleKey(), self::MAX_ATTEMPTS)),
            ]);
        }

        if (Auth::user()->isDeactivated()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'This account was archived. Ask the owner if you need access again.',
            ]);
        }

        if (! Auth::user()->isAdminOrAbove() && \App\Services\RemoteAccessService::isRemoteRequest($this)) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['email' => \App\Services\RemoteAccessService::STAFF_MESSAGE]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        // The login page counts this down live.
        session()->flash('login_locked_until', now()->addSeconds($seconds)->getTimestamp());

        throw ValidationException::withMessages([
            'email' => "Too many wrong attempts. You can try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
