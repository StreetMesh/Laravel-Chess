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

<div class="flex flex-col gap-6 p-6">
    @php($game = $this->game())

    @if ($game === null)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('There is no game here') }}</flux:callout.heading>
            <flux:callout.text>{{ __('It may have finished, or the link may be wrong.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:heading size="xl">{{ __('Chess') }}</flux:heading>

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
                    <span x-show="seat" x-text="`You are ${seat}`"></span>
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

                Measured against the viewport rather than against a parent's
                padding. Cancelling the padding worked out to a board that was
                nearly edge to edge and obviously not, because the page's 1.5rem
                is not the only padding between here and the screen — the layout
                adds its own, and that is Flux's business rather than something
                to keep in step with by hand. Half the parent across, then half
                its own width back, is edge to edge whatever is in between.

                The sides lose their border and their rounding out there, since
                an edge drawn against the edge of the screen is a line with
                nothing on the other side of it. The bottom keeps both: that is
                the part doing the work.
            --}}
            <div class="relative left-1/2 w-screen max-w-none -translate-x-1/2 sm:static sm:left-auto sm:w-full sm:max-w-[26rem] sm:translate-x-0 lg:max-w-[34rem] xl:max-w-[38rem]">
                <div class="grid grid-cols-8 overflow-hidden border-x-0 border-t-0 border-b-[10px] border-zinc-300 sm:rounded-lg sm:border-2 sm:border-b-[10px] dark:border-zinc-950">
                    <template x-for="cell in squares" :key="cell.name">
                        <button
                            type="button"
                            @click="choose(cell.name)"
                            :disabled="!myMove"
                            :title="cell.piece ? `${cell.white ? 'white' : 'black'} ${cell.piece.name} on ${cell.name}` : cell.name"
                            :class="[
                                cell.dark ? 'bg-zinc-200 dark:bg-zinc-700' : 'bg-white dark:bg-zinc-500',
                                selected === cell.name ? 'ring-2 ring-inset ring-emerald-400' : '',
                                myMove ? 'cursor-pointer' : 'cursor-default',
                            ]"
                            class="relative flex aspect-square w-full items-center justify-center"
                        >
                            {{--
                                Font Awesome Free, CC BY 4.0. Solid-only, so the two
                                sides share a silhouette and are told apart by fill
                                and outline, the way a real set is.

                                `paint-order:stroke` puts the outline underneath the
                                fill, so it reads as an edge rather than as a piece
                                that has been thinned. Without it a white piece on a
                                light square is very nearly invisible, which is what
                                the Unicode glyphs used to give us.
                            --}}
                            <svg
                                x-show="cell.piece"
                                viewBox="0 0 512 512"
                                class="size-[65%] overflow-visible [paint-order:stroke]"
                                :class="cell.white
                                    ? 'fill-white stroke-zinc-900 stroke-[66]'
                                    : 'fill-zinc-900 stroke-zinc-100/80 stroke-[36]'"
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
