
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

## CLI Commands + HTTP Equivalents

### 1. Setup Precinct

**CLI:**

```bash
php artisan election:setup-precinct --fresh
```

**HTTP:**

```bash
curl -X POST http://localhost/setup-precinct
```

---

### 2. Read Vote

**CLI:**

```bash
php artisan election:read-vote BALLOT-001 A1
```

**HTTP:**

```bash
curl -X POST http://localhost/read-vote   -d 'ballot_code=BALLOT-001' -d 'mark=A1'
```

---

### 3. Finalize Ballot

**CLI:**

```bash
php artisan election:finalize-ballot BALLOT-001
```

**HTTP:**

```bash
curl -X POST http://localhost/finalize-ballot   -d 'ballot_code=BALLOT-001'
```

---

### 4. Cast Ballot

**CLI:**

```bash
echo "BAL-002|PRESIDENT:A1,MAYOR:B3" | php artisan election:cast-ballot
```

**HTTP:**

```bash
curl -X POST http://localhost/cast-ballot   -d 'ballot_string=BAL-002|PRESIDENT:A1,MAYOR:B3'
```

---

### 5. Tally Votes

**CLI:**

```bash
php artisan election:tally-votes
```

**HTTP:**

```bash
curl -X POST http://localhost/tally-votes
```

---

### 6. Attest Return

**CLI:**

```bash
php artisan election:attest-return BEI:uuid:signature
```

**HTTP:**

```bash
curl -X POST http://localhost/attest-return   -d 'attestation=BEI:uuid:signature'
```

---

### 7. Record Statistics

**CLI:**

```bash
php artisan election:record-statistics --json='{"registered_voters_count":100,"ballots_in_box_count":99}'
```

**HTTP:**

```bash
curl -X PATCH http://localhost/record-statistics   -H "Content-Type: application/json"   -d '{"registered_voters_count":100,"ballots_in_box_count":99}'
```

---

### 8. Wrap-up Voting

**CLI:**

```bash
php artisan election:wrapup-voting --disk=local --dir=final --payload=minimal --max_chars=1200
```

**HTTP:**

```bash
curl -X POST http://localhost/wrapup-voting
```

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
