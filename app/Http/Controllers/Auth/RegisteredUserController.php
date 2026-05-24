<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['iban' => strtoupper(preg_replace('/\s+/', '', $request->input('iban') ?? ''))]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:34', function ($a, $v, $fail) {
                if (empty($v) || strtoupper(substr($v, 0, 2)) !== 'FR') {
                    return;
                }
                if (strlen($v) !== 27) {
                    $fail(__('app.rib.iban_invalid'));

                    return;
                }
                $r = substr($v, 4).substr($v, 0, 4);
                $n = implode('', array_map(fn ($c) => ctype_alpha($c) ? (string) (ord($c) - 55) : $c, str_split($r)));
                if (bcmod($n, '97') !== '1') {
                    $fail(__('app.rib.iban_invalid'));
                }
            }],
            'bic' => ['nullable', 'string', 'max:11'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'first_name' => $request->first_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'postal_code' => $request->postal_code,
            'city' => $request->city,
            'iban' => $request->iban,
            'bic' => $request->bic,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
