<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
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

    /**
     * Le champ `identifier` accepte un email OU un numéro de téléphone.
     * On reste tolérant sur le format pour le téléphone (espaces, tirets, +33...).
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'min:3', 'max:255'],
            'password'   => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = (string) $this->input('identifier');
        $remember   = $this->boolean('remember');
        $ok = false;

        if (str_contains($identifier, '@')) {
            // Tentative par email
            $ok = Auth::attempt(['email' => $identifier, 'password' => $this->input('password')], $remember);
        } else {
            // Tentative par téléphone — on normalise les chiffres et on tente les deux variantes FR
            $user = $this->resolveUserByPhone($identifier);
            if ($user && Hash::check($this->input('password'), $user->password)) {
                Auth::login($user, $remember);
                $ok = true;
            }
        }

        if (! $ok) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function resolveUserByPhone(string $input): ?User
    {
        $digits = preg_replace('/\D/', '', $input);
        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        // Variantes : si saisi en 0xxxxxxxxx (FR), on cherche aussi 33xxxxxxxxx et vice-versa
        $variants = [$digits];
        if (str_starts_with($digits, '33') && strlen($digits) === 11) {
            $variants[] = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $variants[] = '33' . substr($digits, 1);
        }

        return User::query()->where(function ($q) use ($variants) {
            foreach ($variants as $v) {
                $q->orWhereRaw("regexp_replace(coalesce(phone, ''), '\\D', '', 'g') = ?", [$v]);
                $q->orWhereRaw("regexp_replace(coalesce(phone_number, ''), '\\D', '', 'g') = ?", [$v]);
            }
        })->first();
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('identifier')).'|'.$this->ip());
    }
}
