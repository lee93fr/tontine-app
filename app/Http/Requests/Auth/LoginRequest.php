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
            // Tentative par email — on exige aussi que le compte soit actif
            $ok = Auth::attempt(
                ['email' => $identifier, 'password' => $this->input('password'), 'active' => true],
                $remember
            );
        } else {
            // Tentative par téléphone — on normalise les chiffres et on tente les deux variantes FR
            $user = $this->resolveUserByPhone($identifier);
            if ($user && $user->active && Hash::check($this->input('password'), $user->password)) {
                Auth::login($user, $remember);
                $ok = true;
            }
        }

        if (! $ok) {
            $this->hitRateLimit();
            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        $this->clearRateLimit();
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

        try {
            return User::query()->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    // On compare les chiffres seuls extraits côté PHP avec les colonnes phone/phone_number.
                    // preg_replace côté PHP génère $variants ; on cherche une correspondance exacte sur
                    // les valeurs brutes ou après suppression des séparateurs courants (espaces, tirets…).
                    $q->orWhereRaw("regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g') = ?", [$v])
                      ->orWhereRaw("regexp_replace(coalesce(phone_number, ''), '[^0-9]', '', 'g') = ?", [$v]);
                }
            })->first();
        } catch (\Throwable) {
            // Repli si regexp_replace ou une colonne n'est pas disponible sur cette version DB
            return User::query()->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('phone', $v)
                      ->orWhere('phone_number', $v);
                }
            })->first();
        }
    }

    public function ensureIsNotRateLimited(): void
    {
        try {
            if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
                return;
            }
        } catch (\Throwable) {
            // Si le cache est indisponible on laisse passer (fail open)
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

    private function hitRateLimit(): void
    {
        try {
            RateLimiter::hit($this->throttleKey());
        } catch (\Throwable) {
            // Cache indisponible — on ne bloque pas la réponse d'erreur de credentials
        }
    }

    private function clearRateLimit(): void
    {
        try {
            RateLimiter::clear($this->throttleKey());
        } catch (\Throwable) {
            // Cache indisponible — l'authentification a réussi, on continue
        }
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('identifier')).'|'.$this->ip());
    }
}
