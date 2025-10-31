#!/usr/bin/env bash
# Scenario Generator Library
# Creates diverse test scenarios for ballot appreciation testing

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Get scenario description by type
# Usage: get_scenario_description SCENARIO_TYPE
get_scenario_description() {
    local scenario_type="$1"
    
    case "$scenario_type" in
        normal) echo "Clean ballot with clear marks" ;;
        overvote) echo "Ballot with overvoted positions" ;;
        undervote) echo "Ballot with some positions unmarked" ;;
        faint) echo "Ballot with faint/light marks" ;;
        stray) echo "Ballot with stray marks and noise" ;;
        damaged) echo "Ballot with torn edges or damage" ;;
        rotated) echo "Ballot scanned at slight rotation" ;;
        skewed) echo "Ballot with perspective distortion" ;;
        mixed) echo "Combination of various issues" ;;
        *) echo "Unknown scenario type" ;;
    esac
}

# Generate scenario metadata
# Usage: generate_scenario_metadata SCENARIO_NAME SCENARIO_TYPE OUTPUT_DIR
generate_scenario_metadata() {
    local scenario_name="$1"
    local scenario_type="$2"
    local output_dir="$3"
    
    log_debug "Generating scenario metadata: $scenario_name ($scenario_type)"
    
    local metadata_file="${output_dir}/scenario.json"
    
    # Create metadata JSON
    local description=$(get_scenario_description "$scenario_type")
    cat > "$metadata_file" << EOF
{
  "scenario_name": "$scenario_name",
  "scenario_type": "$scenario_type",
  "description": "$description",
  "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "expected_issues": $(get_expected_issues "$scenario_type"),
  "test_parameters": $(get_test_parameters "$scenario_type")
}
EOF
    
    log_debug "Metadata created: $metadata_file"
    echo "$metadata_file"
}

# Get expected issues for scenario type
# Usage: get_expected_issues SCENARIO_TYPE
get_expected_issues() {
    local scenario_type="$1"
    
    case "$scenario_type" in
        normal)
            echo '["none"]'
            ;;
        overvote)
            echo '["overvote"]'
            ;;
        undervote)
            echo '["undervote"]'
            ;;
        faint)
            echo '["low_confidence", "detection_failure"]'
            ;;
        stray)
            echo '["false_positive", "noise"]'
            ;;
        damaged)
            echo '["fiducial_detection_failure", "perspective_correction_failure"]'
            ;;
        rotated)
            echo '["alignment_issues"]'
            ;;
        skewed)
            echo '["perspective_distortion", "fiducial_alignment"]'
            ;;
        mixed)
            echo '["multiple_issues"]'
            ;;
        *)
            echo '["unknown"]'
            ;;
    esac
}

# Get test parameters for scenario type
# Usage: get_test_parameters SCENARIO_TYPE
get_test_parameters() {
    local scenario_type="$1"
    
    case "$scenario_type" in
        normal)
            cat << 'EOF'
{
  "fill_threshold": 0.3,
  "confidence_threshold": 0.7,
  "expected_pass": true
}
EOF
            ;;
        overvote)
            cat << 'EOF'
{
  "fill_threshold": 0.3,
  "confidence_threshold": 0.7,
  "expected_pass": false,
  "expected_violations": ["max_votes_exceeded"]
}
EOF
            ;;
        undervote)
            cat << 'EOF'
{
  "fill_threshold": 0.3,
  "confidence_threshold": 0.7,
  "expected_pass": true,
  "expected_partial": true
}
EOF
            ;;
        faint)
            cat << 'EOF'
{
  "fill_threshold": 0.15,
  "confidence_threshold": 0.5,
  "expected_pass": true,
  "low_confidence_expected": true
}
EOF
            ;;
        stray)
            cat << 'EOF'
{
  "fill_threshold": 0.4,
  "confidence_threshold": 0.6,
  "expected_pass": true,
  "noise_filtering_required": true
}
EOF
            ;;
        damaged)
            cat << 'EOF'
{
  "fill_threshold": 0.3,
  "confidence_threshold": 0.6,
  "expected_pass": false,
  "fiducial_recovery_required": true
}
EOF
            ;;
        rotated)
            cat << 'EOF'
{
  "fill_threshold": 0.3,
  "confidence_threshold": 0.7,
  "expected_pass": true,
  "rotation_correction_required": true,
  "max_rotation_degrees": 5
}
EOF
            ;;
        skewed)
            cat << 'EOF'
{
  "fill_threshold": 0.3,
  "confidence_threshold": 0.6,
  "expected_pass": true,
  "perspective_correction_required": true
}
EOF
            ;;
        mixed)
            cat << 'EOF'
{
  "fill_threshold": 0.25,
  "confidence_threshold": 0.5,
  "expected_pass": false,
  "multiple_corrections_required": true
}
EOF
            ;;
        *)
            echo '{}'
            ;;
    esac
}

