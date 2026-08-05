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
import { capture, drop, lift, permit, place } from './sounds.js'

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

/**
 * How a finished game reads, from where you were sitting.
 *
 * One sentence and one place that writes it, because the live board and a game
 * opened later say the same thing and used to say it in two languages — one in
 * Blade from the record, one here from the room.
 */
export function ending(outcome, winner, seat) {
    if (!outcome) {
        return 'This game is over'
    }

    if (seat) {
        if (!winner) {
            return `You drew by ${outcome}`
        }

        return seat === winner ? `You won by ${outcome}` : `You lost by ${outcome}`
    }

    return winner ? `${winner.charAt(0).toUpperCase()}${winner.slice(1)} won by ${outcome}` : `Drawn by ${outcome}`
}

/**
 * A finished game, walked through.
 *
 * Reads positions the room recorded while it was being played rather than
 * working them out, so nothing here knows the rules. Deriving them would be a
 * second implementation of chess for the sake of reading a game that has
 * already been decided.
 *
 * It answers the four questions the board asks of whatever is holding it, and
 * the answers are all "no": nothing is yours to move, nothing is selected,
 * nothing is a target, and clicking does nothing.
 */
export function chessReplay({ positions, moves, seat, outcome, winner, white, black }) {
    return {
        positions,
        moves,
        seat,
        outcome,
        winner,
        players: { white, black },
        knight: artwork('n'),
        over: true,
        at: 0,

        /*
         * Nobody is to move in a finished game, and the sides are drawn the same
         * way round as they were played.
         */
        turn: '',
        playing: false,
        timer: null,

        squares: [],
        myMove: false,
        selected: null,

        isTarget() {
            return false
        },

        inCheck() {
            return false
        },

        choose() {},

        get ending() {
            return ending(this.outcome, this.winner, this.seat)
        },

        /**
         * Which side is drawn at the top, which is whoever you are not — and
         * black for somebody who played neither, the way a board is drawn when
         * nobody in particular is looking at it.
         */
        get far() {
            return this.seat === 'black' ? 'white' : 'black'
        },

        get near() {
            return this.seat === 'black' ? 'black' : 'white'
        },

        init() {
            /*
             * Where the game finished, not where it started.
             *
             * Opening a finished game on the opening position shows a board
             * nobody was looking at — the position everybody remembers is the
             * one it ended on, and it is the answer to "what happened" that the
             * heading above has just given in words.
             *
             * Replay winds back to the start; arriving does not.
             */
            this.show(this.last)
        },

        get last() {
            return Math.max(0, this.positions.length - 1)
        },

        /**
         * The move that led to the position being shown, or nothing at the
         * start — there are one more positions than moves, because the first
         * one is the board before anybody had done anything.
         */
        get playedHere() {
            return this.at > 0 ? this.moves[this.at - 1] : ''
        },

        show(index) {
            this.at = Math.min(Math.max(index, 0), this.last)
            this.squares = squaresFrom(this.positions[this.at] ?? '', seat === 'black')
        },

        step(by) {
            this.stop()
            this.show(this.at + by)
        },

        /**
         * The same two sounds the live board makes, for the same reason: a move
         * you can hear is a move you noticed.
         */
        advance() {
            if (this.at >= this.last) {
                this.stop()

                return
            }

            this.show(this.at + 1)

            this.playedHere.includes('x') ? capture() : place()
        },

        play() {
            permit()

            if (this.playing) {
                this.stop()

                return
            }

            // Watching from the end means watching it again — which is the
            // ordinary case now, since a finished game opens on its last
            // position rather than its first.
            if (this.at >= this.last) {
                this.show(0)
            }

            this.playing = true
            this.timer = setInterval(() => this.advance(), 900)
        },

        stop() {
            this.playing = false
            clearInterval(this.timer)
            this.timer = null
        },

        // Nothing here outlives the page, but a timer left running after
        // navigating away is a timer still making noises.
        destroy() {
            this.stop()
        },
    }
}

