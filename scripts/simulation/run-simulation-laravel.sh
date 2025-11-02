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

# Check for ImageMagick (required for advanced scenarios)
if command -v convert >/dev/null 2>&1; then
    HAS_IMAGEMAGICK=true
else
    HAS_IMAGEMAGICK=false
fi

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

# Create timestamped run directory
TIMESTAMP=$(date -u '+%Y-%m-%d_%H%M%S')
RUN_DIR="${OUTPUT_DIR}/runs/${TIMESTAMP}"

# Clean up if fresh run requested
if [[ "$FRESH_RUN" == true ]] && [[ -d "${OUTPUT_DIR}/runs" ]]; then
    log_info "Removing all previous runs: ${OUTPUT_DIR}/runs"
    rm -rf "${OUTPUT_DIR}/runs"
fi

# Create run directory structure
mkdir -p "$RUN_DIR"
template_dir="${RUN_DIR}/template"
scenarios_dir="${RUN_DIR}/scenarios"
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
            NO_ALIGN=false  # Enable alignment for quality metrics
            ;;
        overvote)
            # Overvote: Fill 2 bubbles in row A (only 1 allowed for Punong Barangay)
            BUBBLES="ROW_A_A6,ROW_C_C3,ROW_C_C5,ROW_D_D2,ROW_E_E3,ROW_E_E4,ROW_F_F3,ROW_G_G2,ROW_H_H4"
            DESCRIPTION="Overvote scenario"
            NO_ALIGN=false  # Enable alignment for quality metrics
            ;;
        faint)
            # Faint marks with low intensity
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4,ROW_H_H1,ROW_H_H6,ROW_J_J1"
            INTENSITY="0.4"
            DESCRIPTION="Faint marks scenario"
            NO_ALIGN=false  # Enable alignment for quality metrics
            ;;
        fiducials)
            # Fiducials: Test fiducial marker detection
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4"
            DESCRIPTION="Fiducial marker detection test"
            SKIP_FILL=true  # Just test fiducial detection
            NO_ALIGN=false  # Fiducial detection requires alignment
            ;;
        quality-gates)
            # Quality gates: Test with slight distortion WITH alignment
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4"
            DESCRIPTION="Quality metrics with geometric distortion (5° rotation, with fiducial alignment)"
            DISTORTION_ANGLE=5  # 5 degree rotation
            NO_ALIGN=false  # Enable alignment to test quality metrics
            ;;
        distortion)
            # Distortion: Test without alignment correction
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4"
            DESCRIPTION="Appreciation without fiducial alignment (10° rotation)"
            DISTORTION_ANGLE=10
            NO_ALIGN=true
            ;;
        fiducial-alignment)
            # Fiducial alignment: Test WITH correction
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3,ROW_E_E4,ROW_G_G4"
            DESCRIPTION="Appreciation with fiducial alignment correction (10° rotation)"
            DISTORTION_ANGLE=10
            NO_ALIGN=false
            ;;
        cardinal-rotations)
            # Cardinal rotations: Test main 4 orientations
            BUBBLES="ROW_A_A2,ROW_D_D2,ROW_D_D3"
            DESCRIPTION="Cardinal rotation test (0°/90°/180°/270°)"
            ROTATIONS=(0 90 180 270)
            NO_ALIGN=false  # Enable alignment for rotations
            ;;
        *)
            log_warning "Unknown scenario type: $scenario_type"
            continue
            ;;
    esac
    
    # Check if this is a multi-rotation scenario
    if [[ -n "${ROTATIONS:-}" ]]; then
        # Multi-rotation scenario (cardinal-rotations)
        log_info "  Multi-rotation scenario: testing ${#ROTATIONS[@]} rotations"
        
        # Fill bubbles once
        log_info "  Filling bubbles: $BUBBLES"
        FILLED_PNG=$(php artisan simulation:fill-bubbles \
            "${scenario_dir}/blank.png" \
            --bubbles="$BUBBLES" \
            --coordinates="$COORDS_JSON" \
            --output="${scenario_dir}/blank_filled_base.png" \
            2>&1 | tail -1)
        
        if [[ $? -ne 0 ]]; then
            log_error "  Failed to fill bubbles"
            ((scenario_count++))
            continue
        fi
        
        # Test each rotation
        rotation_results=()
        for rotation in "${ROTATIONS[@]}"; do
            log_info "  Testing rotation: ${rotation}°"
            rotation_dir="${scenario_dir}/rotation_${rotation}"
            mkdir -p "$rotation_dir"
            
            # Apply rotation
            if [[ "$HAS_IMAGEMAGICK" == "true" ]]; then
                # For rotation scenarios, we keep both rotated input and upright output
                # Store the upright ballot as the canonical version (since appreciation corrects it)
                cp "${scenario_dir}/blank.png" "${rotation_dir}/blank.png"
                cp "${scenario_dir}/blank_filled_base.png" "${rotation_dir}/blank_filled.png"
                
                if [[ "$rotation" -ne 0 ]]; then
                    # Save the rotated input as a reference
                    convert "${scenario_dir}/blank_filled_base.png" \
                        -background white \
                        -rotate "${rotation}" \
                        "${rotation_dir}/blank_filled_rotated.png" 2>/dev/null
                fi
                
                # Create scenario.json
                cat > "${rotation_dir}/scenario.json" <<ROTATION_SCENARIO
{
  "scenario_id": "${scenario_name}/rotation_${rotation}",
  "scenario_type": "${scenario_type}",
  "description": "${DESCRIPTION} - ${rotation}° rotation",
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "configuration": {
    "rotation_angle": ${rotation},
    "alignment_enabled": true,
    "bubbles_filled": [
$(echo "$BUBBLES" | tr ',' '\n' | sed 's/^/      "/;s/$/",/' | sed '$ s/,$//')
    ]
  }
}
ROTATION_SCENARIO
                
                # Run appreciation on rotated ballot (alignment will correct the rotation)
                ALIGN_FLAG=""
                if [[ "${NO_ALIGN:-true}" != "false" ]]; then
                    ALIGN_FLAG="--no-align"
                fi
                
                # Use the rotated ballot if it exists, otherwise the upright one (for rotation_0)
                INPUT_BALLOT="${rotation_dir}/blank_filled_rotated.png"
                if [[ ! -f "$INPUT_BALLOT" ]]; then
                    INPUT_BALLOT="${rotation_dir}/blank_filled.png"
                fi
                
                php artisan simulation:appreciate \
                    "$INPUT_BALLOT" \
                    "$COORDS_JSON" \
                    --output="${rotation_dir}/results.json" \
                    ${ALIGN_FLAG} \
                    > /dev/null 2>&1
                
                if [[ $? -eq 0 ]]; then
                    DETECTED=$(jq '[.results[] | select(.filled == true)] | length' "${rotation_dir}/results.json")
                    rotation_results+=("${rotation}°: ${DETECTED} detected")
                    log_success "    ${rotation}°: Detected $DETECTED bubbles"
                    
                    # Enrich votes with candidate information
                    php artisan simulation:enrich-votes \
                        "${rotation_dir}/results.json" \
                        --config-dir="$CONFIG_DIR" \
                        --output="${rotation_dir}/votes.json" \
                        --fields=key,value,position,position_name,name,alias,number \
                        > /dev/null 2>&1
                    
                    # Generate overlay visualization
                    # Note: Appreciation with alignment reports results in template coordinate space,
                    # so we overlay on the upright filled ballot (stored in blank_filled.png)
                    php artisan simulation:create-overlay \
                        "${rotation_dir}/blank_filled.png" \
                        "${rotation_dir}/results.json" \
                        "$COORDS_JSON" \
                        "${rotation_dir}/overlay.png" \
                        --document-id=SIM-QUESTIONNAIRE-001 \
                        --show-legend \
                        > /dev/null 2>&1
                    
                    # Validate quality gates (don't exit on failure)
                    php artisan simulation:validate-quality \
                        "${rotation_dir}/results.json" \
                        --output="${rotation_dir}/quality_validation.json" \
                        > /dev/null 2>&1 || true
                    
                    QUALITY_VERDICT=$(jq -r '.validation.overall_verdict // "unknown"' "${rotation_dir}/quality_validation.json" 2>/dev/null || echo "unknown")
                    log_info "    ${rotation}°: Quality ${QUALITY_VERDICT}"
                    
                    log_success "    ${rotation}°: All artifacts generated"
                else
                    rotation_results+=("${rotation}°: FAILED")
                    log_error "    ${rotation}°: Appreciation failed"
                fi
            else
                log_warning "  ImageMagick not available, skipping rotation"
                break
            fi
        done
        
        # Create summary
        cat > "${scenario_dir}/rotation_summary.json" <<ROTATION_SUMMARY
{
  "scenario": "${scenario_name}",
  "rotations_tested": [$(IFS=,; echo "${ROTATIONS[*]}" | sed 's/\([0-9]*\)/"\1°"/g')],
  "results": [
$(for result in "${rotation_results[@]}"; do
    rotation_angle=$(echo "$result" | cut -d: -f1)
    detected=$(echo "$result" | grep -oE '[0-9]+ detected' | cut -d' ' -f1 || echo "0")
    echo "    {\"rotation\": \"$rotation_angle\", \"detected\": ${detected:-0}},"
done | sed '$ s/,$//')
  ]
}
ROTATION_SUMMARY
        
        log_success "  Rotation summary: ${rotation_results[*]}"
        
        # Skip normal appreciation step
        ((scenario_count++))
        continue
    fi
    
    # Handle special scenario types
    if [[ "${SKIP_FILL:-false}" == "true" ]]; then
        # Fiducials scenario: Just copy blank ballot (test fiducial detection only)
        log_info "  Skipping bubble fill (fiducial detection only)"
        cp "${scenario_dir}/blank.png" "${scenario_dir}/blank_filled.png"
        FILLED_PNG="${scenario_dir}/blank_filled.png"
    else
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
    fi
    
    # Apply geometric distortion if specified
    if [[ -n "${DISTORTION_ANGLE:-}" ]]; then
        if [[ "$HAS_IMAGEMAGICK" == "true" ]]; then
            log_info "  Applying rotation: ${DISTORTION_ANGLE}°"
            convert "${scenario_dir}/blank_filled.png" \
                -background white \
                -rotate "${DISTORTION_ANGLE}" \
                "${scenario_dir}/blank_filled_rotated.png" 2>/dev/null
            mv "${scenario_dir}/blank_filled_rotated.png" "${scenario_dir}/blank_filled.png"
        else
            log_warning "  ImageMagick not available, skipping rotation"
        fi
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
    "distortion_angle": ${DISTORTION_ANGLE:-0},
    "alignment_enabled": $([ "${NO_ALIGN:-false}" == "true" ] && echo "false" || echo "true"),
    "bubbles_filled": [
$(echo "$BUBBLES" | tr ',' '\n' | sed 's/^/      "/;s/$/",/' | sed '$ s/,$//')
    ]
  }
}
SCENARIO_META
    
    # Run appreciation
    ALIGN_FLAG="--no-align"  # Default: no alignment
    if [[ "${NO_ALIGN:-true}" == "false" ]]; then
        ALIGN_FLAG=""  # Enable alignment
        log_info "  Running appreciation with fiducial alignment (threshold: ${DETECTION_THRESHOLD})..."
    else
        log_info "  Running appreciation without alignment (threshold: ${DETECTION_THRESHOLD})..."
    fi
    
    php artisan simulation:appreciate \
        "${scenario_dir}/blank_filled.png" \
        "$COORDS_JSON" \
        --output="${scenario_dir}/results.json" \
        ${ALIGN_FLAG} \
        > /dev/null 2>&1
    
    if [[ $? -eq 0 ]]; then
        # Count filled bubbles from results JSON
        FILLED_COUNT=$(jq '[.results[] | select(.filled == true)] | length' "${scenario_dir}/results.json")
        log_success "  Detected $FILLED_COUNT filled bubbles"
        
        # Enrich votes with candidate information
        log_info "  Enriching votes with candidate info..."
        php artisan simulation:enrich-votes \
            "${scenario_dir}/results.json" \
            --config-dir="$CONFIG_DIR" \
            --output="${scenario_dir}/votes.json" \
            --fields=key,value,position,position_name,name,alias,number \
            > /dev/null 2>&1
        
        if [[ $? -eq 0 ]]; then
            log_success "  Votes enriched: votes.json"
        else
            log_warning "  Vote enrichment failed (continuing...)"
        fi
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
    
    # Validate quality gates if alignment was enabled
    if [[ "${NO_ALIGN:-true}" == "false" ]]; then
        php artisan simulation:validate-quality \
            "${scenario_dir}/results.json" \
            --output="${scenario_dir}/quality_validation.json" \
            > /dev/null 2>&1 || true
        
        QUALITY_VERDICT=$(jq -r '.validation.overall_verdict // "unknown"' "${scenario_dir}/quality_validation.json" 2>/dev/null || echo "unknown")
        log_info "  Quality: ${QUALITY_VERDICT}"
    fi
    
    ((scenario_count++))
done

log_success "Scenarios created: ${#SCENARIOS[@]}"

# Create symlink to latest run
LATEST_LINK="${OUTPUT_DIR}/latest"
if [[ -L "$LATEST_LINK" ]] || [[ -e "$LATEST_LINK" ]]; then
    rm -f "$LATEST_LINK"
fi
ln -s "runs/${TIMESTAMP}" "$LATEST_LINK"

log_section "Simulation Complete"
log_info "Run ID: ${TIMESTAMP}"
log_info "Run directory: $RUN_DIR"
log_info "Latest link: $LATEST_LINK"
log_info "Template: $template_dir"
log_info "Scenarios: $scenarios_dir"

# List generated files
echo ""
log_info "Generated artifacts:"
find "$RUN_DIR" -type f | sort | while read file; do
    rel_path="${file#$RUN_DIR/}"
    size=$(ls -lh "$file" | awk '{print $5}')
    echo "  - $rel_path ($size)"
done
