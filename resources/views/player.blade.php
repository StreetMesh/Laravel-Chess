{{--
    One side of the board, and who is playing it.

    Included twice, above and below, with `side` naming which end — `far` or
    `near`, answered by whatever is holding the board. Not "white" and "black",
    because which of those is at the top depends on where you are sitting.

    A piece rather than a swatch of colour. It is the same artwork the board
    draws, filled from the same two values, so a name and the pieces it belongs
    to are unmistakably the same thing — where a coloured dot was only ever a
    key to a legend.

    A side with nobody in it says so rather than showing an empty row: a table
    waiting for an opponent should look like one.

    Whose turn it is, said by the piece rather than in words. A board tells you
    that by somebody picking a piece up, and this is the nearest thing to it —
    two small hops and then a rest, so it reads as waiting rather than as
    something demanding attention.
--}}
<style>
    @keyframes chess-to-move {
        0%, 55%, 100% { transform: translateY(0); }
        18% { transform: translateY(-24%); }
        34% { transform: translateY(0); }
        44% { transform: translateY(-9%); }
    }

    .chess-to-move {
        animation: chess-to-move 1.9s ease-in-out infinite;
    }

    /*
     * Movement is the only thing saying whose turn it is, so somebody who has
     * asked for less of it needs the words back rather than nothing at all.
     */
    .chess-to-move-said {
        display: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .chess-to-move { animation: none; }
        .chess-to-move-said { display: inline; }
    }
</style>
<div class="flex w-full max-w-[26rem] items-center gap-2 lg:max-w-[34rem] xl:max-w-[38rem]">
    <svg
        viewBox="0 0 512 512"
        class="size-5 shrink-0 overflow-visible stroke-white stroke-[66] [paint-order:stroke]"
        x-bind:class="[
            {{ $side }} === 'white' ? 'fill-[#dcd6cc]' : 'fill-slate-800',
            !over && turn === {{ $side }} ? 'chess-to-move' : '',
        ]"
        aria-hidden="true"
    >
        <path x-bind:d="knight.path" x-bind:transform="knight.transform"></path>
    </svg>

    <flux:text class="truncate text-sm font-semibold">
        <span x-show="players[{{ $side }}]" x-text="players[{{ $side }}]"></span>
        <span x-show="!players[{{ $side }}]" x-cloak>{{ __('Waiting for a player') }}</span>
    </flux:text>

    {{-- Only for somebody who has asked not to be shown the movement. --}}
    <flux:text
        x-show="!over && turn === {{ $side }}"
        x-cloak
        class="chess-to-move-said text-xs"
    >{{ __('to move') }}</flux:text>

    @if ($side === 'near')
        {{--
            Giving up, beside your own name — it is the one thing you can do
            that is not a move, and it belongs with the side it would give up.

            Not until there is somebody to resign to: a table with one person at
            it is somebody waiting, and the room refuses it anyway.

            It asks before it acts, and cannot be taken back.
        --}}
        <span
            x-show="seat && !over && here >= 2"
            x-cloak
            class="ms-auto"
            x-data="{ asking: false }"
            @click.outside="asking = false"
        >
            <flux:button
                size="sm"
                variant="ghost"
                @click="asking ? (asking = false, resign()) : asking = true"
                x-bind:class="asking ? 'text-rose-600 dark:text-rose-400' : ''"
            >
                <span x-text="asking ? '{{ __('Really resign?') }}' : '{{ __('Resign') }}'"></span>
            </flux:button>
        </span>
    @endif
</div>