# Generate votes for scenario
# Usage: generate_scenario_votes SCENARIO_TYPE TEMPLATE_FILE OUTPUT_FILE
generate_scenario_votes() {
    local scenario_type="$1"
    local template_file="$2"
    local output_file="$3"
    
    log_debug "Generating votes for scenario type: $scenario_type"
    
    # Check if Python module is available
    check_python_module json || {
        log_error "Python json module required"
        return 1
    }
    
    # Generate votes using Python script
    python3 << PYTHON_EOF
import json
import random
from pathlib import Path

# Load template coordinates
with open('$template_file') as f:
    template = json.load(f)

# Group bubbles: Row A is separate, rows B-J are one multi-seat position
bubbles_by_position = {}
for key in template['bubble'].keys():
    row = key[0]  # Get first character (A, B, C, etc.)
    if row == 'A':
        # Punong Barangay - separate position
        if 'PUNONG_BARANGAY' not in bubbles_by_position:
            bubbles_by_position['PUNONG_BARANGAY'] = []
        bubbles_by_position['PUNONG_BARANGAY'].append(key)
    else:
        # All other rows (B-J) are Sangguniang Barangay members - one position
        if 'SANGGUNIANG_BARANGAY' not in bubbles_by_position:
            bubbles_by_position['SANGGUNIANG_BARANGAY'] = []
        bubbles_by_position['SANGGUNIANG_BARANGAY'].append(key)

# Set max votes per position
max_votes = {
    'PUNONG_BARANGAY': 1,      # Vote for 1 punong barangay
    'SANGGUNIANG_BARANGAY': 7  # Vote for up to 7 members
}

# Generate votes based on scenario type
votes = {}
scenario_type = '$scenario_type'

if scenario_type == 'normal':
    # Vote maximum allowed for each position
    for position, keys in bubbles_by_position.items():
        max_possible = min(max_votes[position], len(keys))
        if max_possible > 0:
            num_votes = max_possible  # Always vote max for normal scenario
            selected = random.sample(keys, num_votes)
            for key in selected:
                votes[key] = {'filled': True, 'fill_ratio': 0.7}

elif scenario_type == 'overvote':
    # Deliberately exceed max_votes for Sangguniang position only
    for position, keys in bubbles_by_position.items():
        if position == 'PUNONG_BARANGAY':
            # Always vote correctly for Punong Barangay (1 vote)
            num_votes = 1
        else:
            # Overvote for Sangguniang (8-9 votes instead of max 7)
            num_votes = min(max_votes[position] + random.randint(1, 2), len(keys))
        
        if num_votes > 0:
            selected = random.sample(keys, num_votes)
            for key in selected:
                votes[key] = {'filled': True, 'fill_ratio': 0.7}

elif scenario_type == 'undervote':
    # Select fewer than max votes for most positions
    for position, keys in bubbles_by_position.items():
        if random.random() < 0.7:  # 70% chance to undervote
            num_votes = random.randint(0, max(1, max_votes[position] - 1))
        else:
            num_votes = max_votes[position]
        if num_votes > 0:
            selected = random.sample(keys, min(num_votes, len(keys)))
            for key in selected:
                votes[key] = {'filled': True, 'fill_ratio': 0.7}

elif scenario_type == 'faint':
    # Use low fill ratios (but vote max allowed)
    for position, keys in bubbles_by_position.items():
        max_possible = min(max_votes[position], len(keys))
        if max_possible > 0:
            num_votes = max_possible  # Vote max, but with faint marks
            selected = random.sample(keys, num_votes)
            for key in selected:
                votes[key] = {'filled': True, 'fill_ratio': random.uniform(0.2, 0.4)}

elif scenario_type == 'stray':
    # Add normal votes plus some stray marks
    for position, keys in bubbles_by_position.items():
        max_possible = min(max_votes[position], len(keys))
        if max_possible > 0:
            num_votes = random.randint(1, max_possible)
            selected = random.sample(keys, num_votes)
            for key in selected:
                votes[key] = {'filled': True, 'fill_ratio': 0.7}
    
    # Add stray marks (unmarked but with some fill)
    all_keys = list(template['bubble'].keys())
    num_strays = random.randint(2, 5)
    stray_keys = random.sample([k for k in all_keys if k not in votes], num_strays)
    for key in stray_keys:
        votes[key] = {'filled': False, 'fill_ratio': random.uniform(0.05, 0.15)}

else:
    # Default: normal voting pattern
    for position, keys in bubbles_by_position.items():
        max_possible = min(max_votes[position], len(keys))
        if max_possible > 0:
            num_votes = random.randint(1, max_possible)
            selected = random.sample(keys, num_votes)
            for key in selected:
                votes[key] = {'filled': True, 'fill_ratio': 0.7}

# Save votes
with open('$output_file', 'w') as f:
    json.dump(votes, f, indent=2)

print(f"Generated {len(votes)} vote marks for scenario: {scenario_type}")
PYTHON_EOF
    
    if [[ $? -eq 0 ]]; then
        log_success "Votes generated: $output_file"
        return 0
    else
        log_error "Failed to generate votes"
        return 1
    fi
}

