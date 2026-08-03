<?php

namespace StreetMesh\Chess;

use StreetMesh\Protocol\Laravel\Capabilities\Capability;
use StreetMesh\Protocol\Laravel\Capabilities\Widget;

/**
 * Chess, as something a venue can offer.
 *
 * The first experience, and so the first thing to exercise the framework as a
 * framework rather than as its own scaffolding. Everything it needs from
 * StreetMesh — arriving from another server, being seated, a room with an
 * authoritative referee, a signed record at the end — it asks for rather than
 * builds.
 *
 * What is left, and what an experience is, is: the rules, the screens, and what
 * a finished game is worth writing down.
 */
final class ChessCapability implements Capability
{
    /**
     * The collection its records go in, and the room type in the hub.
     *
     * One name for both, because they are the same thing seen from two sides:
     * what kind of gathering this is, and what kind of record it produces.
     */
    public const COLLECTION = 'com.streetmesh.games.chess';

    public function name(): string
    {
        return 'chess';
    }

    /**
     * Nothing on the wire. A venue announces that it is a venue; which
     * experiences it happens to have installed is not a stranger's business
     * until they arrive and look at the menu.
     */
    public function serviceType(): string
    {
        return '';
    }

    public function frontPage(): string
    {
        return '';
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        return [new ChessWidget];
    }

    /**
     * @return array<int, array{label: string, route: string, icon?: string}>
     */
    public function navigation(): array
    {
        return [
            ['label' => 'Chess', 'route' => 'chess.lobby', 'icon' => 'squares-2x2'],
        ];
    }
}
