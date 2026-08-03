<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessCapability;
use StreetMesh\Venue\Gatherings\Gathering;
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
            ->where('experience', ChessCapability::COLLECTION)
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

        return (string) ($game->seats()->where('delegation_id', $visitor->id)->value('seat') ?? '');
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
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="xl">{{ __('Chess') }}</flux:heading>
            <flux:button :href="route('chess.lobby')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
        </div>

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
            x-data="chessTable(@js(route('venue.ticket', $game->key)), @js($this->seat()))"
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

            {{-- Eight files, and the same grid whichever way up you are sitting. --}}
            <div class="grid grid-cols-8 overflow-hidden rounded border-2 border-zinc-700">
                <template x-for="cell in squares" :key="cell.name">
                    <button
                        type="button"
                        @click="choose(cell.name)"
                        :disabled="!seat || over"
                        :class="[
                            cell.dark ? 'bg-zinc-600' : 'bg-zinc-300',
                            selected === cell.name ? 'ring-2 ring-inset ring-emerald-400' : '',
                        ]"
                        class="relative flex aspect-square w-10 items-center justify-center text-2xl sm:w-12"
                    >
                        <span
                            x-text="cell.glyph"
                            :class="cell.white ? 'text-white' : 'text-zinc-900'"
                            class="drop-shadow"
                        ></span>
                    </button>
                </template>
            </div>

            <flux:text class="font-mono text-xs" x-text="moves.join(' ')"></flux:text>
        </div>
    @endif
</div>
