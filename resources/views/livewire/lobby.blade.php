<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessCapability;
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
            ->where('experience', ChessCapability::COLLECTION)
            ->where('status', Gathering::OPEN)
            ->withCount('seats')
            ->latest()
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

<div class="flex flex-col gap-6 p-6">
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
                Sitting down is one button whether it makes you a player or an
                audience, because which of those you become is the venue's
                answer and not something to promise before asking.
            --}}
            <flux:button wire:click="sit('{{ $game->key }}')" variant="ghost">
                {{ __('Sit down') }}
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
</div>
