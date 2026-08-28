export type ManagerRef = {
    id: number;
    name: string;
    /** 1 or 2. Also the column on the snake board. */
    slot: number;
};

export type CastawayCard = {
    id: number;
    name: string;
    age: number | null;
    occupation: string | null;
    tribe: string | null;
    photo_url: string | null;
    status: 'in' | 'out';
    exit_episode: number | null;
    points: number;
    owner_slot: number | null;
    owner_name: string | null;
    /** For the tile badge, where a full name will not fit. */
    owner_initial: string | null;
};

export type AvailableCastaway = {
    id: number;
    name: string;
    age: number | null;
    occupation: string | null;
    tribe: string | null;
    photo_url: string | null;
};

export type DraftPickRecord = {
    pick_number: number;
    round: number;
    manager_slot: number;
    manager_name: string;
    castaway: {
        id: number;
        name: string;
        tribe: string | null;
        age: number | null;
    };
};

export type RosterEntry = {
    id: number;
    name: string;
    points: number;
    status: 'in' | 'out';
    exit_episode: number | null;
};

export type ManagerTotalRecord = ManagerRef & {
    points: number;
    bar_percent: number;
};

export type RosterRecord = ManagerRef & {
    alive: number;
    castaways: RosterEntry[];
};

export type EpisodeGap = {
    episode: number;
    /** Signed from draft slot 1's point of view. */
    gap: number;
    leader_slot: number | null;
    totals: Record<number, number>;
};
