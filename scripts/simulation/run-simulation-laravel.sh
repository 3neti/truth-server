#!/bin/bash
# OMR Simulation using Laravel infrastructure
# Matches deprecated test-omr-appreciation.sh functionality

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LIB_DIR="${SCRIPT_DIR}/lib"

# Source common functions
source "${LIB_DIR}/common.sh"

# Default configuration
DEFAULT_OUTPUT_DIR="storage/app/private/simulation"
DEFAULT_CONFIG_DIR="resources/docs/simulation/config"
DEFAULT_SCENARIOS=("normal" "overvote" "faint")

# Parse command line arguments
OUTPUT_DIR="$DEFAULT_OUTPUT_DIR"
CONFIG_DIR="$DEFAULT_CONFIG_DIR"
SCENARIOS=("${DEFAULT_SCENARIOS[@]}")
FRESH_RUN=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -o|--output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        -c|--config-dir)
            CONFIG_DIR="$2"
            shift 2
            ;;
        -s|--scenarios)
            IFS=',' read -ra SCENARIOS <<< "$2"
            shift 2
            ;;
        -f|--fresh)
            FRESH_RUN=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            export LOG_LEVEL=DEBUG
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "OPTIONS:"
            echo "  -o, --output DIR        Output directory"
            echo "  -c, --config-dir DIR    Config directory"
            echo "  -s, --scenarios LIST    Comma-separated scenarios"
            echo "  -f, --fresh             Start fresh"
            echo "  -v, --verbose           Verbose output"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

cd "$PROJECT_ROOT"

# Clean up if fresh run requested
if [[ "$FRESH_RUN" == true ]] && [[ -d "$OUTPUT_DIR" ]]; then
    log_info "Removing existing output directory: $OUTPUT_DIR"
    rm -rf "$OUTPUT_DIR"
fi

# Create output directory structure
mkdir -p "$OUTPUT_DIR"
template_dir="${OUTPUT_DIR}/template"
scenarios_dir="${OUTPUT_DIR}/scenarios"
mkdir -p "$template_dir" "$scenarios_dir"

log_section "Step 0: Seed Database from Config"

log_info "Seeding database from config: $CONFIG_DIR"
php artisan simulation:seed-from-config \
    --config-dir="$CONFIG_DIR" \
    --fresh > /dev/null 2>&1

if [[ $? -ne 0 ]]; then
    log_error "Failed to seed database from config"
    exit 1
fi
log_success "Database seeded with ballot templates"

log_section "Step 1: Generate Ballot Template"

# Generate ballot PDF using Laravel (generates coordinates too)
log_info "Generating ballot from database templates..."

# Use seeded templates (SIM-BALLOT-001, SIM-QUESTIONNAIRE-001)
BALLOT_RESULT=$(php artisan simulation:generate-ballot \
    --output-dir="${template_dir}" \
    2>&1 | tail -1)

if [[ $? -ne 0 ]]; then
    log_error "Ballot generation failed"
    exit 1
fi

# Parse JSON output
BALLOT_PDF=$(echo "$BALLOT_RESULT" | jq -r '.ballot_pdf')
LARAVEL_COORDS=$(echo "$BALLOT_RESULT" | jq -r '.coordinates_json')
QUESTIONNAIRE_PDF=$(echo "$BALLOT_RESULT" | jq -r '.questionnaire_pdf')

# Use Laravel coordinates for filling (has actual bubble positions)
COORDS_JSON="$LARAVEL_COORDS"

# Generate mapping coordinates for reference
log_info "Generating mapping coordinates from mapping.yaml..."
php artisan election:generate-template \
    --config-path=resources/docs/simulation/config \
    --output="${template_dir}/coordinates_mapping.json" > /dev/null 2>&1
log_success "Coordinates: coordinates_laravel.json (for filling), coordinates_mapping.json (reference)"

log_success "Ballot PDF: $(basename $BALLOT_PDF)"
log_success "Questionnaire: $(basename $QUESTIONNAIRE_PDF)"
log_info "Using Laravel coordinates (IDs: ROW_A_A1, ROW_B_B2, etc.)"

# Convert PDF to PNG for scenario use
log_info "Converting ballot PDF to PNG..."
BLANK_PNG=$(php artisan simulation:pdf-to-png "$BALLOT_PDF" 2>&1 | tail -1)
log_success "Blank PNG: $BLANK_PNG"

log_section "Step 2: Generate Test Scenarios"

