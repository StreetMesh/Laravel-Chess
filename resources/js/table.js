/**
 * The board, as a view of what the hub says.
 *
 * This holds no opinion about the rules. It draws the position the room reports
 * and asks for what somebody clicked; whether that is a legal move is decided
 * by the referee, and the answer comes back as new state or as a refusal. A
 * board that knew the rules would be a second implementation of them, and two
 * implementations are two chances to disagree about who won.
 *
 * So there is no chess in this file. There is a grid, a click, and a socket.
 */

import { Client } from 'colyseus.js'

const GLYPHS = {
    k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟',
}

const FILES = 'abcdefgh'

/**
 * A FEN position as sixty-four squares, in the order they are drawn.
 *
 * Reading FEN is not knowing the rules — it is reading a photograph of the
 * board. Nothing here can tell whether a position is legal or whose turn it is.
 */
function squaresFrom(fen, flipped) {
    const rows = (fen || '').split(' ')[0].split('/')
    const cells = []

    rows.forEach((row, rank) => {
        let file = 0

        for (const character of row) {
            if (/\d/.test(character)) {
                file += Number(character)

                continue
            }

            cells.push({
                rank,
                file,
                name: FILES[file] + (8 - rank),
                white: character === character.toUpperCase(),
                glyph: GLYPHS[character.toLowerCase()] ?? '',
            })

            file += 1
        }

        // Empty squares are drawn too, and FEN only counts them.
        for (let f = 0; f < 8; f++) {
            if (!cells.some((cell) => cell.rank === rank && cell.file === f)) {
                cells.push({ rank, file: f, name: FILES[f] + (8 - rank), white: false, glyph: '' })
            }
        }
    })

    cells.sort((a, b) => a.rank - b.rank || a.file - b.file)

    /*
     * Black plays from the other end. Rotating the drawing rather than the
     * position, so that a square is called the same thing on both screens.
     */
    const ordered = flipped ? [...cells].reverse() : cells

    return ordered.map((cell) => ({ ...cell, dark: (cell.rank + cell.file) % 2 === 1 }))
}

export default function chessTable(ticketUrl, seat) {
    return {
        seat,
        squares: squaresFrom('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR', seat === 'black'),
        moves: [],
        status: 'Connecting…',
        trouble: '',
        selected: null,
        over: false,
        room: null,

        async init() {
            let admitted

            try {
                const response = await fetch(ticketUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'application/json',
                    },
                })

                admitted = await response.json()

                if (!response.ok) {
                    throw new Error(admitted.error ?? 'The venue would not let you in.')
                }
            } catch (refused) {
                this.trouble = refused.message
                this.status = ''

                return
            }

            try {
                this.room = await new Client(admitted.hub).joinOrCreate(
                    admitted.experience.replaceAll('.', '_'),
                    { ticket: admitted.ticket, room: admitted.room },
                )
            } catch (refused) {
                this.trouble = 'Could not reach the table.'
                this.status = ''

                return
            }

            this.status = ''

            this.room.onStateChange((state) => {
                this.squares = squaresFrom(state.fen, this.seat === 'black')
                this.moves = [...state.moves]
                this.over = state.outcome !== ''

                this.status = state.outcome
                    ? state.winner
                        ? `${state.winner} wins by ${state.outcome}`
                        : `Drawn — ${state.outcome}`
                    : `${state.turn} to move`
            })

            // A refusal is an answer, and belongs on screen rather than in a log.
            this.room.onMessage('refused', ({ because }) => {
                this.trouble = because
                setTimeout(() => (this.trouble = ''), 2500)
            })

            this.room.onLeave(() => {
                this.status = 'Disconnected'
            })
        },

        /**
         * Two clicks: what to move, and where to.
         *
         * Deliberately not validated here. Clicking a square that cannot move
         * asks anyway, and the referee says no — which is one code path instead
         * of two and cannot drift from the rules.
         */
        choose(square) {
            if (!this.seat || this.over) {
                return
            }

            if (this.selected === null) {
                this.selected = square

                return
            }

            if (this.selected === square) {
                this.selected = null

                return
            }

            this.room?.send('move', { from: this.selected, to: square })
            this.selected = null
        },
    }
}
