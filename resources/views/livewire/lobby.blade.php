<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Results;
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
            ->with(['seats' => fn ($seats) => $seats->whereIn('seat', ['white', 'black'])->with('delegation')])
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

    /**
     * Who is actually at each open table, asked of the hub.
     *
     * Kept apart from the games themselves because they answer different
     * questions. A seat is the venue's record that somebody may play and
     * outlives them closing the tab — it has to, or an opponent could take
     * their chair while they reconnected. This is the room right now.
     *
     * @return array<string, array<int, array{name: string, seat: string}>>
     */
    public function present(): array
    {
        return app(Results::class)->at($this->open());
    }

    /**
     * Who is playing, by the names their own servers gave them.
     *
     * From the seats rather than from the room: this is who the game belongs
     * to, and it stays true while somebody is reconnecting. Who is *there* is a
     * separate line, and a separate question.
     *
     * @return array<string, string> handle by seat
     */
    public function players(Gathering $game): array
    {
        $players = [];

        foreach ($game->seats as $seat) {
            $players[$seat->seat] = (string) ($seat->delegation?->handle ?? '');
        }

        return $players;
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
{{--
    Polled, because one line on this screen is live.

    Who is at a table comes from the hub and changes without anybody here doing
    anything. Rendered once it would be a snapshot that looks like a status —
    two people could be sitting in the same room reading that nobody was there.

    Half a minute, because every open lobby asks the hub each time. Somebody
    reading a list of games is deciding which one to join, not watching it; the
    board is where things need to be immediate.
--}}
<div class="flex flex-col gap-6" wire:poll.30s>
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Chess') }}</flux:heading>

        <flux:button wire:click="start" variant="primary" icon="plus">
            {{ __('Start a game') }}
        </flux:button>
    </div>

    @php($present = $this->present())

    @forelse ($this->open() as $game)
        @php($here = $present[$game->room()] ?? null)

        <flux:card class="flex items-center justify-between gap-4">
            <div class="flex flex-col gap-1">
                {{--
                    Three lines, answering three different questions: which
                    table this is, whose game it is, and whether anybody is
                    there.

                    The key names the table and never changes, which is what
                    makes it worth keeping — it is how you tell two games
                    between the same two people apart, and what somebody would
                    read back to you.
                --}}
                @php($players = $this->players($game))

                <flux:heading>{{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}</flux:heading>

                @if (($players['white'] ?? '') !== '' || ($players['black'] ?? '') !== '')
                    <flux:text class="text-sm">
                        @if (($players['white'] ?? '') !== '' && ($players['black'] ?? '') !== '')
                            {{ $players['white'] }} {{ __('vs') }} {{ $players['black'] }}
                        @else
                            {{ __(':player is waiting for an opponent', [
                                'player' => $players['white'] ?: $players['black'],
                            ]) }}
                        @endif
                    </flux:text>
                @endif

                {{--
                    Who is there, not who has ever been there.

                    The seat count is the venue's record and outlives everybody
                    leaving — a table nobody has opened in a week still had two
                    people sit down at it once. What a person reading this wants
                    to know is whether anybody is there now.

                    Nothing at all if the hub did not answer. A number this
                    server cannot stand behind is worse than no number.
                --}}
                @if ($here !== null)
                    <flux:text class="text-sm">
                        @php($playing = collect($here)->filter(fn ($who) => $who['seat'] !== '')->count())
                        @php($watching = count($here) - $playing)

                        @if ($here === [])
                            {{ __('Nobody here right now') }}
                        @else
                            {{ trans_choice('{1}one playing|[2,*]:count playing', $playing, ['count' => $playing]) }}
                            @if ($watching > 0)
                                · {{ trans_choice('{1}one watching|[2,*]:count watching', $watching, ['count' => $watching]) }}
                            @endif
                        @endif
                    </flux:text>
                @endif
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
