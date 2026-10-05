@props([
    'title' => null,
    'description' => null,
    'noindex' => false,
    'theme' => 'dark',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($theme !== 'system') data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · Hova Music' : 'Hova Music' }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
    <meta name="theme-color" content="#0A0A0C">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body {{ $attributes }}>
    {{ $slot }}
    <x-cookie-banner />
    @stack('scripts')
</body>
</html>
