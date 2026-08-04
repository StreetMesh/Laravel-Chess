/**
 * A game of chess, adjudicated here.
 *
 * This room is the authority on the rules. It decides whether a move stands,
 * whose turn it is, and when the game is over — and it refuses a browser that
 * says otherwise, because a browser is somebody else's computer.
 *
 * It is not the authority on what *happened*. It cannot sign anything and it
 * forgets everything when it stops. The venue is what makes a result durable,
 * and the record ends up in the players' own stores rather than here.
 *
 * The rules themselves are chess.js, because chess has been implemented
 * correctly many times and this is not the interesting part of the problem.
 * What is interesting is that they are enforced *here* rather than in the two
 * browsers that would each like to win.
 */

import { Chess } from 'chess.js'
import { MapSchema, schema } from '@colyseus/schema'
import type { Client } from '@colyseus/core'
import { Occupant, VenueRoom, type Ticket } from '@streetmesh/hub'

const SEATS = ['white', 'black'] as const

export const ChessState = schema(
  {
    /** The position, in the notation every chess program already reads. */
    fen: 'string',

    /** Moves so far, so a screen can show the game rather than the position. */
    moves: ['string'],

    /** `white`, `black`, or empty once nobody is to move. */
    turn: 'string',

    /**
     * Every move that may be played right now, as `e2e4`.
     *
     * Published so a board can show somebody where a piece may go without
     * working it out. A browser that computed this would be a second
     * implementation of the rules, and two implementations are two chances to
     * disagree about who won — so the side that already decides what is legal
     * is the side that says so.
     *
     * Only ever the side to move's, which is the same list the room would
     * accept. It gives nothing away: it is derivable from the position by
     * anybody who cares to, and both players can see the position.
     */
    legal: ['string'],

    /** Empty while playing; otherwise how it ended. */
    outcome: 'string',
    winner: 'string',

    occupants: { map: Occupant },
  },
  'ChessState',
)

type ChessStateType = InstanceType<typeof ChessState>

export class ChessRoom extends VenueRoom<ChessStateType> {
  /** Two players and an audience. */
  maxClients = 16

  private game = new Chess()

  protected opened(): void {
    this.state = new ChessState({
      fen: this.game.fen(),
      moves: [],
      turn: 'white',
      legal: [],
      outcome: '',
      winner: '',
      occupants: new MapSchema<InstanceType<typeof Occupant>>(),
    })

    // White has twenty moves before anybody has done anything, and a board
    // that showed none of them until the second move would look broken.
    this.publishLegalMoves()

    this.onMessage('move', (client, message: { from?: string; to?: string; promotion?: string }) => {
      this.play(client, message)
    })
  }

  /**
   * A move, or a refusal.
   *
   * Every reason to say no is checked here rather than relied upon in the
   * screen. A screen that greys out the wrong squares is a bug; a screen that
   * could make an illegal move stand would be a different game.
   */
  private play(client: Client, move: { from?: string; to?: string; promotion?: string }): void {
    const ticket = this.seats.get(client.sessionId)

    if (!ticket) {
      return
    }

    if (this.state.outcome !== '') {
      client.send('refused', { because: 'That game is over.' })

      return
    }

    if (!SEATS.includes(ticket.seat as (typeof SEATS)[number])) {
      client.send('refused', { because: 'You are watching, not playing.' })

      return
    }

    if (ticket.seat !== this.state.turn) {
      client.send('refused', { because: 'It is not your turn.' })

      return
    }

    try {
      /*
       * chess.js throws on an illegal move rather than returning null, and
       * that is the whole enforcement: a browser can ask for anything and only
       * what the rules allow changes the position.
       */
      const played = this.game.move({
        from: String(move?.from),
        to: String(move?.to),
        promotion: move?.promotion ? String(move.promotion) : 'q',
      })

      this.state.moves.push(played.san)
    } catch {
      client.send('refused', { because: 'That is not a legal move.' })

      return
    }

    this.state.fen = this.game.fen()
    this.state.turn = this.game.turn() === 'w' ? 'white' : 'black'

    this.publishLegalMoves()

    this.settleIfOver()
  }

  /**
   * Deciding it is over, and saying how.
   *
   * Only the outcome is written into state. Turning that into a record is the
   * venue's job, and the venue asks — this room never calls out, holds no
   * credential, and could not be believed if it did.
   */
  /**
   * What may be played from here.
   *
   * Recomputed rather than adjusted, because a list of legal moves that is
   * patched as the game goes on is a rules engine, and there is already one
   * of those two lines up.
   */
  private publishLegalMoves(): void {
    this.state.legal.splice(0)

    if (this.game.isGameOver()) {
      return
    }

    for (const move of this.game.moves({ verbose: true })) {
      this.state.legal.push(`${move.from}${move.to}`)
    }
  }

  private settleIfOver(): void {
    if (!this.game.isGameOver()) {
      return
    }

    this.state.turn = ''
    this.state.legal.splice(0)

    if (this.game.isCheckmate()) {
      this.state.outcome = 'checkmate'

      // chess.js reports whose turn it is; in checkmate that side has lost.
      this.state.winner = this.game.turn() === 'w' ? 'black' : 'white'

      return
    }

    this.state.outcome = this.game.isStalemate()
      ? 'stalemate'
      : this.game.isInsufficientMaterial()
        ? 'insufficient material'
        : this.game.isThreefoldRepetition()
          ? 'repetition'
          : 'draw'

    this.state.winner = ''
  }

  protected seated(client: Client, ticket: Ticket): void {
    client.send('seated', { seat: ticket.seat, watching: !SEATS.includes(ticket.seat as never) })
  }
}

export default { name: 'com.streetmesh.games.chess', room: ChessRoom }
