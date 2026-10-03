@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'id' => null,
    'help' => null,
    'bag' => 'default',
])

@php
    $id ??= 's-'.$name;
    $error = $errors->getBag($bag)->first($name);
    $current = (string) old($name, $selected);
@endphp

<div class="hm-field">
    <label for="{{ $id }}" class="hm-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="hm-select"
        @if ($error) aria-invalid="true" @endif
        @if ($help || $error) aria-describedby="{{ collect([$help ? $id.'-help' : null, $error ? $id.'-error' : null])->filter()->implode(' ') }}" @endif
        {{ $attributes }}>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected($current === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($help)<p id="{{ $id }}-help" class="hm-field__help">{{ $help }}</p>@endif
    @if ($error)
        <p id="{{ $id }}-error" class="hm-field__error"><x-lucide-circle-x class="hm-icon" width="16" height="16" aria-hidden="true" /><span>{{ $error }}</span></p>
    @endif
</div>
