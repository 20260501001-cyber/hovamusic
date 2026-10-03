@props([
    'name',
    'value',
    'title',
    'description' => null,
    'id' => null,
    'checked' => false,
])

@php
    $id ??= 'r-'.$name.'-'.$value;
    $isChecked = (string) old($name, $checked ? $value : null) === (string) $value;
@endphp

<div class="hm-choice">
    <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" class="hm-choice__input" @checked($isChecked) {{ $attributes }}>
    <label for="{{ $id }}" class="hm-choice__label">
        <span class="hm-choice__title">{{ $title }}</span>
        @if ($description)<span class="hm-choice__desc">{{ $description }}</span>@endif
    </label>
</div>
