@if(!empty($templates))
    <select name="{{ $name }}"
            class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm bg-white">
        <option value="">{{ $emptyLabel }}</option>
        @foreach($templates as $tpl)
            <option value="{{ $tpl['id'] }}" @selected($currentValue == $tpl['id'])>
                #{{ $tpl['id'] }} — {{ $tpl['name'] }}
            </option>
        @endforeach
    </select>
@else
    <input type="number" name="{{ $name }}" min="1" value="{{ $currentValue }}"
           placeholder="ID DocuSeal"
           class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm bg-white">
@endif
