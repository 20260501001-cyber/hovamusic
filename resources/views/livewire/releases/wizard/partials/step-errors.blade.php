{{-- "Devam et"e basıldığında görünür hataların özeti; odak buraya taşınır. --}}
@if ($summary !== [])
    <div x-ref="summary" tabindex="-1" class="outline-none">
        <x-ui.alert tone="danger" :title="__('release.wizard.step_errors')">
            <ul class="m-0 grid gap-1 pl-5">
                @foreach ($summary as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    </div>
@endif