export default function chessTable({ ticketUrl, settleUrl, seat, invitation, white, black }) {
    return {
        seat,
        squares: squaresFrom('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR', seat === 'black'),
        moves: [],
        status: 'Connecting…',
        trouble: '',
        selected: null,
        over: false,
        turn: 'white',

        /** The side whose king is under attack, as the room reports it. */
        check: '',

        /**
         * How many of the two chairs have somebody in them.
         *
         * Counted off the room rather than the venue's seats, because the
         * question is whether there is a game on rather than who is entitled to
         * one. Somebody who has opened a table and is waiting has a chair and no
         * opponent, and there is nothing there to resign.
         */
        here: 0,

        /** How it ended, once it has. */
        outcome: '',
        winner: '',

        /** What the invitation says once it has been sent or copied. */
        invited: '',

        /**
         * Who is playing each side, by the name their own server gave them.
         *
         * Seeded from the venue's seats and kept up to date by the room, which
         * is the difference between the two: a seat is a right to a chair and
         * survives somebody closing a tab, while the room only knows who is
         * connected. A name is never cleared once known — an opponent who has
         * dropped out for a moment is still who you are playing.
         */
        players: { white, black },

        /**
         * The position after every move, kept as they arrive.
         *
         * So that a game ending turns this board into a replay of itself
         * without the page being loaded again — the room has been sending
         * these all along and nothing here was keeping them.
         */
        positions: [],
        at: 0,
        reviewing: false,
        playing: false,
        timer: null,

        /**
         * Every move available right now, as `e2e4`, exactly as the room sent
         * it. Not derived here — see `isTarget`.
         */
        legal: [],
        room: null,
        settling: false,

        /**
         * How many moves we have already made a noise about.
         *
         * Arriving at a game in progress delivers every move at once, and
         * playing a sound for each would be a rattle rather than a board. This
         * starts at whatever was already there and only reacts to what comes
         * after.
         */
        heard: 0,

        /**
         * Whether we have seen the room's state at all yet.
         *
         * The first delivery is the game so far, however much of it there is,
         * and none of it just happened. Without this the board announced the
         * last move of a game you had only just opened — and because a browser
         * will not make a noise before you touch it, that sound sat waiting and
         * came out on the first click, sounding like a move nobody had made.
         */
        synced: false,

        /**
         * A knight, for the line saying which side you are playing.
         *
         * The same artwork the board draws, taken from the same table. A second
         * copy of a path would be a second thing to keep in step for no reason.
         */
        knight: artwork('n'),

        async init() {
            /*
             * Somebody who has followed an invitation has no place here yet, so
             * there is no ticket to ask for and nothing to join. The board they
             * are looking at is a still one and the only thing on offer is a
             * chair.
             *
             * Asking anyway is how this screen used to greet them: the venue
             * answered "That visitor has no place there", and a page told a
             * stranger they were trespassing on a game they had been invited
             * to.
             */
            if (!ticketUrl) {
                this.status = ''

                return
            }

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
                this.moves = [...state.moves]
                this.positions = [...state.positions]

                this.sound()
                this.legal = [...state.legal]
                this.turn = state.turn
                this.check = state.check
                this.outcome = state.outcome
                this.winner = state.winner

                let seated = 0
                state.occupants?.forEach((who) => {
                    if (!who.seat) {
                        return
                    }

                    seated += 1
                    this.players[who.seat] = who.name
                })
                this.here = seated
                this.over = state.outcome !== ''

                this.status = this.over ? '' : `${state.turn} to move`

                /*
                 * A game that has just ended becomes a record of itself, here,
                 * without the page being loaded again: the board stops taking
                 * moves, the ending is said in one line, and the whole thing
                 * can be played back.
                 *
                 * Only on the way in. Once somebody is stepping through it,
                 * later state must not drag the board back to the end under
                 * them.
                 */
                if (this.over && !this.reviewing) {
                    this.reviewing = true
                    this.show(this.last)
                } else if (!this.reviewing) {
                    this.squares = squaresFrom(state.fen, this.seat === 'black')
                }

                if (this.over) {
                    this.settle()
                }
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
         * Ask somebody to play.
         *
         * Through the operating system's own share sheet where there is one, so
         * the invitation goes wherever that person actually talks to their
         * opponent rather than through anything this venue would have to run.
         *
         * The sentence and the address are handed over separately because that
         * is what a share sheet expects: a target that can make a link out of a
         * URL does, and one that cannot puts the two together itself. Pasting
         * the address into the sentence as well would show it twice in most of
         * them.
         *
         * Where there is no share sheet — most desktop browsers — the whole
         * thing goes on the clipboard instead, which is what somebody was going
         * to do with it anyway.
         */
        async invite() {
            const sentence = `Hey, let's play Chess.`

            try {
                if (navigator.share) {
                    await navigator.share({ title: 'Chess', text: sentence, url: invitation })

                    return
                }

                await navigator.clipboard.writeText(`${sentence} ${invitation}`)
                this.invited = 'Link copied'
            } catch (cancelled) {
                // Dismissing the share sheet throws, and is not a failure — it
                // is somebody deciding not to. Nothing to say about it.
                return
            }

            setTimeout(() => (this.invited = ''), 2500)
        },

        /**
         * Tell the venue there is something to write down.
         *
         * The hub cannot do this itself: it decided the result and holds no key
         * to sign it with, so the record has to be written by the venue. All
         * this says is "go and look" — what happened comes from the hub when
         * the venue asks, so nothing here is trusted with the outcome.
         *
         * Once per board. Both players and every watcher will say it, which is
         * the point: it only takes one of them still having the page open, and
         * the venue ignores the rest.
         */
        async settle() {
            if (this.settling) {
                return
            }

            this.settling = true

            try {
                await fetch(settleUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'application/json',
                    },
                })
            } catch (unreachable) {
                // The venue keeps the game open, so the next person to open the
                // board asks again. Nothing is lost by this failing quietly.
            }
        },

        /**
         * Let go of the seat when this component goes away.
         *
         * Navigating away with `wire:navigate` never unloads the page, so
         * nothing closes the socket on its own — the old connection sits in the
         * room holding the chair, and coming back is refused as somebody
         * already sitting there. A full reload appeared to fix it because
         * unloading is the only thing that was closing anything.
         *
         * The hub no longer depends on this happening; a browser that crashes
         * cannot run it, and that has to work too.
         */
        destroy() {
            this.stop()
            this.room?.leave()
            this.room = null
        },

        get last() {
            return Math.max(0, this.positions.length - 1)
        },

        get playedHere() {
            return this.at > 0 ? this.moves[this.at - 1] : ''
        },

        get ending() {
            return ending(this.outcome, this.winner, this.seat)
        },

        /**
         * Which side is drawn at the top, which is whoever you are not.
         *
         * The board turns around for black, so the far side of it is the
         * opponent — and for somebody watching, who has no side, it is black,
         * the way a board is drawn when nobody in particular is looking at it.
         */
        get far() {
            return this.seat === 'black' ? 'white' : 'black'
        },

        get near() {
            return this.seat === 'black' ? 'black' : 'white'
        },

        show(index) {
            this.at = Math.min(Math.max(index, 0), this.last)
            this.squares = squaresFrom(this.positions[this.at] ?? '', this.seat === 'black')
        },

        step(by) {
            this.stop()
            this.show(this.at + by)
        },

        advance() {
            if (this.at >= this.last) {
                this.stop()

                return
            }

            this.show(this.at + 1)
            this.playedHere.includes('x') ? capture() : place()
        },

        play() {
            permit()

            if (this.playing) {
                this.stop()

                return
            }

            if (this.at >= this.last) {
                this.show(0)
            }

            this.playing = true
            this.timer = setInterval(() => this.advance(), 900)
        },

        stop() {
            this.playing = false
            clearInterval(this.timer)
            this.timer = null
        },

        /**
         * One noise per move, and only ever one of the two.
         *
         * Which one is already written in the move itself: chess notation puts
         * an `x` in a capture and nothing in an ordinary move, so the room does
         * not have to say and this does not have to work it out from the
         * position.
         *
         * Both players hear it, including the one who made it — a move you
         * cannot hear yourself make feels like it did not land.
         */
        sound() {
            const arrived = this.moves.length

            // Only the last one, however many turned up — and nothing at all
            // for the first delivery, which is the game so far rather than
            // anything that has just happened.
            if (this.synced && arrived > this.heard) {
                this.moves[arrived - 1].includes('x') ? capture() : place()
            }

            this.synced = true
            this.heard = arrived
        },

        /**
         * Giving up, which is the one ending a player decides on their own.
         *
         * The room still knows how to conclude a game by agreement — a draw is
         * a real chess ending and the referee should be able to record one —
         * but nothing on this screen offers it, so nothing here asks.
         */
        resign() {
            this.room?.send('resign')
        },

        /**
         * Whether this browser may touch the board at all.
         *
         * Watchers have no seat, and the player who is not to move has nothing
         * to do — letting them pick pieces up produces a selection that can
         * only ever be refused, which reads as the board being broken rather
         * than as it not being your turn.
         *
         * This is politeness, not enforcement. The room refuses a move from
         * the wrong seat regardless, and has to: this runs on somebody else's
         * computer.
         */
        get myMove() {
            return Boolean(this.seat) && !this.over && this.turn === this.seat
        },

        /**
         * Whether this square holds the king that is currently in trouble.
         *
         * Which side is in check comes from the room; which square that king is
         * on is read off the position we are already drawing. Neither is worked
         * out here — noticing the `+` on a move would say a check happened
         * without saying whose king it was.
         */
        inCheck(cell) {
            return this.check !== '' && cell.piece?.name === 'king' && (cell.white ? 'white' : 'black') === this.check
        },

        /**
         * Whether a square is somewhere the selected piece may go.
         *
         * A lookup in what the room published, not a calculation. Working it
         * out here would be a second implementation of chess, and two
         * implementations are two chances to disagree about who won.
         */
        isTarget(square) {
            return this.selected !== null && this.legal.includes(this.selected + square)
        },

        /**
         * Whether there is any move at all from a square.
         *
         * What makes a first click land on a piece rather than on scenery —
         * and it distinguishes your own pieces from your opponent's without
         * knowing which is which, because only the side to move has moves.
         */
        canMoveFrom(square) {
            return this.legal.some((move) => move.startsWith(square))
        },

        /**
         * Two clicks: what to move, and where to.
         *
         * The second click is deliberately not validated. Clicking a square
         * that cannot be reached asks anyway and the referee says no — one code
         * path instead of two, and it cannot drift from the rules.
         */
        choose(square) {
            /*
             * Whatever else this click does, it is the interaction a browser is
             * waiting for before it will let a page make any sound at all.
             * Without it the first thing anybody would hear is their opponent's
             * move, which arrives over a socket and is not an interaction.
             */
            permit()

            if (!this.myMove) {
                return
            }

            // Putting back the piece you were holding.
            if (this.selected === square) {
                this.selected = null
                drop()

                return
            }

            // Picking up, or changing your mind about which piece. A square
            // with no moves is not a piece you can play, so it does not become
            // a selection that could only ever be refused.
            if (this.selected === null || this.canMoveFrom(square)) {
                const picking = this.canMoveFrom(square)

                this.selected = picking ? square : null

                // Only when something is actually in your hand. Clicking empty
                // board is not picking anything up, and putting a piece back
                // down is not either.
                if (picking) {
                    lift()
                }

                return
            }

            this.room?.send('move', { from: this.selected, to: square })
            this.selected = null
        },
    }
}
