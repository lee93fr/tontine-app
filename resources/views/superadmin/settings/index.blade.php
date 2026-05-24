@extends('layouts.app')
@section('title', 'Paramètres applicatifs')

@php
    $tabs = [];
    if ($isSuper) {
        $tabs['sms']       = ['label' => 'SMS',       'icon' => '📱'];
        $tabs['smtp']      = ['label' => 'SMTP',      'icon' => '✉️'];
        $tabs['signature'] = ['label' => 'Signature', 'icon' => '✍️'];
    }
    $tabs['emails'] = ['label' => 'Modèles email', 'icon' => '📨'];
@endphp

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Paramètres applicatifs</h1>
        <p class="text-gray-500 mt-1">
            @if($isSuper)
                Configurez SMS (Brevo), SMTP et les modèles d'emails transactionnels.
            @else
                Personnalisez les modèles d'emails transactionnels.
            @endif
        </p>
    </div>

    @if($isSuper && ! $tableReady)
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-3 text-sm">
        <p class="font-semibold mb-1">⚠️ Migration non appliquée</p>
        <p>La table <code class="bg-amber-100 px-1 rounded">app_settings</code> n'existe pas encore.
        Les valeurs affichées proviennent du <code class="bg-amber-100 px-1 rounded">.env</code>.
        L'enregistrement échouera tant que <code class="bg-amber-100 px-1 rounded">php artisan migrate</code> n'a pas été lancée.</p>
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3">
        <ul class="list-disc list-inside text-sm space-y-0.5">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Onglets --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Tabs">
            @foreach($tabs as $key => $info)
                @php $active = $tab === $key; @endphp
                <a href="{{ route('admin.settings.index', ['tab' => $key]) }}"
                   class="whitespace-nowrap inline-flex items-center gap-2 py-3 px-4 border-b-2 text-sm font-medium transition
                          {{ $active ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <span>{{ $info['icon'] }}</span>
                    <span>{{ $info['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ================= ONGLET SMS ================= --}}
    @if($tab === 'sms' && $isSuper)
        @include('superadmin.settings._tab_sms', compact('sms'))
    @endif

    {{-- ================= ONGLET SMTP ================= --}}
    @if($tab === 'smtp' && $isSuper)
        @include('superadmin.settings._tab_smtp', ['smtp' => $smtp, 'mail' => $mail])
    @endif

    {{-- ================= ONGLET SIGNATURE ================= --}}
    @if($tab === 'signature' && $isSuper)
        @include('superadmin.settings._tab_signature', compact('docuseal'))
    @endif

    {{-- ================= ONGLET MODÈLES EMAIL ================= --}}
    @if($tab === 'emails')
        @include('superadmin.settings._tab_emails', ['emailGroups' => $emailGroups])
    @endif

</div>
@endsection
