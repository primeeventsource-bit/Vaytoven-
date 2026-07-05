@php
    // $spec = [type, label, rules, options?, placeholder?]
    [$type, $label] = $spec;
    $options = $spec[3] ?? [];
    $placeholder = $spec[4] ?? '';
    $inputId = $idPrefix.'-'.$field;
@endphp

@if ($type === 'textarea')
    <div class="full">
        <label for="{{ $inputId }}">{{ $label }}</label>
        <textarea id="{{ $inputId }}" name="{{ $field }}" rows="3" placeholder="{{ $placeholder }}">{{ $value }}</textarea>
    </div>
@elseif ($type === 'json')
    <div class="full">
        <label for="{{ $inputId }}">{{ $label }} (JSON)</label>
        <textarea id="{{ $inputId }}" name="{{ $field }}" rows="3" spellcheck="false"
                  placeholder="{{ $placeholder }}">{{ is_string($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_SLASHES) }}</textarea>
    </div>
@elseif ($type === 'bool')
    <div>
        <label>{{ $label }}</label>
        <input type="hidden" name="{{ $field }}" value="0">
        <label class="vyt-switch">
            <input type="checkbox" id="{{ $inputId }}" name="{{ $field }}" value="1" @checked((bool) $value)>
            <span class="vyt-slider"></span>
        </label>
    </div>
@elseif ($type === 'select')
    <div>
        <label for="{{ $inputId }}">{{ $label }}</label>
        <select id="{{ $inputId }}" name="{{ $field }}">
            @foreach ($options as $option)
                <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
            @endforeach
        </select>
    </div>
@elseif ($type === 'number' || $type === 'cents')
    <div>
        <label for="{{ $inputId }}">{{ $label }}{{ $type === 'cents' ? ' (cents)' : '' }}</label>
        <input type="number" step="1" id="{{ $inputId }}" name="{{ $field }}" value="{{ $value }}" placeholder="{{ $placeholder }}">
    </div>
@else
    <div>
        <label for="{{ $inputId }}">{{ $label }}</label>
        <input type="text" id="{{ $inputId }}" name="{{ $field }}" value="{{ $value }}" placeholder="{{ $placeholder }}">
    </div>
@endif

@error($field)
    <div class="vyt-seterror full">{{ $message }}</div>
@enderror
