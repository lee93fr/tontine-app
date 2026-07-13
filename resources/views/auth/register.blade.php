<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.auth.register_title') }} — TontineApp</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen py-8">
<div class="max-w-xl mx-auto px-4">
    <div class="bg-white rounded-2xl shadow-xl p-8">

        <div class="text-center mb-8">
            <img src="{{ asset('favicon.svg') }}" alt="TontineApp" class="w-16 h-16 mx-auto mb-3 rounded-lg shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">TontineApp</h1>
            <p class="text-gray-500 mt-1 text-sm">{{ __('app.auth.register_subtitle') }}</p>
        </div>

        <div class="flex justify-center gap-2 mb-6">
            <a href="{{ route('lang.switch', 'fr') }}"
               class="text-xs px-3 py-1 rounded-full border font-medium transition
                   {{ app()->getLocale() === 'fr' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-300 hover:border-indigo-400' }}">FR</a>
            <a href="{{ route('lang.switch', 'en') }}"
               class="text-xs px-3 py-1 rounded-full border font-medium transition
                   {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-300 hover:border-indigo-400' }}">EN</a>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            {{-- Informations personnelles --}}
            <h2 class="text-sm font-semibold text-gray-700">{{ __('app.participant.personal_info') }}</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('app.common.first_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                                  @error('first_name') border-red-400 @enderror">
                    @error('first_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('app.profile.last_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                                  @error('name') border-red-400 @enderror">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('app.profile.phone') }} <span class="text-red-500">*</span>
                </label>
                <input type="text" name="phone" inputmode="tel" required
                       value="{{ old('phone') ? trim(preg_replace('/(\d{2})(?=\d)/', '$1 ', preg_replace('/\D/', '', old('phone')))) : '' }}"
                       oninput="const d=this.value.replace(/\D/g,'').slice(0,10);this.value=d.replace(/(\d{2})(?=\d)/g,'$1 ').trim()"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                              @error('phone') border-red-400 @enderror"
                       placeholder="06 12 34 56 78">
                @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                              @error('email') border-red-400 @enderror">
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Adresse postale --}}
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('app.address.section') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('app.address.address') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="address" value="{{ old('address') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                               placeholder="12 rue de la Paix">
                        @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('app.address.postal_code') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="postal_code" value="{{ old('postal_code') }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                                   placeholder="75001">
                            @error('postal_code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('app.address.city') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="city" value="{{ old('city') }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                                   placeholder="Paris">
                            @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIB --}}
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('app.rib.section') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.rib.iban') }}</label>
                        <input type="text" name="iban"
                               value="{{ old('iban') ? trim(preg_replace('/(.{4})(?=.)/', '$1 ', strtoupper(preg_replace('/\s+/', '', old('iban'))))) : '' }}"
                               oninput="formatIban(this)" data-iban-error="{{ __('app.rib.iban_invalid') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                                      @error('iban') border-red-400 @enderror"
                               placeholder="FR76 3000 6000 0112 3456 7890 189" maxlength="42">
                        <p class="iban-err text-red-500 text-xs mt-1 hidden"></p>
                        @error('iban')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.rib.bic') }}</label>
                        <input type="text" name="bic" value="{{ old('bic') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                                      @error('bic') border-red-400 @enderror"
                               placeholder="{{ __('app.rib.bic_hint') }}" maxlength="11">
                        @error('bic')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Mot de passe --}}
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('app.profile.change_password') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.password') }}</label>
                        <input type="password" name="password" required autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none
                                      @error('password') border-red-400 @enderror">
                        @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.confirm_password') }}</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                    </div>
                    <p class="text-xs text-gray-400">{{ __('app.profile.password_hint') }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                    {{ __('app.auth.already_registered') }}
                </a>
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                    {{ __('app.auth.register_btn') }}
                </button>
            </div>
        </form>

    </div>
</div>
<script>
function formatIban(el) {
    const raw = el.value.replace(/\s/g,'').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,34);
    el.value = raw.replace(/(.{4})(?=.)/g,'$1 ');
    const errEl = el.parentElement.querySelector('.iban-err');
    if (raw.startsWith('FR') && raw.length === 27) {
        const s = raw.slice(4) + raw.slice(0,4);
        const n = s.split('').map(c => /[A-Z]/.test(c) ? String(c.charCodeAt(0)-55) : c).join('');
        let m = 0; for (const d of n) m = (m*10+parseInt(d)) % 97;
        const msg = m === 1 ? '' : (el.dataset.ibanError || 'IBAN invalide');
        el.setCustomValidity(msg);
        if (errEl) { errEl.textContent = msg; errEl.classList.toggle('hidden', !msg); }
    } else {
        el.setCustomValidity('');
        if (errEl) errEl.classList.add('hidden');
    }
}
</script>
</body>
</html>
