@php
    $messages = [
        'profile-information-updated' => __('panel.flash.profile_updated'),
        'password-updated' => __('panel.flash.password_updated'),
        'preferences-updated' => __('panel.flash.preferences_updated'),
        'two-factor-authentication-disabled' => __('panel.flash.two_factor_disabled'),
        'two-factor-authentication-confirmed' => __('panel.flash.two_factor_confirmed'),
        'recovery-codes-generated' => __('panel.flash.recovery_codes_generated'),
        'verification-link-sent' => __('auth.verify.link_sent'),
    ];
    $message = $messages[session('status')] ?? null;
@endphp

@if ($message)
    <x-ui.alert tone="success" :title="$message" />
@endif
