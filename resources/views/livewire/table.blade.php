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
                    <span x-show="!seat">{{ __('Watching') }}</span>

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
                            <flux:button
                                size="xs"
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

            {{--
                Eight files, and the same grid whichever way up you are sitting.

                Takes the width it is given up to a ceiling, rather than being
                eight squares of a fixed size. A board measured in pixels per
                square runs off the side of a phone and is lost in the middle of
                a desktop; measured as a share of the space, it is the same board
                on both.

                A thicker edge along the bottom than around the sides, in the
                darker cut of the squares' own colour. It is the whole of the
                dimensionality: enough to read as a solid object sitting on the
                page rather than a pattern printed on it.

                On a phone it runs to both edges, which is what buys the squares
                enough size to aim at with a thumb.

                On a phone it cancels exactly one padding: Flux's main area
                applies `p-6`, and this takes 1.5rem back off each side.

                It took three tries to be worth this little. Cancelling the
                page's own padding left the layout's; measuring against the
                viewport put a 100vw element inside Flux's body grid, where the
                main column is a track rather than the screen. Both were guesses
                at a number nobody had looked up. `flux:main` says `p-6 lg:p-8`
                in its own source, and once the screen stopped padding twice
                there was one padding left with a known value.

                Below `sm` only — at `lg` the main area pads by `p-8` and the
                board is nowhere near the edges anyway.

                On a phone the sides lose their border and their rounding: the
                board actually reaches the edges now, and an edge drawn against
                the edge of the screen is a line with nothing on the far side of
                it. Top and bottom keep theirs, and the thick bottom is what
                still makes it an object.

                This was tried once before it was true — when the board stopped
                short of the edges, dropping the sides only made it look
                unfinished.
            --}}
            <div class="max-sm:-mx-6 max-sm:w-[calc(100%+3rem)] w-full sm:max-w-[26rem] lg:max-w-[34rem] xl:max-w-[38rem]">
                <div class="grid grid-cols-8 overflow-hidden border-x-0 border-t-2 border-b-[10px] border-slate-300 sm:rounded-lg sm:border-x-2 dark:border-slate-950">
                    <template x-for="cell in squares" :key="cell.name">
                        <button
                            type="button"
                            @click="choose(cell.name)"
                            :disabled="!myMove"
                            :title="cell.piece ? `${cell.white ? 'white' : 'black'} ${cell.piece.name} on ${cell.name}` : cell.name"
                            :class="[
                                cell.dark ? 'bg-slate-200 dark:bg-slate-700' : 'bg-white dark:bg-slate-500',
                                selected === cell.name ? 'ring-2 ring-inset ring-emerald-400' : '',
                                myMove ? 'cursor-pointer' : 'cursor-default',
                            ]"
                            class="relative flex aspect-square w-full items-center justify-center"
                        >
                            {{--
                                Font Awesome Free, CC BY 4.0. Solid-only, so the
                                two sides share a silhouette and are told apart
                                by fill, the way a real set is.

                                Both sides are stickers: a white border cut
                                round the shape, the same on each, and only the
                                fill telling them apart. The white pieces are
                                off-white rather than white so there is a fill
                                to see inside their own border.

                                A warm pearl against a blue-black, which is the
                                pairing a real set has: ivory is never white and
                                the dark side is never quite black. Cooling the
                                pale side to match the dark one made it read as
                                grey rather than as a material.

                                One border colour and one weight for both, which
                                is why they read as one set — four colours and
                                two stroke widths was where this started, and it
                                looked like two drawings sharing a board.

                                `paint-order:stroke` puts the outline underneath the
                                fill, so it reads as an edge rather than as a piece
                                that has been thinned. Without it a white piece on a
                                light square is very nearly invisible, which is what
                                the Unicode glyphs used to give us.

                                A shadow lifts them off the squares. Offset
                                downward and barely blurred, so it falls from
                                the foot of the piece the way a shadow does for
                                something standing on a board — a shadow spread
                                evenly around a shape reads as the shape
                                floating instead.

                                A filter on the whole element rather than
                                anything in the artwork, so it follows the
                                silhouette, outline included, and costs the path
                                data nothing.

                                Eight-digit hex rather than an rgb() with an
                                alpha slash, because a bracketed utility that
                                fails to parse produces no shadow at all rather
                                than an error — and nothing here can tell the
                                difference between that and a subtle one.
                            --}}
                            <svg
                                x-show="cell.piece"
                                viewBox="0 0 512 512"
                                class="size-[65%] overflow-visible stroke-white stroke-[66] drop-shadow-[0_3px_2px_#00000059] [paint-order:stroke]"
                                :class="cell.white ? 'fill-[#dcd6cc]' : 'fill-slate-800'"
                                aria-hidden="true"
                            >
                                <path :d="cell.piece?.path" :transform="cell.piece?.transform"></path>
                            </svg>

                            {{--
                                Where the piece you are holding may go.

                                A dot on an empty square and a ring around a square
                                with something on it, so a capture does not hide
                                behind the piece it would take.
                            --}}
                            <span
                                x-show="isTarget(cell.name)"
                                :class="cell.piece
                                    ? 'absolute inset-1 rounded-full ring-4 ring-inset ring-emerald-400/70'
                                    : 'absolute size-3 rounded-full bg-emerald-400/70'"
                            ></span>
                        </button>
                    </template>
                </div>
            </div>

            <flux:text class="font-mono text-xs" x-text="moves.join(' ')"></flux:text>
        </div>
    @endif
</div>
