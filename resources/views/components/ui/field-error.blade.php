@props(['id', 'message' => null])

@if ($message)
    <p id="{{ $id }}" {{ $attributes->class('hm-field__error') }}>
        <x-lucide-circle-x class="hm-icon" width="16" height="16" aria-hidden="true" />
        <span>{{ $message }}</span>
    </p>
@endif
