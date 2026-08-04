<?php

namespace StreetMesh\Chess;

use StreetMesh\Protocol\Scope;
use StreetMesh\Venue\Experiences\Experience;

/**
 * Chess, as something a venue hosts.
 *
 * Written as a capability first, which was wrong and said so: two of the four
 * methods it had to implement returned empty strings, because chess has no
 * service type to announce and no front page to greet anybody with. Chess is
 * not a kind of server. It is a thing you can do at one.
 *
 * The visible symptom was that it appeared in the main navigation beside
 * "Residents" and "Experiences", as though a server could be a chess in the way
 * it can be a domicile.
 */
final class ChessExperience implements Experience
{
    /**
     * One name for three things: the collection its records go in, the room
     * type its hub serves, and the experience itself.
     */
    public const COLLECTION = 'com.streetmesh.games.chess';

    public function name(): string
    {
        return self::COLLECTION;
    }

    public function title(): string
    {
        return 'Chess';
    }

    public function description(): string
    {
        return 'Play somebody who lives on another server, and keep your own record of it.';
    }

    public function icon(): string
    {
        return 'squares-2x2';
    }

    public function route(): string
    {
        return 'chess.lobby';
    }

    /**
     * Adding, and never altering.
     *
     * A venue that could change a game after the fact could change who won, so
     * it asks for the least that works — and a visitor reading this on their own
     * server's consent screen can see the difference.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return [(string) Scope::forRepo([self::COLLECTION], [Scope::CREATE])];
    }
}
