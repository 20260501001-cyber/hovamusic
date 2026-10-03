@props([
    'rows' => 3,
    'label' => null,
])

<div {{ $attributes->class('hm-skel') }} role="status" aria-label="{{ $label ?? __('ui.loading') }}">
    @for ($i = 0; $i < $rows; $i++)
        <div class="hm-skel__row">
            <span class="hm-skel__block hm-skel__cover"></span>
            <span class="hm-skel__lines">
                <span class="hm-skel__block hm-skel__line" style="width: {{ 72 - $i * 9 }}%"></span>
                <span class="hm-skel__block hm-skel__line hm-skel__line--s" style="width: {{ 38 + $i * 7 }}%"></span>
            </span>
        </div>
    @endfor
</div>