# Create complete scenario
# Usage: create_scenario SCENARIO_NAME SCENARIO_TYPE OUTPUT_DIR TEMPLATE_FILE
create_scenario() {
    local scenario_name="$1"
    local scenario_type="$2"
    local output_dir="$3"
    local template_file="$4"
    
    log_info "Creating scenario: $scenario_name ($scenario_type)"
    
    # Validate scenario type
    local valid_types="normal overvote undervote faint stray damaged rotated skewed mixed"
    if ! echo "$valid_types" | grep -qw "$scenario_type"; then
        log_error "Unknown scenario type: $scenario_type"
        log_info "Available types: $valid_types"
        return 1
    fi
    
    # Create output directory
    mkdir -p "$output_dir"
    
    # Generate metadata
    generate_scenario_metadata "$scenario_name" "$scenario_type" "$output_dir"
    
    # Generate votes
    local votes_file="${output_dir}/votes.json"
    generate_scenario_votes "$scenario_type" "$template_file" "$votes_file"
    
    # Copy template reference
    cp "$template_file" "${output_dir}/coordinates.json"
    
    log_success "Scenario created: $output_dir"
}

# Generate scenario suite (multiple scenarios)
# Usage: generate_scenario_suite OUTPUT_DIR TEMPLATE_FILE [SCENARIO_TYPES...]
generate_scenario_suite() {
    local output_dir="$1"
    local template_file="$2"
    shift 2
    local scenario_types=("$@")
    
    # Default to common scenario types if none specified
    if [[ ${#scenario_types[@]} -eq 0 ]]; then
        scenario_types=("normal" "overvote" "undervote" "faint" "stray")
    fi
    
    log_info "Generating scenario suite with ${#scenario_types[@]} scenarios"
    
    local count=1
    for scenario_type in "${scenario_types[@]}"; do
        local scenario_name="scenario-${count}-${scenario_type}"
        local scenario_dir="${output_dir}/${scenario_name}"
        
        create_scenario "$scenario_name" "$scenario_type" "$scenario_dir" "$template_file"
        
        ((count++))
    done
    
    log_success "Scenario suite generated: $output_dir"
}

# List available scenario types
# Usage: list_scenario_types
list_scenario_types() {
    echo "Available scenario types:"
    local types="normal overvote undervote faint stray damaged rotated skewed mixed"
    for type in $types; do
        local desc=$(get_scenario_description "$type")
        echo "  - $type: $desc"
    done
}

# Export functions
export -f generate_scenario_metadata
export -f generate_scenario_votes
export -f create_scenario
export -f generate_scenario_suite
export -f list_scenario_types
