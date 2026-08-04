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

/**
 * Piece artwork from Font Awesome Free, used under CC BY 4.0.
 *
 * Path data rather than the package, so that installing this experience stays
 * one step. The free set is solid-only, which decides how the two sides are
 * told apart: they share a silhouette and differ by fill and outline, the way
 * a real set does — not by outline-versus-fill, which is what the Unicode
 * chess glyphs offer and why they were the wrong tool here. A white piece
 * drawn as a white glyph on a light square is very nearly not drawn at all.
 *
 * The icons are 512 tall and between 320 and 512 wide, so each is centred with
 * a transform rather than given its own viewBox. A bound `:viewBox` would not
 * survive the HTML parser lowercasing it, and SVG treats `viewbox` as an
 * entirely different attribute — `transform` is already lowercase.
 */
const CANVAS = 512

const PIECES = {
    k: {
        name: 'king',
        width: 448,
        path: 'M224-32c17.7 0 32 14.3 32 32l0 32 32 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-32 0 0 64 153.8 0c21.1 0 38.2 17.1 38.2 38.2 0 6.4-1.6 12.7-4.7 18.3L352 384 408.2 454.3c5 6.3 7.8 14.1 7.8 22.2 0 19.6-15.9 35.5-35.5 35.5L67.5 512c-19.6 0-35.5-15.9-35.5-35.5 0-8.1 2.7-15.9 7.8-22.2L96 384 4.7 216.6C1.6 210.9 0 204.6 0 198.2 0 177.1 17.1 160 38.2 160l153.8 0 0-64-32 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l32 0 0-32c0-17.7 14.3-32 32-32z',
    },
    q: {
        name: 'queen',
        width: 512,
        path: 'M256 80a48 48 0 1 0 0-96 48 48 0 1 0 0 96zM5.5 185L128 384 71.8 454.3c-5 6.3-7.8 14.1-7.8 22.2 0 19.6 15.9 35.5 35.5 35.5l312.9 0c19.6 0 35.5-15.9 35.5-35.5 0-8.1-2.7-15.9-7.8-22.2L384 384 506.5 185c3.6-5.9 5.5-12.7 5.5-19.6l0-.6c0-20.3-16.5-36.8-36.8-36.8-7.3 0-14.4 2.2-20.4 6.2l-16.9 11.3c-12.7 8.5-29.6 6.8-40.4-4l-34.1-34.1C356.1 100.1 346.2 96 336 96s-20.1 4.1-27.3 11.3l-30.1 30.1c-12.5 12.5-32.8 12.5-45.3 0l-30.1-30.1C196.1 100.1 186.2 96 176 96s-20.1 4.1-27.3 11.3l-34.1 34.1c-10.8 10.8-27.7 12.5-40.4 4L57.3 134.2c-6.1-4-13.2-6.2-20.4-6.2-20.3 0-36.8 16.5-36.8 36.8l0 .6c0 6.9 1.9 13.7 5.5 19.6z',
    },
    r: {
        name: 'rook',
        width: 384,
        path: 'M0 32L0 133.5c0 17 6.7 33.3 18.7 45.3L64 224 64 384 7.8 454.3C2.7 460.6 0 468.4 0 476.5 0 496.1 15.9 512 35.5 512l312.9 0c19.6 0 35.5-15.9 35.5-35.5 0-8.1-2.7-15.9-7.8-22.2l-56.2-70.3 0-160 45.3-45.3c12-12 18.7-28.3 18.7-45.3L384 32c0-17.7-14.3-32-32-32L320 0c-17.7 0-32 14.3-32 32l0 32-48 0 0-32c0-17.7-14.3-32-32-32L176 0c-17.7 0-32 14.3-32 32l0 32-48 0 0-32C96 14.3 81.7 0 64 0L32 0C14.3 0 0 14.3 0 32z',
    },
    b: {
        name: 'bishop',
        width: 320,
        path: 'M64 384L48.3 368.3C17.4 337.4 0 295.4 0 251.7 0 213.1 13.5 175.8 38.2 146.1L106.7 64 96 64C78.3 64 64 49.7 64 32S78.3 0 96 0L224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-10.7 0 47.6 57.1-85.9 85.9c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l82.3-82.3c18.7 27.3 28.7 59.7 28.7 93 0 43.7-17.4 85.7-48.3 116.6L256 384 312.2 454.3c5 6.3 7.8 14.1 7.8 22.2 0 19.6-15.9 35.5-35.5 35.5L35.5 512c-19.6 0-35.5-15.9-35.5-35.5 0-8.1 2.7-15.9 7.8-22.2L64 384z',
    },
    n: {
        name: 'knight',
        width: 384,
        path: 'M192-32c106 0 192 86 192 192l0 133.5c0 17-6.8 33.2-18.7 45.2L320 384 370.8 434.7c8.5 8.5 13.2 20 13.2 32 0 25-20.3 45.2-45.2 45.3L45.3 512c-25 0-45.2-20.3-45.2-45.3 0-12 4.8-23.5 13.2-32L64 384 64 349.4c0-18.7 8.2-36.4 22.3-48.6l89.7-76.8-48 0-12.1 12.1c-12.7 12.7-30 19.9-48 19.9-37.5 0-67.9-30.4-67.9-67.9l0-8.7c0-22.8 8.2-44.9 23.1-62.3L96 32 96 0c0-17.7 14.3-32 32-32l64 0zM160 72a24 24 0 1 0 0 48 24 24 0 1 0 0-48z',
    },
    p: {
        name: 'pawn',
        width: 384,
        path: 'M192-32c66.3 0 120 53.7 120 120 0 27-8.9 51.9-24 72 17.7 0 32 14.3 32 32s-14.3 32-32 32l-10.7 0 26.7 160 56.2 70.3c5 6.3 7.8 14.1 7.8 22.2 0 19.6-15.9 35.5-35.5 35.5L51.5 512c-19.6 0-35.5-15.9-35.5-35.5 0-8.1 2.7-15.9 7.8-22.2L80 384 106.7 224 96 224c-17.7 0-32-14.3-32-32s14.3-32 32-32c-15.1-20.1-24-45-24-72 0-66.3 53.7-120 120-120z',
    },
}

const FILES = 'abcdefgh'

/**
 * One piece, ready to be drawn: the path, and what centres it.
 */
function artwork(symbol) {
    const piece = PIECES[symbol.toLowerCase()]

    if (!piece) {
        return null
    }

    return {
        name: piece.name,
        path: piece.path,
        transform: `translate(${(CANVAS - piece.width) / 2} 0)`,
    }
}

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
                piece: artwork(character),
            })

            file += 1
        }

        // Empty squares are drawn too, and FEN only counts them.
        for (let f = 0; f < 8; f++) {
            if (!cells.some((cell) => cell.rank === rank && cell.file === f)) {
                cells.push({ rank, file: f, name: FILES[f] + (8 - rank), white: false, piece: null })
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
