/**
 * Draft board geometry.
 *
 * The order is straight alternating — every round runs slot 1 then slot 2, so
 * the board is a ladder rather than a serpentine: one row per round, the pair
 * joined by a connector, and a spine down the left gutter that fills as the
 * rounds complete.
 *
 * The board is fluid: every measurement is derived from the width its container
 * actually has, and the cell positions, the connectors and the spine all come
 * out of the same `geometryFor()` result so they cannot drift apart.
 *
 * Only the horizontal axis flexes. Row pitch and cell height stay fixed, so the
 * type inside a cell never scales down to something unreadable.
 */
const CELL_WIDTH_RATIO = 220 / 560;
const COLUMN_GAP_RATIO = 60 / 220;
const MIN_CELL_WIDTH = 132;
const MAX_CELL_WIDTH = 260;

/** Left gutter holding the round number and the progress spine. */
const GUTTER = 38;
const NARROW_GUTTER = 24;

export const CELL_HEIGHT = 46;
export const ROW_STEP = 62;
export const FIRST_ROW_Y = 31;

/** Below this a cell only has room for the pick number and the name. */
export const COMPACT_CELL_WIDTH = 168;

export type BoardGeometry = {
    width: number;
    height: number;
    gutter: number;
    /** Centre of the round-number gutter and the progress spine. */
    gutterX: number;
    columnA: number;
    columnB: number;
    cellWidth: number;
    cellHeight: number;
    rows: number;
    compact: boolean;
};

export type BoardColumn = 'A' | 'B';

export type BoardCell = {
    pickNumber: number;
    round: number;
    column: BoardColumn;
    /** Draft slot that owns this cell. Column position and slot are the same thing. */
    slot: number;
    left: number;
    top: number;
    centreX: number;
    centreY: number;
};

export const rowY = (round: number): number =>
    FIRST_ROW_Y + ROW_STEP * (round - 1);

export const geometryFor = (
    containerWidth: number,
    totalPicks: number,
): BoardGeometry => {
    const width = Math.max(containerWidth, MIN_CELL_WIDTH * 2 + 24);
    const gutter = width < 420 ? NARROW_GUTTER : GUTTER;
    const usable = width - gutter;
    const rows = Math.max(1, Math.ceil(totalPicks / 2));
    const cellWidth = Math.min(
        MAX_CELL_WIDTH,
        Math.max(MIN_CELL_WIDTH, usable * CELL_WIDTH_RATIO),
    );

    // The columns sit off the cell width rather than the container, so the gap
    // between them stays in proportion instead of stretching into a long empty
    // run on a wide screen. The gutter travels with the cells rather than being
    // pinned to the container edge, so the spine never drifts away from them.
    const columnGap = cellWidth * COLUMN_GAP_RATIO;
    const blockWidth = gutter + cellWidth * 2 + columnGap;
    const blockLeft = Math.max(0, (width - blockWidth) / 2);

    return {
        width,
        height: FIRST_ROW_Y * 2 + ROW_STEP * (rows - 1),
        gutter,
        gutterX: blockLeft + gutter / 2,
        columnA: blockLeft + gutter + cellWidth / 2,
        columnB: blockLeft + gutter + cellWidth * 1.5 + columnGap,
        cellWidth,
        cellHeight: CELL_HEIGHT,
        rows,
        compact: cellWidth < COMPACT_CELL_WIDTH,
    };
};

/**
 * Slot 1 takes the odd picks and slot 2 the even ones. Column position is the
 * same thing as draft slot, which is what carries ownership without colour.
 */
export const columnFor = (pickNumber: number): BoardColumn =>
    pickNumber % 2 === 1 ? 'A' : 'B';

export const cellFor = (
    pickNumber: number,
    geometry: BoardGeometry,
): BoardCell => {
    const round = Math.ceil(pickNumber / 2);
    const column = columnFor(pickNumber);
    const centreX = column === 'A' ? geometry.columnA : geometry.columnB;

    return {
        pickNumber,
        round,
        column,
        slot: column === 'A' ? 1 : 2,
        left: centreX - geometry.cellWidth / 2,
        top: rowY(round) - geometry.cellHeight / 2,
        centreX,
        centreY: rowY(round),
    };
};

export const boardCells = (
    totalPicks: number,
    geometry: BoardGeometry,
): BoardCell[] =>
    Array.from({ length: totalPicks }, (_, index) =>
        cellFor(index + 1, geometry),
    );

export type BoardRow = {
    round: number;
    y: number;
    /** Connector between the two cells of this round. */
    from: number;
    to: number;
};

export const boardRows = (
    totalPicks: number,
    geometry: BoardGeometry,
): BoardRow[] =>
    Array.from({ length: geometry.rows }, (_, index) => {
        const round = index + 1;

        return {
            round,
            y: rowY(round),
            from: geometry.columnA + geometry.cellWidth / 2,
            to: geometry.columnB - geometry.cellWidth / 2,
        };
    }).filter((row) => row.round * 2 - 1 <= totalPicks);

/**
 * How far down the spine the draft has got, so the gutter line doubles as the
 * progress bar the way the serpentine thread used to.
 */
export const spineProgressY = (
    picksMade: number,
    totalPicks: number,
): number => {
    if (picksMade <= 0) {
        return rowY(1);
    }

    const reached = Math.min(picksMade + 1, totalPicks);

    return rowY(Math.ceil(reached / 2));
};
