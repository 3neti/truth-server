# TruthElection CLI Manual

## Introduction

The `TruthElection` command-line interface (CLI) serves as the human-readable interface layer between the **Augmented Reality Ballot Appreciation System** (via Computer Vision) and the **TruthLedger** (our persistent blockchain-style election record store). From scanning ballots to producing signed, serialized, and auditable election returns (ER), this CLI translates the physical into the immutable.

It bridges:
- **Computer Vision Input** → via `ReadVote` and `FinalizeBallot`
- **Precinct Context + Mappings** → via YAML/JSON config files
- **Blockchain-Ready Output** → JSON payloads, PDFs, encoded QR lines

This guide explains **each CLI command**, **stream/manual input support**, **dependencies**, and **output artifacts**.

---

## Configuration Requirements

Before running any commands, ensure the following files are present and valid:

- `election.json`: Master metadata for the election (precinct code, positions, etc.)
- `precinct.yaml`: Configuration for your current precinct (voting limits, BEIs, etc.)
- `mappings.yaml`: Mapping of ballot keys (A1, B3) to candidate codes per position

Place them in a readable location and reference them in your `ElectionStoreInterface` or `boot()` method.

---

## CLI Commands

### 1. `election:setup`

```bash
php artisan election:setup-precinct --fresh
```

#### Purpose:
Initializes the election environment. Use `--fresh` to reset all cached state (ballot marks, tallies, signatures).

#### What Happens:
- Loads election.json
- Loads precinct.yaml and mappings.yaml
- Initializes in-memory or persistent `ElectionStore`

#### Caution:
Don’t use `--fresh` in production unless explicitly resetting.

---

### 2. `election:read-vote`

```bash
php artisan election:read-vote {ballot_code} {ballot_key}
```

#### Purpose:
Records a single mark on a ballot.

#### Example:
```bash
php artisan election:read-vote BAL-001 A1
```

#### What Happens:
- Adds mark `A1` to ballot `BAL-001`
- Internally resolves candidate via `mappings.yaml`
- Ballot remains open until finalized

---

### 3. `election:finalize-ballot`

```bash
php artisan election:finalize-ballot {ballot_code}
```

#### Purpose:
Finalizes a ballot and resolves all votes into structured `BallotData`.

#### What Happens:
- Fetches all marks on the ballot
- Resolves them via `MappingContext`
- Submits the structured vote to the precinct via `SubmitBallot`

---

### 4. `election:cast-ballot`

```bash
echo "BAL-002|PRESIDENT:A1,MAYOR:B3" | php artisan election:cast-ballot
```

#### Purpose:
Streams a structured vote via pipe input.

#### Format:
```
{ballot_code}|{POSITION}:{CANDIDATE_KEY},{POSITION}:{CANDIDATE_KEY}
```

#### What Happens:
- Parses the vote
- Converts to `BallotData`
- Submits instantly

#### Use Case:
Batch processing post-CV scan (ideal for QR/AR pipelines)

---

### 5. `election:attest-return`

```bash
php artisan election:attest-return BEI:uuid:signature
```

#### Purpose:
Records a BEI's digital signature to the precinct result.

#### What Happens:
- Appends attestation to precinct ER
- Required for ER finalization

---

### 6. `election:record-statistics`

```bash
php artisan election:record-statistics --json='{"registered_voters_count":100,"ballots_in_box_count":99}'
```

#### Purpose:
Records statistics for reconciliation (turnout, etc.)

#### Optional:
Stream via pipe:
```bash
echo '{"registered_voters_count":100}' | php artisan election:record-statistics
```

---

### 7. `election:tally-votes`

```bash
echo "123456" | php artisan election:tally-votes
```

#### Purpose:
Triggers final tally generation (authorization may be required)

#### What Happens:
- Computes total votes per candidate per position
- Stores internal `ElectionReturnData`

---

### 8. `election:wrapup-voting`

```bash
php artisan election:wrapup-voting --disk=local --dir=final --payload=minimal --max_chars=1200
```

#### Purpose:
Finalizes precinct-level election return and saves outputs

#### What Happens:
- Signs the election return (must be attested)
- Generates:
    - ✅ **Election Return JSON** (`ER-XXXX.json`)
    - ✅ **PDF Printout** (with full layout)
    - ✅ **QR-encoded Lines** (chunked to fit character limit)

---

## Output Artifacts

- **ElectionReturnData (JSON)** – machine-readable, blockchain-storable
- **PDF** – printable ER for human + legal inspection
- **QR Payloads** – multiple encoded chunks, decodable offline

---

## Streamed vs Manual Input

All vote entry commands (`cast-ballot`, `record-statistics`, `tally-votes`) support **streamed input** for bulk automation:

```bash
cat votes.txt | php artisan election:cast
```

But can also be run manually per input:

```bash
php artisan election:cast-ballot
> BAL-003|PRESIDENT:A2,GOVERNOR:B5
```

---

## Conclusion

The `TruthElection` CLI is your bridge between **ballot appreciation** and **truth persistence**. With configurable inputs, human-verifiable outputs, and automation-ready streams — you now have a full precinct-level stack to support secure, verifiable, and transparent elections.

For more details, see: `docs/`, or explore `TruthElection\Support` classes.

---

**End of Manual**
