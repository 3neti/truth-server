/* Core entity types */

// election.ts (or a nearby types file)

/** Matches App\Data\ElectoralInspectorData */
export interface ElectoralInspectorData {
    id: string
    name: string
    /** e.g. 'chairperson' | 'member' (kept string for forward-compat) */
    role?: string | null
}

/** Matches App\Data\PrecinctData one-to-one */
export interface PrecinctData {
    id: string
    code: string
    location_name: string
    latitude: number
    longitude: number
    electoral_inspectors: ElectoralInspectorData[]

    watchers_count?: number | null
    precincts_count?: number | null
    registered_voters_count?: number | null
    actual_voters_count?: number | null
    ballots_in_box_count?: number | null
    unused_ballots_count?: number | null
    spoiled_ballots_count?: number | null
    void_ballots_count?: number | null
}

export interface CandidateData {
    code: string
    name?: string
    alias?: string
}

export interface VoteData {
    position_code?: string
    position?: { code: string }
    candidate_codes?: CandidateData[]
    candidates?: CandidateData[]
}

export interface BallotData {
    id: string
    code: string
    votes: VoteData[]
}

export interface TallyData {
    position_code: string
    candidate_code: string
    candidate_name: string
    count: number
}

    export interface ElectionReturnData {
        id: string
        code: string
        precinct: PrecinctData
        tallies: TallyData[]
        ballots?: BallotData[]
        last_ballot?: BallotData
        signatures?: Array<{ id?: string; name?: string; role?: string | null; signed_at?: string | null }>
    }

/* Optional: small runtime guards for safer manual testing */
export function isElectionReturnData(x: unknown): x is ElectionReturnData {
    const er = x as any
    return !!er
        && typeof er.id === 'string'
        && typeof er.code === 'string'
        && er.precinct && typeof er.precinct === 'object'
        && Array.isArray(er.tallies)
}
