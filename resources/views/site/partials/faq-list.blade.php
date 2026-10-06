<div class="hm-faq">
    @foreach ($faqs as $faq)
        <details class="hm-faq__item">
            <summary>
                <span>{{ $faq->question }}</span>
                <x-lucide-plus class="hm-icon" width="20" height="20" aria-hidden="true" />
            </summary>
            <div class="hm-faq__answer hm-prose">{!! $faq->html() !!}</div>
        </details>
    @endforeach
</div>
