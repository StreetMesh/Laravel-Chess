{{--
    A finished game: what happened, and the way back through it.

    Shown by the live board the moment a game ends and by a game opened later,
    which are the same thing seen at different times — so it is one file, driven
    by whatever is holding it. Both answer `over`, `ending`, `at`, `last` and
    `playedHere`; neither is mentioned here.

    It used to exist twice, once in Blade from the record and once in the room's
    own words, and the two drifted immediately: one said "Black wins by
    resignation" and the other "Black won by resignation", and only one of them
    knew which side you had been.

    Font Awesome Free, CC BY 4.0. A chequered flag rather than a plain one,
    which is what the end of a game looks like — Flux ships only the plain one,
    so it comes from the same set as the pieces.

    Copied out of the package rather than typed. Transcribed by hand it came out
    eleven characters short, which is not a thing you can see in a diff and is
    very much a thing you can see on the screen: the subpaths that cut the
    checks out went with it and the flag rendered as a blob.
--}}
<div x-show="over" x-cloak class="flex w-full flex-col items-center gap-4">
    <flux:callout class="w-full">
        <flux:callout.heading class="flex items-center gap-2">
            <svg viewBox="0 0 448 512" class="size-4 shrink-0 fill-current" aria-hidden="true">
                <path d="M32 0C49.7 0 64 14.3 64 32l0 16 69-17.2c38.1-9.5 78.3-5.1 113.5 12.5 46.3 23.2 100.8 23.2 147.1 0l9.6-4.8C423.8 28.1 448 43.1 448 66.1l0 279.7c0 13.3-8.3 25.3-20.8 30l-34.7 13c-46.2 17.3-97.6 14.6-141.7-7.4-37.9-19-81.4-23.7-122.5-13.4L64 384 64 480c0 17.7-14.3 32-32 32S0 497.7 0 480L0 32C0 14.3 14.3 0 32 0zM64 187.1l64-13.9 0 65.5-64 13.9 0 65.5 48.8-12.2c5.1-1.3 10.1-2.4 15.2-3.3l0-63.9 38.9-8.4c8.3-1.8 16.7-2.5 25.1-2.1l0-64c13.6 .4 27.2 2.6 40.4 6.4l23.6 6.9 0 66.7-41.7-12.3c-7.3-2.1-14.8-3.4-22.3-3.8l0 71.4c21.8 1.9 43.3 6.7 64 14.4l0-69.8 22.7 6.7c13.5 4 27.3 6.4 41.3 7.4l0-64.2c-7.8-.8-15.6-2.3-23.2-4.5l-40.8-12 0-62c-13-3.8-25.8-8.8-38.2-15-8.2-4.1-16.9-7-25.8-8.8l0 72.4c-13-.4-26 .8-38.7 3.6l-25.3 5.5 0-75.2-64 16 0 73.1zM320 335.7c16.8 1.5 33.9-.7 50-6.8l14-5.2 0-71.7-7.9 1.8c-18.4 4.3-37.3 5.7-56.1 4.5l0 77.4zm64-149.4l0-70.8c-20.9 6.1-42.4 9.1-64 9.1l0 69.4c13.9 1.4 28 .5 41.7-2.6l22.3-5.2z"></path>
            </svg>

            <span x-text="ending"></span>
        </flux:callout.heading>
    </flux:callout>

</div>