scenario_count=1
for scenario_type in "${SCENARIOS[@]}"; do
    scenario_name="scenario-${scenario_count}-${scenario_type}"
    scenario_dir="${scenarios_dir}/${scenario_name}"
    mkdir -p "$scenario_dir"
    
    log_info "Creating scenario: $scenario_name"
    
    # Copy blank ballot to scenario
    cp "$BLANK_PNG" "${scenario_dir}/blank.png"
    
    # Define bubbles for each scenario type
    # Using Laravel format: ROW_{row}_{bubbleId}
    case "$scenario_type" in
        normal)
            # Normal: Fill some bubbles from different rows
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4,ROW_H_H1,ROW_H_H6,ROW_J_J1"
            DESCRIPTION="Normal ballot with 8 filled bubbles"
            ;;
        overvote)
            # Overvote: Fill 2 bubbles in row A (only 1 allowed for Punong Barangay)
            BUBBLES="ROW_A_A6,ROW_C_C3,ROW_C_C5,ROW_D_D2,ROW_E_E3,ROW_E_E4,ROW_F_F3,ROW_G_G2,ROW_H_H4"
            DESCRIPTION="Overvote scenario"
            ;;
        faint)
            # Faint marks with low intensity
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4,ROW_H_H1,ROW_H_H6,ROW_J_J1"
            INTENSITY="0.4"
            DESCRIPTION="Faint marks scenario"
            ;;
        *)
            log_warning "Unknown scenario type: $scenario_type"
            continue
            ;;
    esac
    
    # Fill bubbles
    log_info "  Filling bubbles: $BUBBLES"
    FILLED_PNG=$(php artisan simulation:fill-bubbles \
        "${scenario_dir}/blank.png" \
        --bubbles="$BUBBLES" \
        --coordinates="$COORDS_JSON" \
        --output="${scenario_dir}/blank_filled.png" \
        ${INTENSITY:+--intensity=$INTENSITY} \
        2>&1 | tail -1)
    
    if [[ $? -ne 0 ]]; then
        log_error "  Failed to fill bubbles"
        ((scenario_count++))
        continue
    fi
    
    # Get threshold configuration from Laravel
    DETECTION_THRESHOLD=$(php artisan tinker --execute="echo config('omr-thresholds.detection_threshold');" 2>/dev/null | tail -1 || echo "0.3")
    VALID_MARK=$(php artisan tinker --execute="echo config('omr-thresholds.classification.valid_mark');" 2>/dev/null | tail -1 || echo "0.95")
    AMBIGUOUS_MIN=$(php artisan tinker --execute="echo config('omr-thresholds.classification.ambiguous_min');" 2>/dev/null | tail -1 || echo "0.15")
    AMBIGUOUS_MAX=$(php artisan tinker --execute="echo config('omr-thresholds.classification.ambiguous_max');" 2>/dev/null | tail -1 || echo "0.45")
    FAINT_MARK=$(php artisan tinker --execute="echo config('omr-thresholds.classification.faint_mark');" 2>/dev/null | tail -1 || echo "0.16")
    
    # Create scenario.json with metadata
    cat > "${scenario_dir}/scenario.json" <<SCENARIO_META
{
  "scenario_id": "${scenario_name}",
  "scenario_type": "${scenario_type}",
  "description": "${DESCRIPTION}",
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "configuration": {
    "thresholds": {
      "detection_threshold": ${DETECTION_THRESHOLD},
      "valid_mark": ${VALID_MARK},
      "ambiguous_min": ${AMBIGUOUS_MIN},
      "ambiguous_max": ${AMBIGUOUS_MAX},
      "faint_mark": ${FAINT_MARK}
    },
    "fill_intensity": ${INTENSITY:-1.0},
    "bubbles_filled": [
$(echo "$BUBBLES" | tr ',' '\n' | sed 's/^/      "/;s/$/",/' | sed '$ s/,$//')
    ]
  }
}
SCENARIO_META
    
    # Run appreciation
    log_info "  Running appreciation (threshold: ${DETECTION_THRESHOLD})..."
    php artisan simulation:appreciate \
        "${scenario_dir}/blank_filled.png" \
        "$COORDS_JSON" \
        --output="${scenario_dir}/results.json" \
        --no-align \
        > /dev/null 2>&1
    
    if [[ $? -eq 0 ]]; then
        # Count filled bubbles from results JSON
        FILLED_COUNT=$(jq '[.results[] | select(.filled == true)] | length' "${scenario_dir}/results.json")
        log_success "  Detected $FILLED_COUNT filled bubbles"
        
        # Extract detected votes (filled bubbles) to votes.json
        jq '{
          "timestamp": (now | strftime("%Y-%m-%dT%H:%M:%SZ")),
          "detected_votes": [
            .results[] | select(.filled == true) | {
              "bubble_id": .id,
              "fill_ratio": .fill_ratio,
              "confidence": .confidence,
              "warnings": .warnings
            }
          ],
          "summary": {
            "total_bubbles": (.results | length),
            "filled_bubbles": ([.results[] | select(.filled == true)] | length),
            "unfilled_bubbles": ([.results[] | select(.filled == false)] | length)
          }
        }' "${scenario_dir}/results.json" > "${scenario_dir}/votes.json"
        
        log_success "  Votes extracted: votes.json"
    else
        log_error "  Appreciation failed"
        ((scenario_count++))
        continue
    fi
    
    # Create overlay
    log_info "  Creating overlay..."
    php artisan simulation:create-overlay \
        "${scenario_dir}/blank_filled.png" \
        "${scenario_dir}/results.json" \
        "$COORDS_JSON" \
        "${scenario_dir}/overlay.png" \
        --document-id=SIM-QUESTIONNAIRE-001 \
        --show-legend > /dev/null 2>&1
    
    if [[ $? -eq 0 ]]; then
        log_success "  Overlay created: overlay.png"
    else
        log_warning "  Overlay generation failed (continuing...)"
    fi
    
    ((scenario_count++))
done

log_success "Scenarios created: ${#SCENARIOS[@]}"

log_section "Simulation Complete"
log_info "Output directory: $OUTPUT_DIR"
log_info "Template: $template_dir"
log_info "Scenarios: $scenarios_dir"

# List generated files
echo ""
log_info "Generated artifacts:"
find "$OUTPUT_DIR" -type f | sort | while read file; do
    rel_path="${file#$OUTPUT_DIR/}"
    size=$(ls -lh "$file" | awk '{print $5}')
    echo "  - $rel_path ($size)"
done
