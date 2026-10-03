@props([
    'name',
    'label',
    'id' => null,
    'type' => 'text',
    'value' => null,
    'help' => null,
    'bag' => 'default',
    'mono' => false,
    'optional' => false,
    'suffix' => null,
    'multiline' => false,
    'rows' => 4,
])

@php
    $id ??= 'f-'.str_replace(['.', '[', ']'], '-', $name);
    $error = $errors->getBag($bag)->first($name);
    $describedBy = collect([$help ? $id.'-help' : null, $error ? $id.'-error' : null])->filter()->implode(' ') ?: null;
    $inputClasses = collect(['hm-input', $mono ? 'hm-input--mono' : null, $multiline ? 'hm-input--area' : null, $error ? 'is-invalid' : null])->filter()->implode(' ');
    $current = $type === 'password' ? null : old($name, $value);
@endphp

<div class="hm-field">
    <label for="{{ $id }}" class="hm-label">
        {{ $label }}@if ($optional)<span class="hm-label__optional"> · {{ __('ui.optional') }}</span>@endif
    </label>

    @if ($multiline)
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" class="{{ $inputClasses }}"
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes }}>{{ $current }}</textarea>
    @elseif ($suffix)
        <div class="hm-input-wrap">
            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" class="{{ $inputClasses }}"
                @if ($error) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $attributes }}>
            <span class="hm-input-suffix">{{ $suffix }}</span>
        </div>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($current !== null) value="{{ $current }}" @endif class="{{ $inputClasses }}"
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes }}>
    @endif

    @if ($help)
        <p id="{{ $id }}-help" class="hm-field__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="hm-field__error">
            <x-lucide-circle-x class="hm-icon" width="16" height="16" aria-hidden="true" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
