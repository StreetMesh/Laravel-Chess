{{--
    The board, drawn from whatever is holding it.

    Included by the live table and by the replay of a finished one, which read
    entirely different things — a websocket in one case and a list of recorded
    positions in the other. Neither is mentioned here. All this needs is a
    `squares` array and something to answer `myMove`, `selected`, `isTarget`
    and `choose`, which the replay answers with "no", "nothing", "no" and
    "nothing happens".

    One copy because two copies drift. The account menu was two copies of one
    thing and grew a control in only one of them.
--}}

{{--
    The one piece of styling this package cannot express in utilities, so it
    ships it rather than asking the host for a keyframe. A host that had not
    been told about it would simply not shake, which is the sort of thing
    nobody notices is missing.

    Small on purpose: a king that is being warned about, not a king having a
    fit. It settles rather than shaking forever, because a check lasts until
    somebody answers it and an animation that never stops stops being read.

    Off entirely for anybody who has asked for less movement — the ring is
    still there, and the room refuses an illegal move regardless.
--}}
<style>
    @keyframes chess-check {
        0%, 60%, 100% { transform: translateX(0) rotate(0); }
        10% { transform: translateX(-7%) rotate(-5deg); }
        20% { transform: translateX(7%) rotate(5deg); }
        30% { transform: translateX(-5%) rotate(-4deg); }
        40% { transform: translateX(5%) rotate(4deg); }
        50% { transform: translateX(-2%) rotate(-2deg); }
    }

    .chess-piece[data-check] {
        animation: chess-check 1.6s ease-in-out infinite;
        transform-origin: 50% 85%;
    }

    /*
        Which side a piece is on, said in an attribute rather than in a class.
        Safari would not take the class off again.

        A square only ever goes from holding a white piece to holding a black
        one when black captures. Safari left the pearl class on and added the
        dark one under it, so a pawn came out ivory on the square of the bishop
        it had just taken. The other direction looked fine, because adding is
        the half that worked. Alpine does this correctly against a spec DOM,
        which is why it survived being read several times.

        An attribute holds one value. Nothing is added or removed, so there is
        nothing to be left behind, in this engine or the next one.
    */
    /* The player rows draw the same pieces and use these too. */
    .chess-piece { fill: #1e293b; }
    .chess-piece[data-side='white'] { fill: #dcd6cc; }
    .dark .chess-piece[data-side='white'] { fill: #c0b6a2; }

    @media (prefers-reduced-motion: reduce) {
        .chess-piece[data-check] { animation: none; }
    }
</style>
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
                    {{--
                        The same green, at the same weight, as everything else
                        the board draws in green. It was `emerald-400` where the
                        dots and the capture ring are `emerald-400/70`, and two
                        pixels where they are four — the same token reads as a
                        different colour at full opacity over a pale square, and
                        it did.
                    --}}
                    selected === cell.name ? 'ring-4 ring-inset ring-emerald-400/70' : '',
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
                    class="chess-piece size-[65%] overflow-visible stroke-white stroke-[66] drop-shadow-[0_3px_2px_#00000059] [paint-order:stroke]"
                    :data-side="cell.white ? 'white' : 'black'"
                    :data-check="inCheck(cell) ? 'yes' : null"
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
