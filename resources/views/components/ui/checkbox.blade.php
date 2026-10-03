@props([
    'name',
    'id' => null,
    'description' => null,
    'value' => '1',
    'checked' => false,
    'bag' => 'default',
])

@php
    $id ??= 'c-'.str_replace(['.', '[', ']'], '-', $name);
    $error = $errors->getBag($bag)->first(rtrim(str_replace(['[', ']'], ['.', ''], $name), '.'));
    $isChecked = old(rtrim(str_replace(['[', ']'], ['.', ''], $name), '.'), $checked);
@endphp

<div class="hm-check">
    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" class="hm-check__input"
        @checked($isChecked)
        @if ($description || $error) aria-describedby="{{ collect([$description ? $id.'-desc' : null, $error ? $id.'-error' : null])->filter()->implode(' ') }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes }}>
    <label for="{{ $id }}" class="hm-check__label">
        <span class="hm-check__box" aria-hidden="true"><x-lucide-check class="hm-icon" width="14" height="14" stroke-width="2.5" /></span>
        <span class="hm-check__text">
            <span class="hm-check__title">{{ $slot }}</span>
            @if ($description)<span id="{{ $id }}-desc" class="hm-check__desc">{{ $description }}</span>@endif
        </span>
    </label>
    @if ($error)
        <p id="{{ $id }}-error" class="hm-field__error hm-check__error">
            <x-lucide-circle-x class="hm-icon" width="16" height="16" aria-hidden="true" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
