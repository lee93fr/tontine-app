@extends('layouts.app')

@section('title', __('app.emails.title'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.emails.title') }}</h1>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('app.emails.col_template') }}</th>
                    @foreach($locales as $locale)
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ strtoupper($locale) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($templates as $key => $byLocale)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 sm:px-6 py-4">
                            <p class="font-medium text-gray-900 text-sm">{{ __('app.emails.key_' . $key) }}</p>
                            <p class="text-xs text-gray-400 font-mono mt-0.5 hidden sm:block">{{ $key }}</p>
                        </td>
                        @foreach($locales as $locale)
                            <td class="px-3 py-4 text-center">
                                <a href="{{ route('admin.emails.edit', [$key, $locale]) }}"
                                   class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-sm font-medium transition
                                       {{ $byLocale[$locale] ? 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                    @if($byLocale[$locale])
                                        <span>✏️</span><span class="hidden sm:inline">{{ __('app.action.edit') }}</span>
                                    @else
                                        <span>➕</span><span class="hidden sm:inline">{{ __('app.action.create') }}</span>
                                    @endif
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-sm text-gray-500">{{ __('app.emails.hint_vars') }}</p>
</div>
@endsection
