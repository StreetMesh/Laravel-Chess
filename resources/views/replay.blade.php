{{--
    The way back through a finished game.

    Under the board rather than above it, where the thing being controlled is.
    It sat above with the result and read as part of the announcement rather
    than as something to press.

    Nothing here works anything out — the positions were recorded by the room as
    the game was played, and deriving them from the moves instead would be a
    second implementation of chess to read a game already decided.
--}}
<div x-show="over" x-cloak class="flex w-full flex-col items-center gap-3">
{{--
    The game again, from the positions the room recorded as it was played.
    Nothing here works anything out — deriving them from the moves would be
    a second implementation of chess to read a game already decided.
--}}
<template x-if="positions.length > 1">
    <div class="flex flex-col items-center gap-3">
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
</template>
</div>
