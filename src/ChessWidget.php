<?php

namespace StreetMesh\Chess;

use StreetMesh\Protocol\Laravel\Capabilities\Widget;
use StreetMesh\Venue\Gatherings\Gathering;

/**
 * A panel for a home page this experience does not own.
 *
 * Offered rather than placed. An operator decides whether chess appears on
 * their server's home page at all, and where beside everything else they have
 * installed.
 */
final class ChessWidget implements Widget
{
    public function name(): string
    {
        return 'chess.games';
    }

    public function title(): string
    {
        return 'Chess';
    }

    public function view(): string
    {
        return 'chess::widget';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'open' => Gathering::query()
                ->where('experience', ChessCapability::COLLECTION)
                ->where('status', Gathering::OPEN)
                ->count(),
        ];
    }
}
