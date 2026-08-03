{{--
    A panel for a home page this experience does not own.
--}}
<flux:card class="flex flex-col gap-3">
    <flux:heading>{{ __('Chess') }}</flux:heading>

    <flux:text>
        {{ $open === 0
            ? __('No games in progress.')
            : trans_choice('{1}One game in progress.|[2,*]:count games in progress.', $open, ['count' => $open]) }}
    </flux:text>

    <div>
        <flux:button :href="route('chess.lobby')" size="sm" variant="ghost" wire:navigate>
            {{ __('Go to the tables') }}
        </flux:button>
    </div>
</flux:card>
