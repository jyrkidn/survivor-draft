/**
 * The two manager colours, resolved from the Tailwind theme so the palette has
 * one home. Components set `--mgr` and then use `text-[color:var(--mgr)]`,
 * which keeps the class names static and the colour dynamic.
 */
export const managerColour = (slot?: number | null): string => {
    if (slot === 1) {
        return 'var(--color-mgr-a)';
    }

    if (slot === 2) {
        return 'var(--color-mgr-b)';
    }

    return 'var(--color-dead)';
};

export const managerColourBright = (slot?: number | null): string =>
    slot === 1 ? 'var(--color-mgr-a-bright)' : 'var(--color-mgr-b-bright)';

/** `+34`, `−4`, `0` — a real minus sign, because these sit in a mono column. */
export const signed = (value: number): string => {
    if (value > 0) {
        return `+${value}`;
    }

    if (value < 0) {
        return `−${Math.abs(value)}`;
    }

    return '0';
};

/** `09` — pick and episode numbers are always two digits. */
export const pad = (value: number): string => String(value).padStart(2, '0');
