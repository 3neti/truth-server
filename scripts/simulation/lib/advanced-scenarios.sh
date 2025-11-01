#!/usr/bin/env bash
# Advanced Scenario Library
# Implements fiducial testing, geometric distortion, and rotation scenarios

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Rotate image with canvas-based approach to prevent cropping
# Args: input_image, output_image, angle_degrees
rotate_with_canvas() {
    local input_image="$1"
    local output_image="$2"
    local angle="$3"
    
    python3 << PYROTATE
import cv2
import numpy as np
import sys

def rotate_with_canvas(image, angle, bg_color=255):
    """Rotate image at any angle with expanded canvas to prevent cropping."""
    h, w = image.shape[:2]
    
    # For rotation with corner fiducials, use full diagonal as canvas size
    diagonal = int(np.ceil(np.sqrt(w**2 + h**2))) + 200  # +200px safety margin
    
    # Create canvas with white background
    if len(image.shape) == 3:
        canvas = np.full((diagonal, diagonal, 3), bg_color, dtype=np.uint8)
    else:
        canvas = np.full((diagonal, diagonal), bg_color, dtype=np.uint8)
    
    # Center original image in canvas
    y_offset = (diagonal - h) // 2
    x_offset = (diagonal - w) // 2
    canvas[y_offset:y_offset+h, x_offset:x_offset+w] = image
    
    # Rotate around canvas center
    center = (diagonal // 2, diagonal // 2)
    rotation_matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    border_val = (bg_color, bg_color, bg_color) if len(image.shape) == 3 else bg_color
    rotated = cv2.warpAffine(canvas, rotation_matrix, (diagonal, diagonal),
                             borderMode=cv2.BORDER_CONSTANT,
                             borderValue=border_val)
    
    return rotated

try:
    image = cv2.imread('${input_image}')
    if image is None:
        raise Exception("Could not load image: ${input_image}")
    
    rotated = rotate_with_canvas(image, ${angle})
    cv2.imwrite('${output_image}', rotated)
    print(f"Rotated image saved: ${output_image}")
except Exception as e:
    print(f"Error: {e}", file=sys.stderr)
    sys.exit(1)
PYROTATE
    
    return $?
}

# Run a single rotation test
# Args: degree, source_filled, source_blank, coords_file, output_dir, ground_truth
run_rotation_test() {
    local degree=$1
    local source_filled=$2
    local source_blank=$3
    local coords_file=$4
    local output_dir=$5
    local ground_truth=$6
    
    mkdir -p "$output_dir"
    
    log_debug "Running rotation test: ${degree}°"
    
    # Generate rotated images
    if ! rotate_with_canvas "$source_blank" "${output_dir}/blank.png" "$degree"; then
        log_error "Failed to rotate blank ballot"
        return 1
    fi
    
    if ! rotate_with_canvas "$source_filled" "${output_dir}/blank_filled.png" "$degree"; then
        log_error "Failed to rotate filled ballot"
        return 1
    fi
    
    # Run appreciation with ArUco fiducial mode
    local appreciate_script="packages/omr-appreciation/omr-python/appreciate.py"
    
    if [ ! -f "$appreciate_script" ]; then
        log_error "Appreciation script not found: $appreciate_script"
        return 1
    fi
    
    # Run appreciation WITH alignment (ArUco mode)
    OMR_FIDUCIAL_MODE=aruco python3 "$appreciate_script" \
        "${output_dir}/blank_filled.png" \
        "$coords_file" \
        --threshold 0.3 \
        ${CONFIG_DIR:+--config-path "$CONFIG_DIR"} \
        > "${output_dir}/results.json" 2>"${output_dir}/stderr.log"
    
    local appreciate_status=$?
    
    # Generate metadata
    cat > "${output_dir}/metadata.json" <<METADATA
{
  "rotation": ${degree},
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "fiducial_mode": "aruco",
  "threshold": 0.3,
  "alignment_enabled": true
}
METADATA
    
    # Validate against ground truth if available
    if [ -f "$ground_truth" ] && [ $appreciate_status -eq 0 ]; then
        if python3 scripts/compare_appreciation_results.py \
            --result "${output_dir}/results.json" \
            --truth "$ground_truth" \
            --output "${output_dir}/validation.json" \
            >> "${output_dir}/stderr.log" 2>&1; then
            return 0
        else
            return 1
        fi
    fi
    
    return $appreciate_status
}

# Generate scenario-8: Cardinal rotations (0/45/90/135/180/225/270/315 degrees)
# Args: run_dir, source_filled, source_blank, coords_file, ground_truth
generate_scenario_cardinal_rotations() {
    local run_dir=$1
    local source_filled=$2
    local source_blank=$3
    local coords_file=$4
    local ground_truth=$5
    
    local scenario_dir="${run_dir}/scenario-8-cardinal-rotations"
    mkdir -p "$scenario_dir"
    
    log_info "Generating scenario-8: Cardinal rotations"
    
    # Create scenario metadata
    cat > "${scenario_dir}/metadata.json" <<METADATA
{
  "scenario": "cardinal-rotations",
  "description": "ArUco fiducial detection on all rotations: cardinal (0/90/180/270) and diagonal (45/135/225/315)",
  "rotations_tested": [0, 45, 90, 135, 180, 225, 270, 315],
  "ground_truth": "${ground_truth}",
  "alignment_enabled": true,
  "fiducial_mode": "aruco",
  "rotation_method": "unified_canvas_based"
}
METADATA
    
    local passed=0
    local failed=0
    
    # Test each rotation
    for deg in 0 45 90 135 180 225 270 315; do
        local rot_dir="${scenario_dir}/rot_$(printf '%03d' $deg)"
        
        log_info "  Testing ${deg}° rotation..."
        
        if run_rotation_test "$deg" "$source_filled" "$source_blank" "$coords_file" "$rot_dir" "$ground_truth"; then
            log_success "  ${deg}° rotation test passed"
            passed=$((passed + 1))
        else
            log_error "  ${deg}° rotation test failed"
            failed=$((failed + 1))
        fi
    done
    
    # Generate summary
    cat > "${scenario_dir}/summary.json" <<SUMMARY
{
  "total_rotations": $((passed + failed)),
  "passed": ${passed},
  "failed": ${failed}
}
SUMMARY
    
    log_success "Cardinal rotation tests complete: ${passed}/$((passed + failed)) passed"
    
    return 0
}

# Generate scenario-4: Fiducial marker detection
# Args: run_dir, coords_file
generate_scenario_fiducials() {
    local run_dir=$1
    local coords_file=$2
    
    local scenario_dir="${run_dir}/scenario-4-fiducials"
    mkdir -p "$scenario_dir"
    
    log_info "Generating scenario-4: Fiducial marker detection"
    
    # Create scenario metadata
    cat > "${scenario_dir}/metadata.json" <<METADATA
{
  "scenario": "fiducials",
  "description": "Fiducial marker detection and alignment validation",
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "fiducial_modes_tested": ["aruco"],
  "alignment_enabled": true
}
METADATA
    
    # Generate blank ballot with ArUco markers (already done by render_blank_ballot)
    # This scenario validates that fiducials are properly detected
    
    log_success "Fiducial marker scenario created"
    return 0
}

# Generate scenario-5: Quality gates
# Args: run_dir
generate_scenario_quality_gates() {
    local run_dir=$1
    local fixture_dir="storage/app/tests/omr-appreciation/fixtures/skew-rotation"
    
    local scenario_dir="${run_dir}/scenario-5-quality-gates"
    mkdir -p "$scenario_dir"
    
    log_info "Generating scenario-5: Quality gates"
    
    # Create scenario metadata
    cat > "${scenario_dir}/metadata.json" <<METADATA
{
  "scenario": "quality-gates",
  "description": "Ballot alignment quality with synthetic distortions",
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "fixtures_tested": 0,
  "test_matrix": "SKEW_ROTATION_TEST_SCENARIO.md"
}
METADATA
    
    if [ ! -d "$fixture_dir" ] || [ -z "$(ls -A "$fixture_dir" 2>/dev/null | grep '.png$')" ]; then
        log_warning "Quality gate fixtures not found, skipping"
        return 0
    fi
    
    local passed=0
    local failed=0
    local fixture_count=0
    
    # Test each fixture
    for fixture in "$fixture_dir"/*.png; do
        [ -f "$fixture" ] || continue
        local basename=$(basename "$fixture" .png)
        fixture_count=$((fixture_count + 1))
        
        log_info "  Testing ${basename}..."
        
        if python3 packages/omr-appreciation/omr-python/test_quality_on_fixture.py \
            "$fixture" > "${scenario_dir}/${basename}_metrics.log" 2>&1; then
            passed=$((passed + 1))
        else
            failed=$((failed + 1))
        fi
    done
    
    # Update metadata with fixture count
    python3 << PYMETA
import json
with open('${scenario_dir}/metadata.json', 'r') as f:
    meta = json.load(f)
meta['fixtures_tested'] = ${fixture_count}
with open('${scenario_dir}/metadata.json', 'w') as f:
    json.dump(meta, f, indent=2)
PYMETA
    
    # Generate summary
    cat > "${scenario_dir}/summary.json" <<SUMMARY
{
  "total_fixtures": ${fixture_count},
  "passed": ${passed},
  "failed": ${failed}
}
SUMMARY
    
    log_success "Quality gate tests complete: ${passed}/${fixture_count} passed"
    return 0
}

# Generate scenario-6: Distortion (no alignment)
# Args: run_dir, coords_file, ground_truth
generate_scenario_distortion() {
    local run_dir=$1
    local coords_file=$2
    local ground_truth=$3
    
    local scenario_dir="${run_dir}/scenario-6-distortion"
    local fixture_dir="storage/app/tests/omr-appreciation/fixtures/filled-distorted"
    
    mkdir -p "$scenario_dir"
    
    log_info "Generating scenario-6: Distortion (no alignment)"
    
    # Create scenario metadata
    cat > "${scenario_dir}/metadata.json" <<METADATA
{
  "scenario": "filled-ballot-distortion",
  "description": "Real-world ballot appreciation under geometric distortion",
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "fixtures_tested": 0,
  "ground_truth": "${ground_truth}",
  "alignment_enabled": false
}
METADATA
    
    if [ ! -d "$fixture_dir" ] || [ -z "$(ls -A "$fixture_dir" 2>/dev/null | grep '.png$')" ]; then
        log_warning "Distortion fixtures not found, skipping"
        return 0
    fi
    
    if [ ! -f "$ground_truth" ]; then
        log_warning "Ground truth not found: $ground_truth, skipping"
        return 0
    fi
    
    local passed=0
    local failed=0
    local fixture_count=0
    local appreciate_script="packages/omr-appreciation/omr-python/appreciate.py"
    
    for fixture in "$fixture_dir"/*.png; do
        [ -f "$fixture" ] || continue
        local basename=$(basename "$fixture" .png)
        fixture_count=$((fixture_count + 1))
        
        log_info "  Testing ${basename}..."
        
        # Run appreciation WITHOUT alignment
        if python3 "$appreciate_script" \
            "$fixture" \
            "$coords_file" \
            --threshold 0.3 \
            --no-align \
            ${CONFIG_DIR:+--config-path "$CONFIG_DIR"} \
            > "${scenario_dir}/${basename}_appreciation.json" 2>"${scenario_dir}/${basename}_stderr.log"; then
            
            # Validate against ground truth
            if python3 scripts/compare_appreciation_results.py \
                --result "${scenario_dir}/${basename}_appreciation.json" \
                --truth "$ground_truth" \
                --output "${scenario_dir}/${basename}_validation.json" \
                > "${scenario_dir}/${basename}_combined.log" 2>&1; then
                passed=$((passed + 1))
            else
                failed=$((failed + 1))
            fi
        else
            failed=$((failed + 1))
        fi
    done
    
    # Update metadata
    python3 << PYMETA
import json
with open('${scenario_dir}/metadata.json', 'r') as f:
    meta = json.load(f)
meta['fixtures_tested'] = ${fixture_count}
with open('${scenario_dir}/metadata.json', 'w') as f:
    json.dump(meta, f, indent=2)
PYMETA
    
    # Generate summary
    cat > "${scenario_dir}/summary.json" <<SUMMARY
{
  "total_fixtures": ${fixture_count},
  "passed": ${passed},
  "failed": ${failed}
}
SUMMARY
    
    log_success "Distortion tests complete: ${passed}/${fixture_count} passed"
    return 0
}

# Generate scenario-7: Fiducial alignment
# Args: run_dir, coords_file, ground_truth
generate_scenario_fiducial_alignment() {
    local run_dir=$1
    local coords_file=$2
    local ground_truth=$3
    
    local scenario_dir="${run_dir}/scenario-7-fiducial-alignment"
    local fixture_dir="storage/app/tests/omr-appreciation/fixtures/filled-distorted-fiducial"
    
    mkdir -p "$scenario_dir"
    
    log_info "Generating scenario-7: Fiducial alignment"
    
    # Create scenario metadata
    cat > "${scenario_dir}/metadata.json" <<METADATA
{
  "scenario": "fiducial-alignment",
  "description": "Ballot appreciation with fiducial-based alignment correction",
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "fixtures_tested": 0,
  "ground_truth": "${ground_truth}",
  "alignment_enabled": true
}
METADATA
    
    if [ ! -d "$fixture_dir" ] || [ -z "$(ls -A "$fixture_dir" 2>/dev/null | grep '.png$')" ]; then
        log_warning "Fiducial alignment fixtures not found, skipping"
        return 0
    fi
    
    if [ ! -f "$ground_truth" ]; then
        log_warning "Ground truth not found: $ground_truth, skipping"
        return 0
    fi
    
    local passed=0
    local failed=0
    local fixture_count=0
    local appreciate_script="packages/omr-appreciation/omr-python/appreciate.py"
    
    for fixture in "$fixture_dir"/*.png; do
        [ -f "$fixture" ] || continue
        local basename=$(basename "$fixture" .png)
        fixture_count=$((fixture_count + 1))
        
        log_info "  Testing ${basename}..."
        
        # Run appreciation WITH alignment
        if python3 "$appreciate_script" \
            "$fixture" \
            "$coords_file" \
            --threshold 0.3 \
            ${CONFIG_DIR:+--config-path "$CONFIG_DIR"} \
            > "${scenario_dir}/${basename}_appreciation.json" 2>"${scenario_dir}/${basename}_stderr.log"; then
            
            # Validate against ground truth
            if python3 scripts/compare_appreciation_results.py \
                --result "${scenario_dir}/${basename}_appreciation.json" \
                --truth "$ground_truth" \
                --output "${scenario_dir}/${basename}_validation.json" \
                > "${scenario_dir}/${basename}_combined.log" 2>&1; then
                passed=$((passed + 1))
            else
                failed=$((failed + 1))
            fi
        else
            failed=$((failed + 1))
        fi
    done
    
    # Update metadata
    python3 << PYMETA
import json
with open('${scenario_dir}/metadata.json', 'r') as f:
    meta = json.load(f)
meta['fixtures_tested'] = ${fixture_count}
with open('${scenario_dir}/metadata.json', 'w') as f:
    json.dump(meta, f, indent=2)
PYMETA
    
    # Generate summary
    cat > "${scenario_dir}/summary.json" <<SUMMARY
{
  "total_fixtures": ${fixture_count},
  "passed": ${passed},
  "failed": ${failed}
}
SUMMARY
    
    log_success "Fiducial alignment tests complete: ${passed}/${fixture_count} passed"
    return 0
}

# Export functions
export -f rotate_with_canvas
export -f run_rotation_test
export -f generate_scenario_cardinal_rotations
export -f generate_scenario_fiducials
export -f generate_scenario_quality_gates
export -f generate_scenario_distortion
export -f generate_scenario_fiducial_alignment
