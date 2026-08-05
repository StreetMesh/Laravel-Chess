<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Gatherings;
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
        $visitor = app(Visitors::class)->current(request());
        $game = $this->game();

        if ($visitor === null || $game === null) {
            return '';
        }

        // Asked by who they are. Keyed on the delegation, somebody who came
        // back through the door was shown as watching their own game.
        return (string) (app(Gatherings::class)->seatOf($game, $visitor)?->seat ?? '');
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
    @else
        {{--
            Which table this is, and the way out.

            The key rather than "Chess": you know it is chess, you are looking
            at a chessboard. What a screen cannot tell you is which of several
            games you are in, and that is the thing somebody reads back to you.
        --}}
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="xl">
                {{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}
            </flux:heading>

            <flux:button
                :href="route('chess.lobby')"
                size="sm"
                variant="outline"
                icon:trailing="arrow-right"
                wire:navigate
            >
                {{ __('Lobby') }}
            </flux:button>
        </div>
    @endif

    @if ($game !== null && ! $game->isOpen())
        {{--
            A game that is over is drawn from what the venue kept, not from a
            room. The hub forgot this the moment the last person left it, so
            trying to join one is how somebody coming back to look at a finished
            game was shown "That is over" as though they had done something
            wrong.

            Nothing here connects to anything. It is a record being read.
        --}}
        @php($outcome = $game->outcome ?? [])

        <div class="flex flex-col items-center gap-4">
            <flux:callout icon="flag" class="w-full">
                <flux:callout.heading>
                    @if (($outcome['outcome'] ?? '') === '')
                        {{--
                            A game this venue concluded without keeping how.
                            Saying "unknown" would be closer to a guess than to
                            an answer, and inventing one would be this venue
                            asserting something it never saw.
                        --}}
                        {{ __('This game is over') }}
                    @elseif (($outcome['winner'] ?? '') !== '')
                        {{ __(':winner won by :how', [
                            'winner' => ucfirst((string) $outcome['winner']),
                            'how' => $outcome['outcome'],
                        ]) }}
                    @else
                        {{ __('Drawn — :how', ['how' => $outcome['outcome']]) }}
                    @endif
                </flux:callout.heading>

                @if ($this->seat() !== '')
                    <flux:callout.text>
                        {{ __('You were :seat.', ['seat' => $this->seat()]) }}
                    </flux:callout.text>
                @endif
            </flux:callout>

            {{--
                The game again, from the positions the room recorded as it was
                played. Nothing here works anything out — replaying by deriving
                the positions from the moves would be a second implementation of
                chess, for the sake of reading a game already decided.
            --}}
            @if (($outcome['positions'] ?? []) !== [])
                <div
                    wire:ignore
                    x-data="chessReplay(@js(array_values((array) $outcome['positions'])), @js(array_values((array) ($outcome['moves'] ?? []))), @js($this->seat()))"
                    class="flex w-full flex-col items-center gap-4"
                >
                    @include('chess::board')

                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" @click="step(-1)" x-bind:disabled="at === 0">
                            {{ __('Back') }}
                        </flux:button>

                        <flux:button size="sm" variant="outline" @click="play()">
                            <span x-text="playing ? '{{ __('Pause') }}' : '{{ __('Replay') }}'"></span>
                        </flux:button>

                        <flux:button size="sm" variant="ghost" @click="step(1)" x-bind:disabled="at === last">
                            {{ __('Forward') }}
                        </flux:button>
                    </div>

                    <flux:text class="font-mono text-xs">
                        <span x-text="at === 0 ? '{{ __('Before the first move') }}' : `${at}. ${playedHere}`"></span>
                    </flux:text>
                </div>
            @elseif (($outcome['moves'] ?? []) !== [])
                <flux:text class="font-mono text-xs">{{ implode(' ', (array) $outcome['moves']) }}</flux:text>
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
            x-data="chessTable(@js(route('venue.ticket', $game->key)), @js(route('chess.settle', $game->key)), @js($this->seat()))"
            class="flex flex-col items-center gap-4"
        >
            <template x-if="trouble">
                <flux:callout variant="danger" icon="exclamation-triangle" class="w-full">
                    <flux:callout.text x-text="trouble"></flux:callout.text>
                </flux:callout>
            </template>

            <div class="flex w-full items-center justify-between gap-4">
                <flux:text>
                    {{--
                        Watching, said the same way playing is: a mark, then the
                        word, in bold. Grey because it is the one status that is
                        not a side — there is no colour to be.
                    --}}
                    <span x-show="!seat" class="flex items-center gap-3 font-semibold">
                        <flux:icon name="eye" class="size-4 shrink-0 text-slate-400" />
                        {{ __('Watching') }}
                    </span>

                    {{--
                        Which side you are, in the colour you are. The knight is
                        the same artwork the board draws, filled from the same
                        two values — so "you are black" is answered by the thing
                        on the board rather than by the word alone.

                        A light rim on both, because a pearl piece on a white
                        page has nothing else to be seen against.

                        The pearl is written out here and again on the board
                        below, and the two have to agree. Naming it once would
                        mean a colour defined in the host's stylesheet, and this
                        package would lose its pale pieces in any host that had
                        not been told about it — Tailwind only generates what it
                        finds written down.
                    --}}
                    <span x-show="seat" class="flex items-center gap-3 font-semibold">
                        <svg
                            viewBox="0 0 512 512"
                            class="size-4 shrink-0 overflow-visible stroke-slate-300 stroke-[24] [paint-order:stroke]"
                            :class="seat === 'white' ? 'fill-[#dcd6cc]' : 'fill-slate-800'"
                            aria-hidden="true"
                        >
                            <path :d="knight.path" :transform="knight.transform"></path>
                        </svg>

                        <span x-text="`You are ${seat}`"></span>

                        {{--
                            Beside who you are, because it is the one thing you
                            can do that is not a move — and it belongs next to
                            the side it would give up rather than adrift under
                            the board.

                            It asks before it acts: the label says what a second
                            press will do, and clicking anywhere else puts the
                            question away. It cannot be taken back.
                        --}}
                        <span x-show="!over" x-data="{ asking: false }" @click.outside="asking = false">
                            {{--
                                `sm` rather than `xs`, because `xs` is `text-xs`
                                and everything beside it is `text-sm`. Matching
                                the line it sits in matters more here than being
                                the smallest thing available.
                            --}}
                            <flux:button
                                size="sm"
                                variant="ghost"
                                @click="asking ? (asking = false, resign()) : asking = true"
                                x-bind:class="asking ? 'text-rose-600 dark:text-rose-400' : ''"
                            >
                                <span x-text="asking ? '{{ __('Really resign?') }}' : '{{ __('Resign') }}'"></span>
                            </flux:button>
                        </span>
                    </span>
                </flux:text>

                <flux:text x-text="status"></flux:text>
            </div>

            @include('chess::board')

            <flux:text class="font-mono text-xs" x-text="moves.join(' ')"></flux:text>
        </div>
    @endif
</div>
