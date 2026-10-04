<x-layouts.panel :title="__('notifications.center.title')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="hm-page-head">
            <h1 class="hm-h1">{{ __('notifications.center.title') }}</h1>
            @if ($unread > 0)
                <p class="m-0 text-ink-muted">{{ __('notifications.center.unread', ['count' => $unread]) }}</p>
            @endif
        </div>
        @if ($unread > 0)
            <form method="POST" action="{{ route('panel.notifications.read-all') }}">
                @csrf
                <x-ui.button type="submit" icon="check-check">{{ __('notifications.center.mark_all') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <x-ui.empty-state :title="__('notifications.center.empty_title')" icon="bell">
            {{ __('notifications.center.empty_body') }}
        </x-ui.empty-state>
    @else
        <ul class="m-0 list-none overflow-hidden rounded-md border border-line border-b-0 p-0">
            @foreach ($notifications as $notification)
                @php
                    $isUnread = $notification->read_at === null;
                    $at = $notification->created_at->timezone(config('hova.display_timezone'));
                @endphp
                <li @class(['flex flex-wrap items-start gap-3 border-b border-line p-4', 'bg-accent-soft' => $isUnread])>
                    <span class="mt-1.5 flex-none" aria-hidden="true">
                        <span @class(['block size-2 rounded-full', 'bg-accent' => $isUnread, 'bg-transparent' => ! $isUnread])></span>
                    </span>
                    <div class="grid min-w-0 flex-1 gap-1">
                        <a href="{{ route('panel.notifications.open', $notification->id) }}" @class(['hm-link', 'font-semibold' => $isUnread])>
                            @if ($isUnread)<span class="hm-sr">{{ __('notifications.center.new') }}: </span>@endif{{ $notification->data['title'] ?? __('notifications.center.title') }}
                        </a>
                        @if (filled($notification->data['body'] ?? null))
                            <p class="m-0 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit($notification->data['body'], 240) }}</p>
                        @endif
                        <time class="text-sm text-ink-subtle" datetime="{{ $notification->created_at->toIso8601String() }}">{{ \App\Support\Format::longDate($at) }} {{ $at->format('H:i') }}</time>
                    </div>
                    @if ($isUnread)
                        <form method="POST" action="{{ route('panel.notifications.read', $notification->id) }}">
                            @csrf
                            <x-ui.button type="submit" size="s" variant="ghost" icon="check">{{ __('notifications.center.mark_read') }}</x-ui.button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        {{ $notifications->links() }}
    @endif
</x-layouts.panel>
