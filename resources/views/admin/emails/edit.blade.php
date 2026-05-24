@extends('layouts.app')

@section('title', __('app.emails.edit_title'))

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.index', ['tab' => 'emails']) }}" class="text-gray-400 hover:text-gray-600">
            ← {{ __('app.action.back') }}
        </a>
        <h1 class="text-2xl font-bold text-gray-900">
            {{ __('app.emails.edit_title') }} — {{ __('app.emails.key_' . $key) }}
            <span class="ml-2 text-sm font-normal bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">{{ strtoupper($locale) }}</span>
        </h1>
    </div>

    <form method="POST" action="{{ route('admin.emails.update', [$key, $locale]) }}" class="space-y-6">
        @csrf
        @method('PATCH')

        {{-- Subject --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <h2 class="font-semibold text-gray-700">{{ __('app.emails.field_subject') }}</h2>
            <input type="text" name="subject"
                   value="{{ old('subject', $template?->subject) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                   required>
            @error('subject')
                <p class="text-red-600 text-sm">{{ $message }}</p>
            @enderror
        </div>

        {{-- Body --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <h2 class="font-semibold text-gray-700">{{ __('app.emails.field_body') }}</h2>
            <p class="text-xs text-gray-500">{{ __('app.emails.body_hint') }}</p>
            <textarea name="body" rows="14"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                      required>{{ old('body', $template?->body) }}</textarea>
            @error('body')
                <p class="text-red-600 text-sm">{{ $message }}</p>
            @enderror
        </div>

        {{-- Variables legend --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 space-y-3">
            <h2 class="font-semibold text-amber-800">{{ __('app.emails.vars_legend') }}</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($vars as $var)
                    <code class="bg-white border border-amber-300 text-amber-900 text-xs px-2 py-1 rounded font-mono cursor-pointer select-all"
                          title="{{ __('app.emails.click_to_copy') }}"
                          onclick="navigator.clipboard.writeText('{{ $var }}')">{{ $var }}</code>
                @endforeach
            </div>
            <p class="text-xs text-amber-700">{{ __('app.emails.vars_note') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition">
                {{ __('app.action.save') }}
            </button>
            <a href="{{ route('admin.settings.index', ['tab' => 'emails']) }}"
               class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">
                {{ __('app.action.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
