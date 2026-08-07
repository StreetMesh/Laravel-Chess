<?php

namespace StreetMesh\Chess\Tests;

use Illuminate\Support\Facades\Http;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\P256;
use StreetMesh\Protocol\Scope;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Visitors;

/**
 * Chess as the venue sees it.
 *
 * Nothing here knows how a knight moves — that is the hub's business, and
 * deliberately not duplicated, because two implementations of the rules are two
 * chances to disagree about who won. What is tested is everything the rules are
 * not: that a game exists, who is at it, and that when it ends each player ends
 * up holding their own record of it.
 */
class GameTest extends TestCase
{
    private function games(): Games
    {
        return $this->app->make(Games::class);
    }

    private function player(string $who): Delegation
    {
        return Delegation::create([
            'did' => 'did:web:'.$who.'.home.test',
            'handle' => $who.'.home.test',
            'issuer' => 'https://'.$who.'.home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'refresh_token' => 'a-refresh-token',
            'scope' => 'atproto '.Scope::forRepo([ChessExperience::COLLECTION], [Scope::CREATE]),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function finished(array $overrides = []): array
    {
        return [
            'outcome' => 'checkmate',
            'winner' => 'white',
            'moves' => ['e4', 'e5', 'Bc4', 'Nc6', 'Qh5', 'Nf6', 'Qxf7#'],
            'fen' => 'r1bqkb1r/pppp1Qpp/2n2n2/4p3/2B1P3/8/PPPP1PPP/RNB1K1NR b KQkq - 0 4',
            ...$overrides,
        ];
    }

    public function test_opening_a_game_seats_whoever_opened_it(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->assertSame(ChessExperience::COLLECTION, $game->experience);
        $this->assertSame('white', (string) $game->seats()->value('seat'));
    }

    public function test_the_second_person_takes_the_other_chair(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $this->assertSame('black', $games->join($game, $this->player('bob'))->seat);
    }

    /**
     * A game whose white player gave their permission back.
     *
     * A seat belongs to a delegation and goes with it, so revoking leaves a
     * game with a black player and no white — a shape the lobby had never been
     * shown. It read `$players['white']` directly, an undefined key is an
     * exception once debug is off, and so the lobby returned 500 to everybody,
     * over one visitor's revoked permission.
     */
    public function test_the_lobby_survives_a_game_whose_player_revoked(): void
    {
        $games = $this->games();

        $white = $this->player('alice');
        $black = $this->player('bob');

        $game = $games->open($white);
        $games->join($game, $black);

        // Revoking is a delete, and the seat goes with it.
        $white->delete();

        session([Visitors::SESSION_KEY => $black->id]);

        $this->get(route('chess.lobby'))
            ->assertOk()
            ->assertSee('bob is waiting for an opponent');
    }

    /**
     * A game other people can watch is a better thing than a game they cannot,
     * so a third arrival joins the audience rather than being turned away.
     */
    public function test_a_third_person_watches_rather_than_being_turned_away(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $games->join($game, $this->player('bob'));

        $this->assertSame('', $games->join($game, $this->player('carol'))->seat);
    }

    /**
     * The end of the whole exercise: each player holds their own record of the
     * game, on the server they chose, signed by a venue that may not outlive it.
     */
    public function test_a_finished_game_reaches_both_players_own_stores(): void
    {
        $written = [];

        Http::fake(function ($request) use (&$written) {
            $written[] = $request->url();

            return Http::response(['uri' => 'at://did:web:somebody/'.ChessExperience::COLLECTION.'/3abc', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $records = $games->settle($game, $this->finished());

        $this->assertSame(['white', 'black'], array_keys($records));

        // Two servers written to, because the players do not live together.
        $this->assertCount(2, $written);
        $this->assertNotSame($written[0], $written[1]);

        $this->assertSame(Gathering::CONCLUDED, $game->refresh()->status);
    }

    /**
     * Written from each player's own point of view, because it is their record
     * rather than a row in somebody's database.
     */
    public function test_each_player_is_told_what_happened_to_them(): void
    {
        $sent = [];

        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data();

            return Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $games->settle($game, $this->finished());

        $claims = array_map(
            fn (array $body): array => json_decode(
                (string) base64_decode(strtr(explode('.', $body['record']['attestation'])[1], '-_', '+/'), true),
                true,
            ),
            $sent,
        );

        $this->assertSame(['win', 'loss'], array_column($claims, 'result'));
        $this->assertSame(['white', 'black'], array_column($claims, 'seat'));

        // And each names the other, so a record is a game rather than a score.
        $this->assertSame('did:web:bob.home.test', $claims[0]['opponent']);
        $this->assertSame('did:web:alice.home.test', $claims[1]['opponent']);
    }

    public function test_a_draw_is_a_draw_for_both_of_them(): void
    {
        $sent = [];

        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data();

            return Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $games->settle($game, $this->finished(['outcome' => 'stalemate', 'winner' => '']));

        foreach ($sent as $body) {
            $claims = json_decode(
                (string) base64_decode(strtr(explode('.', $body['record']['attestation'])[1], '-_', '+/'), true),
                true,
            );

            $this->assertSame('draw', $claims['result']);
            $this->assertSame('stalemate', $claims['outcome']);
        }
    }

    /**
     * Somebody withdrawing permission is an ordinary answer, and their opponent
     * should still end up with their record.
     */
    public function test_one_server_refusing_does_not_cost_the_other_player_theirs(): void
    {
        Http::fake(function ($request) {
            return str_contains($request->url(), 'alice')
                ? Http::response(['error' => 'invalid_token'], 401)
                : Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $records = $games->settle($game, $this->finished());

        $this->assertSame(['black'], array_keys($records));
    }

    /**
     * The audience did not play, so there is nothing to say about them.
     */
    public function test_watchers_get_no_record(): void
    {
        Http::fake(fn () => Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201));

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));
        $games->join($game, $this->player('carol'));

        $this->assertCount(2, $games->settle($game, $this->finished()));
    }
}
