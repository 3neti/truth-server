#!/bin/bash
# Test ballot cast format integration

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

echo "========================================="
echo "Testing Ballot Cast Format Integration"
echo "========================================="
echo ""

# Run simulation with one normal scenario
echo "Step 1: Running simulation (normal scenario)..."
./scripts/simulation/run-simulation-laravel.sh \
    --scenarios normal \
    --fresh \
    > /dev/null 2>&1

if [[ $? -ne 0 ]]; then
    echo "✗ Simulation failed"
    exit 1
fi

echo "✓ Simulation completed"
echo ""

# Find latest run
LATEST_RUN=$(readlink storage/app/private/simulation/latest)
SCENARIO_DIR="storage/app/private/simulation/${LATEST_RUN}/scenarios/scenario-1-normal"

echo "Step 2: Checking generated files..."
echo "  Scenario: $SCENARIO_DIR"

if [[ ! -f "${SCENARIO_DIR}/votes.json" ]]; then
    echo "✗ votes.json not found"
    exit 1
fi

echo "✓ votes.json exists"
echo ""

echo "Step 3: Extracting ballot_cast_format..."
BALLOT_CAST_FORMAT=$(jq -r '.ballot_cast_format // empty' "${SCENARIO_DIR}/votes.json")

if [[ -z "$BALLOT_CAST_FORMAT" ]]; then
    echo "✗ No ballot_cast_format found in votes.json"
    echo ""
    echo "votes.json contents:"
    cat "${SCENARIO_DIR}/votes.json" | jq '.'
    exit 1
fi

echo "✓ ballot_cast_format found:"
echo "  $BALLOT_CAST_FORMAT"
echo ""

echo "Step 4: Testing with --cast-ballots flag..."
./scripts/simulation/run-simulation-laravel.sh \
    --scenarios normal \
    --cast-ballots \
    > /tmp/cast-test-output.log 2>&1

if [[ $? -eq 0 ]]; then
    echo "✓ Simulation with --cast-ballots completed"
    
    # Check if ballot was cast
    LATEST_RUN=$(readlink storage/app/private/simulation/latest)
    SCENARIO_DIR="storage/app/private/simulation/${LATEST_RUN}/scenarios/scenario-1-normal"
    
    if [[ -f "${SCENARIO_DIR}/ballot-cast-format.txt" ]]; then
        echo "✓ ballot-cast-format.txt created:"
        echo "  $(cat "${SCENARIO_DIR}/ballot-cast-format.txt")"
    fi
    
    if [[ -f "${SCENARIO_DIR}/ballot-cast.sh" ]]; then
        echo "✓ ballot-cast.sh created"
    fi
    
    if [[ -f "${SCENARIO_DIR}/ballot-cast-output.log" ]]; then
        echo "✓ Ballot cast log:"
        cat "${SCENARIO_DIR}/ballot-cast-output.log" | head -10
    fi
else
    echo "✗ Simulation with --cast-ballots failed"
    echo "See /tmp/cast-test-output.log for details"
    exit 1
fi

echo ""
echo "========================================="
echo "✓ All tests passed!"
echo "========================================="
