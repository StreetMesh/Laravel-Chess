<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Visitors;

new #[Title('Chess')] class extends Component
{
    /**
     * Games anybody could join, most recent first.
     *
     * @return \Illuminate\Support\Collection<int, Gathering>
     */
    public function open(): \Illuminate\Support\Collection
    {
        return Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->where('status', Gathering::OPEN)
            ->withCount('seats')
            ->latest()
            ->get();
    }

    /**
     * What has happened here, most recent first.
     *
     * From what the venue kept when each game concluded. The hub forgot every
     * one of these the moment its last player left, so this is the only place
     * left that knows a game was ever played.
     *
     * @return \Illuminate\Support\Collection<int, Gathering>
     */
    public function finished(): \Illuminate\Support\Collection
    {
        return Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->where('status', Gathering::CONCLUDED)
            ->latest('concluded_at')
            ->limit(8)
            ->get();
    }

    public function start(): void
    {
        $visitor = app(Visitors::class)->current(request());

        if ($visitor === null) {
            return;
        }

        $game = app(Games::class)->open($visitor);

        $this->redirectRoute('chess.table', $game->key, navigate: true);
    }

    public function sit(string $key): void
    {
        $visitor = app(Visitors::class)->current(request());
        $game = Gathering::query()->where('key', $key)->first();

        if ($visitor === null || $game === null) {
            return;
        }

        app(Games::class)->join($game, $visitor);

        $this->redirectRoute('chess.table', $game->key, navigate: true);
    }
};?>

{{--
    No padding of its own. The host's layout already pads the main area — Flux
    applies `p-6 lg:p-8` to it — and a screen that pads again is a screen with
    twice the margins of every other one, which is exactly how this looked.
--}}
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Chess') }}</flux:heading>

        <flux:button wire:click="start" variant="primary" icon="plus">
            {{ __('Start a game') }}
        </flux:button>
    </div>

    @forelse ($this->open() as $game)
        <flux:card class="flex items-center justify-between gap-4">
            <div class="flex flex-col gap-1">
                <flux:heading>{{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}</flux:heading>
                <flux:text class="text-sm">
                    {{ trans_choice('{1}one player waiting|[2,*]:count at the table', $game->seats_count, ['count' => $game->seats_count]) }}
                </flux:text>
            </div>

            {{--
                What the button offers is what you would actually get. Both
                chairs taken means watching, and saying "Play" there would be a
                promise the venue is about to break.

                Still the venue's answer either way — this only reads the same
                thing the venue is about to decide, and being wrong about it
                costs nothing but the word.
            --}}
            <flux:button wire:click="sit('{{ $game->key }}')" variant="outline">
                {{ $game->seats_count >= 2 ? __('Watch') : __('Play') }}
            </flux:button>
        </flux:card>
    @empty
        <flux:callout icon="squares-2x2">
            <flux:callout.heading>{{ __('Nothing in progress') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Start one, and send somebody the link. They do not need an account here — they arrive with the address they already use.') }}
            </flux:callout.text>
        </flux:callout>
    @endforelse

    {{--
        What has happened here.

        Only the venue knows: the hub forgot each of these when its last player
        left, and the record that counts is on the servers the players live on
        rather than this one.
    --}}
    @if ($this->finished()->isNotEmpty())
        <div class="mt-2 flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Finished') }}</flux:heading>

            @foreach ($this->finished() as $game)
                @php($outcome = $game->outcome ?? [])

                <flux:card
                    :href="route('chess.table', $game->key)"
                    wire:navigate
                    class="flex items-center justify-between gap-4"
                >
                    <flux:text class="text-sm">
                        {{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}
                    </flux:text>

                    <flux:badge size="sm" :color="($outcome['winner'] ?? '') !== '' ? 'emerald' : 'zinc'">
                        @if (($outcome['winner'] ?? '') !== '')
                            {{ __(':winner won', ['winner' => ucfirst((string) $outcome['winner'])]) }}
                        @else
                            {{ __('Drawn') }}
                        @endif
                        @if (($outcome['outcome'] ?? '') !== '')
                            — {{ $outcome['outcome'] }}
                        @endif
                    </flux:badge>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
