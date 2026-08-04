<?php

namespace StreetMesh\Chess\Http;

use Illuminate\Http\JsonResponse;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Results;

/**
 * Getting a finished game into the players' own records.
 *
 * The whole point of the exercise, and the last thing to be wired up. A game
 * nobody keeps is an afternoon; a game each player ends up holding their own
 * signed record of is the thing this project exists to demonstrate.
 *
 * A browser asks for this, and asking is all it can do. What happened comes
 * from the hub, which decided it; whether it happened at all comes from the
 * hub too, because a game still being played answers nothing. So the worst a
 * visitor can achieve by calling this early, or often, or for somebody else's
 * table, is to make this server ask a question it already knows the answer to.
 */
final class SettleController
{
    public function __invoke(string $key, Games $games, Results $results): JsonResponse
    {
        $game = Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->where('key', $key)
            ->first();

        if ($game === null) {
            return response()->json(['settled' => false, 'because' => 'no such game'], 404);
        }

        /*
         * Already done. Settling twice would write each player a second record
         * of the same game, and a repository is append-only — there would be no
         * taking it back.
         */
        if (! $game->isOpen()) {
            return response()->json(['settled' => true, 'already' => true]);
        }

        $result = $results->of($game);

        if ($result === null) {
            return response()->json(['settled' => false, 'because' => 'not over']);
        }

        $written = $games->settle($game, [
            'outcome' => (string) ($result['outcome'] ?? ''),
            'winner' => (string) ($result['winner'] ?? ''),
            'moves' => array_values((array) ($result['moves'] ?? [])),
            'fen' => (string) ($result['fen'] ?? ''),
        ]);

        return response()->json(['settled' => true, 'records' => $written]);
    }
}
