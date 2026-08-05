<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Gatherings;
use StreetMesh\Venue\Gatherings\Seat;
use StreetMesh\Venue\Visitors;

new #[Title('Chess')] class extends Component
{
    public string $key = '';

    public function mount(string $key): void
    {
        $this->key = $key;
    }

    public function game(): ?Gathering
    {
        return Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->where('key', $this->key)
            ->first();
    }

    /**
     * Which chair this visitor is in, or empty if they are watching.
     */
    public function seat(): string
    {
        return (string) ($this->place()?->seat ?? '');
    }

    /**
     * Whether they have a place here at all.
     *
     * Not the same question as which chair. Somebody watching has a place and
     * an empty seat; somebody who has just followed an invitation has neither,
     * and asking the venue for a ticket on their behalf is how this screen used
     * to greet them with "That visitor has no place there".
     */
    /**
     * Who is playing each side, by the name their own server gave them.
     *
     * From the seats rather than the room, because this is who the game belongs
     * to — it stays true while somebody is reconnecting, and it is what a
     * stranger looking at an invitation sees before any socket is opened.
     *
     * @return array<string, string>
     */
    public function players(): array
    {
        $game = $this->game();

        if ($game === null) {
            return [];
        }

        $players = [];

        foreach ($game->seats()->with('delegation')->whereIn('seat', ['white', 'black'])->get() as $seat) {
            $players[$seat->seat] = (string) ($seat->delegation?->handle ?? '');
        }

        return $players;
    }

    public function seated(): bool
    {
        return $this->place() !== null;
    }

    private function place(): ?Seat
    {
        $visitor = app(Visitors::class)->current(request());
        $game = $this->game();

        if ($visitor === null || $game === null) {
            return null;
        }

        // Asked by who they are. Keyed on the delegation, somebody who came
        // back through the door was shown as watching their own game.
        return app(Gatherings::class)->seatOf($game, $visitor);
    }
};?>

{{--
    No padding of its own. Flux's main area already applies `p-6 lg:p-8`, and
    padding again is how this screen ended up with twice the margins of the
    rest.
--}}
<div class="flex flex-col gap-6">
    @php($game = $this->game())

    @if ($game === null)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('There is no game here') }}</flux:callout.heading>
            <flux:callout.text>{{ __('It may have finished, or the link may be wrong.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($game !== null && ! $game->isOpen())
        {{--
            A game that is over, drawn from what the venue kept rather than
            from a room — the hub forgot it when the last person left, so
            joining one is how somebody coming back to look was once shown
            "That is over" as though they had done something wrong.

            The same component the live board becomes when a game ends, given
            the record instead of a socket. One set of markup for one thing.
        --}}
        @php($outcome = $game->outcome ?? [])

        <div
            wire:ignore
            x-data="chessReplay({
                positions: @js(array_values((array) ($outcome['positions'] ?? []))),
                moves: @js(array_values((array) ($outcome['moves'] ?? []))),
                seat: @js($this->seat()),
                outcome: @js((string) ($outcome['outcome'] ?? '')),
                winner: @js((string) ($outcome['winner'] ?? '')),
                white: @js($this->players()['white'] ?? ''),
                black: @js($this->players()['black'] ?? ''),
            })"
            class="flex w-full flex-col items-center gap-4"
        >
            @include('chess::header')

            @include('chess::ending')

            @if (($outcome['positions'] ?? []) !== [])
                @include('chess::player', ['side' => 'far'])

                @include('chess::board')

                @include('chess::player', ['side' => 'near'])

                @include('chess::replay')
            @endif
        </div>
    @elseif ($game !== null)
        {{--
            The board is drawn and driven by the hub, not by Livewire.

            A move has to be refused by the referee rather than by this page, so
            the page holds no opinion about the rules at all — it renders what
            the room says and asks for what somebody clicked. Everything below
            is scaffolding around a websocket.

            `wire:ignore` because Livewire must not re-render underneath it: a
            round trip to PHP between two clicks would fight the live state.
        --}}
        <div
            wire:ignore
            x-data="chessTable({
                ticketUrl: @js($this->seated() ? route('venue.ticket', $game->key) : null),
                settleUrl: @js(route('chess.settle', $game->key)),
                seat: @js($this->seat()),
                invitation: @js(route('chess.table', $game->key)),
                white: @js($this->players()['white'] ?? ''),
                black: @js($this->players()['black'] ?? ''),
            })"
            class="flex flex-col items-center gap-4"
        >
            @include('chess::header')

            <template x-if="trouble">
                <flux:callout variant="danger" icon="exclamation-triangle" class="w-full">
                    <flux:callout.text x-text="trouble"></flux:callout.text>
                </flux:callout>
            </template>

            {{--
                Who is playing, on the side of the board they are sitting at.

                The far one above and the near one below, which is where they
                would be if this were a table — and for somebody watching, who
                is on neither side, black is above the way a board is drawn when
                nobody in particular is looking at it.
            --}}
            {{-- The moment it ends, this becomes the record of itself. --}}
            @include('chess::ending')

            @include('chess::player', ['side' => 'far'])

            @include('chess::board')

            @include('chess::player', ['side' => 'near'])

            @include('chess::replay')

            <flux:text class="font-mono text-xs" x-text="moves.join(' ')"></flux:text>
        </div>
    @endif
</div>
