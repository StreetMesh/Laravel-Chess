{{--
    Which table this is, what is happening at it, and the way out.

    Inside the board's component rather than above it, because the middle of
    those three changes on its own — whose move it is, or an invitation while
    there is nobody to play. It used to sit outside and could only ever show the
    key.

    The key rather than "Chess": you know it is chess, you are looking at a
    chessboard. What a screen cannot tell you is which of several games you are
    in, and that is the thing somebody reads back to you.
--}}
<div class="flex w-full items-center justify-between gap-4">
    <flux:heading size="xl">
        {{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}
    </flux:heading>

    <div class="flex items-center gap-3">
        {{--
            Playing a finished game back, beside its name rather than under the
            board. There is one control now: stepping through it a move at a
            time was a third and a fourth button for something nobody was asking
            to do that precisely.
        --}}
        <flux:button
            x-show="over"
            x-cloak
            size="sm"
            variant="outline"
            icon="play"
            @click="play()"
        >
            <span x-text="playing ? '{{ __('Pause') }}' : '{{ __('Replay') }}'"></span>
        </flux:button>

        @if ($game->isOpen() && ! $this->seated())
            {{--
                The one thing on offer to somebody who has just followed an
                invitation. Behind the door, which is where they meet it for the
                first time — the board they are looking at asked nothing of them.
            --}}
            <flux:button
                :href="route('chess.sit', $game->key)"
                size="sm"
                variant="primary"
                icon="arrow-right-end-on-rectangle"
                wire:navigate
            >
                {{ __('Sit to play') }}
            </flux:button>
        @elseif ($game->isOpen())
            {{--
                One place, three things, in order of what is worth doing.

                A table whose socket has gone offers the way back. A table with
                one person at it asks for the other — it does not need telling
                that white is to move, it needs an opponent. Otherwise it says
                whose turn it is.
            --}}
            <flux:button
                x-show="disconnected"
                x-cloak
                size="sm"
                variant="primary"
                icon="arrow-path"
                @click="reconnect()"
            >
                {{ __('Reconnect') }}
            </flux:button>

            {{--
                Two ways to hand somebody the table, offered rather than
                guessed at.

                It used to be one button that picked for you: the share sheet
                where the browser had one, the clipboard where it did not. Which
                you got depended on the browser rather than on what you wanted.
            --}}
            <flux:dropdown x-show="!disconnected && waiting" x-cloak position="bottom" align="end">
                <flux:button size="sm" variant="primary" icon="arrow-up-on-square" icon:trailing="chevron-down">
                    <span x-text="invited || '{{ __('Invite opponent') }}'"></span>
                </flux:button>

                <flux:menu>
                    <flux:menu.item icon="link" @click="copyLink()">
                        {{ __('Copy link') }}
                    </flux:menu.item>

                    {{-- Only where the operating system has one to offer. --}}
                    <flux:menu.item icon="arrow-up-on-square" x-show="canShare" @click="share()">
                        {{ __('Share') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <flux:text x-show="!disconnected && !waiting" x-text="status"></flux:text>
        @endif

        {{--
            Hidden on a phone, where the board wants the width and the browser
            has a back button of its own an inch below this one.
        --}}
        <flux:button
            :href="route('chess.lobby')"
            size="sm"
            variant="outline"
            icon:trailing="arrow-right"
            class="max-sm:hidden"
            wire:navigate
        >
            {{ __('Lobby') }}
        </flux:button>
    </div>
</div>
