#!/usr/bin/env bash
# Main Simulation Runner
# End-to-end ballot appreciation testing workflow

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="${SCRIPT_DIR}/lib"

# Source all library scripts
source "${LIB_DIR}/common.sh"
source "${LIB_DIR}/template-generator.sh"
source "${LIB_DIR}/scenario-generator.sh"
source "${LIB_DIR}/ballot-renderer.sh"
source "${LIB_DIR}/aruco-generator.sh"
source "${LIB_DIR}/ballot-appreciator.sh"
source "${LIB_DIR}/overlay-generator.sh"

# Default configuration
DEFAULT_OUTPUT_DIR="storage/app/private/simulation"
DEFAULT_CONFIG_DIR="config"
DEFAULT_SCENARIOS=("normal" "overvote" "faint")

# Usage information
usage() {
    cat << EOF
Usage: $0 [OPTIONS]

Run end-to-end ballot appreciation simulation testing.

OPTIONS:
    -o, --output DIR        Output directory (default: storage/app/private/simulation)
    -c, --config DIR        Config directory (default: config)
    -s, --scenarios LIST    Comma-separated scenario types (default: normal,overvote,faint)
    -l, --list-scenarios    List available scenario types and exit
    -f, --fresh             Start fresh by removing existing output directory
    -v, --verbose           Enable verbose logging
    -h, --help              Show this help message

EXAMPLES:
    # Run default scenarios
    $0

    # Run specific scenarios
    $0 --scenarios normal,overvote,undervote,faint,stray

    # Fresh run with custom output
    $0 --fresh --output /tmp/simulation

    # List available scenario types
    $0 --list-scenarios

EOF
}

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
        -c|--config)
            CONFIG_DIR="$2"
            shift 2
            ;;
        -s|--scenarios)
            IFS=',' read -ra SCENARIOS <<< "$2"
            shift 2
            ;;
        -l|--list-scenarios)
            list_scenario_types
            exit 0
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
            usage
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Main execution
main() {
    log_section "Ballot Appreciation Simulation"
    
    # Export configuration for Laravel integration
    export CONFIG_DIR="$CONFIG_DIR"
    export ELECTION_CONFIG_PATH="$CONFIG_DIR"
    
    # Clean up if fresh run requested
    if [[ "$FRESH_RUN" == true ]] && [[ -d "$OUTPUT_DIR" ]]; then
        log_info "Removing existing output directory: $OUTPUT_DIR"
        rm -rf "$OUTPUT_DIR"
    fi
    
    # Create output directory structure
    mkdir -p "$OUTPUT_DIR"
    local template_dir="${OUTPUT_DIR}/template"
    local scenarios_dir="${OUTPUT_DIR}/scenarios"
    mkdir -p "$template_dir" "$scenarios_dir"
    
    # Step 1: Generate template from election config
    log_section "Step 1: Generate Ballot Template"
    
    local coordinates_file="${template_dir}/coordinates.json"
    # Export for child processes (Laravel commands)
    export COORDINATES_FILE="$coordinates_file"
    export TEMPLATE_DIR="$template_dir"
    
    if ! generate_template "$CONFIG_DIR" "$coordinates_file" "${template_dir}/generate.log"; then
        log_error "Template generation failed"
        return 1
    fi
    
    # Validate coordinates
    if ! validate_coordinates "$coordinates_file"; then
        log_error "Coordinate validation failed"
        return 1
    fi
    
    record_success "Template generation"
    
    # Step 2: Generate test scenarios
    log_section "Step 2: Generate Test Scenarios"
    
    log_info "Creating ${#SCENARIOS[@]} test scenarios: ${SCENARIOS[*]}"
    
    local scenario_count=1
    for scenario_type in "${SCENARIOS[@]}"; do
        local scenario_name="scenario-${scenario_count}-${scenario_type}"
        local scenario_dir="${scenarios_dir}/${scenario_name}"
        
        if create_scenario "$scenario_name" "$scenario_type" "$scenario_dir" "$coordinates_file"; then
            record_success "Scenario: $scenario_name"
        else
            record_failure "Scenario: $scenario_name"
        fi
        
        ((scenario_count++))
    done
    
    # Step 3: Render ballot images for each scenario
    log_section "Step 3: Render Ballot Images"
    
    for scenario_dir in "$scenarios_dir"/scenario-*; do
        if [[ ! -d "$scenario_dir" ]]; then
            continue
        fi
        
        local scenario_name=$(basename "$scenario_dir")
        log_info "Rendering ballot: $scenario_name"
        
        local votes_file="${scenario_dir}/votes.json"
        local coords_file="${scenario_dir}/coordinates.json"
        local blank_ballot="${scenario_dir}/blank.png"
        local filled_ballot="${scenario_dir}/ballot.png"
        
        # Render blank ballot (template only)
        if render_blank_ballot "$coords_file" "$blank_ballot"; then
            log_debug "Blank ballot rendered: $blank_ballot"
        else
            log_warning "Failed to render blank ballot for $scenario_name"
        fi
        
        # Render filled ballot (with votes)
        if render_ballot "$votes_file" "$coords_file" "$filled_ballot" "${scenario_dir}/render.log"; then
            record_success "Render: $scenario_name"
        else
            record_failure "Render: $scenario_name"
        fi
    done
    
    # Step 4: Run ballot appreciation on each scenario
    log_section "Step 4: Run Ballot Appreciation"
    
    for scenario_dir in "$scenarios_dir"/scenario-*; do
        if [[ ! -d "$scenario_dir" ]]; then
            continue
        fi
        
        local scenario_name=$(basename "$scenario_dir")
        local ballot_image="${scenario_dir}/ballot.png"
        
        if [[ ! -f "$ballot_image" ]]; then
            log_warning "Ballot image not found for $scenario_name, skipping"
            continue
        fi
        
        log_info "Appreciating ballot: $scenario_name"
        
        local coords_file="${scenario_dir}/coordinates.json"
        local results_file="${scenario_dir}/appreciation_results.json"
        
        if appreciate_ballot "$ballot_image" "$coords_file" "$results_file" "${scenario_dir}/appreciate.log"; then
            record_success "Appreciation: $scenario_name"
            
            # Compare with ground truth
            local votes_file="${scenario_dir}/votes.json"
            local comparison_file="${scenario_dir}/comparison.json"
            if compare_results "$results_file" "$votes_file" "$comparison_file"; then
                record_success "Comparison: $scenario_name"
            else
                record_failure "Comparison: $scenario_name"
            fi
        else
            record_failure "Appreciation: $scenario_name"
        fi
    done
    
    # Step 5: Generate overlays for visual inspection
    log_section "Step 5: Generate Visual Overlays"
    
    for scenario_dir in "$scenarios_dir"/scenario-*; do
        if [[ ! -d "$scenario_dir" ]]; then
            continue
        fi
        
        local scenario_name=$(basename "$scenario_dir")
        local ballot_image="${scenario_dir}/ballot.png"
        local results_file="${scenario_dir}/appreciation_results.json"
        local overlay_image="${scenario_dir}/overlay.png"
        
        if [[ ! -f "$ballot_image" ]] || [[ ! -f "$results_file" ]]; then
            log_warning "Required files not found for $scenario_name, skipping overlay"
            continue
        fi
        
        log_info "Generating overlay: $scenario_name"
        
        if generate_overlay "$ballot_image" "$results_file" "$overlay_image" "${scenario_dir}/overlay.log"; then
            record_success "Overlay: $scenario_name"
        else
            record_failure "Overlay: $scenario_name"
        fi
    done
    
    # Step 6: Generate summary report
    log_section "Step 6: Generate Summary Report"
    
    generate_summary_report "$scenarios_dir" "${OUTPUT_DIR}/summary.txt"
    
    # Display results
    log_section "Simulation Results"
    show_test_results
    
    # Summary
    local total_tests=$(get_total_tests)
    local passed_tests=$(get_passed_tests)
    local failed_tests=$(get_failed_tests)
    
    echo ""
    log_info "Total tests: $total_tests"
    log_success "Passed: $passed_tests"
    
    if [[ $failed_tests -gt 0 ]]; then
        log_error "Failed: $failed_tests"
        echo ""
        log_info "Output directory: $OUTPUT_DIR"
        log_info "Summary report: ${OUTPUT_DIR}/summary.txt"
        return 1
    else
        echo ""
        log_success "All tests passed!"
        log_info "Output directory: $OUTPUT_DIR"
        log_info "Summary report: ${OUTPUT_DIR}/summary.txt"
        return 0
    fi
}

# Generate summary report
generate_summary_report() {
    local scenarios_dir="$1"
    local output_file="$2"
    
    log_info "Generating summary report: $output_file"
    
    {
        echo "========================================"
        echo "Ballot Appreciation Simulation Summary"
        echo "========================================"
        echo ""
        echo "Generated: $(date)"
        echo "Scenarios: ${#SCENARIOS[@]}"
        echo ""
        
        for scenario_dir in "$scenarios_dir"/scenario-*; do
            if [[ ! -d "$scenario_dir" ]]; then
                continue
            fi
            
            local scenario_name=$(basename "$scenario_dir")
            echo "----------------------------------------"
            echo "Scenario: $scenario_name"
            echo "----------------------------------------"
            
            # Scenario metadata
            local metadata_file="${scenario_dir}/scenario.json"
            if [[ -f "$metadata_file" ]]; then
                echo "Type: $(jq -r '.scenario_type' "$metadata_file" 2>/dev/null || echo 'unknown')"
                echo "Description: $(jq -r '.description' "$metadata_file" 2>/dev/null || echo 'N/A')"
            fi
            
            # Files generated
            echo ""
            echo "Files:"
            for file in ballot.png appreciation_results.json overlay.png; do
                if [[ -f "${scenario_dir}/${file}" ]]; then
                    local size=$(get_file_size "${scenario_dir}/${file}")
                    echo "  ✓ $file (${size} bytes)"
                else
                    echo "  ✗ $file (missing)"
                fi
            done
            
            # Appreciation results
            local results_file="${scenario_dir}/appreciation_results.json"
            if [[ -f "$results_file" ]]; then
                echo ""
                echo "Appreciation Results:"
                local filled_count=$(jq '[.bubbles[] | select(.filled == true)] | length' "$results_file" 2>/dev/null || echo 0)
                local total_count=$(jq '.bubbles | length' "$results_file" 2>/dev/null || echo 0)
                echo "  Filled bubbles: ${filled_count}/${total_count}"
                
                local avg_confidence=$(jq '[.bubbles[].confidence] | add / length' "$results_file" 2>/dev/null || echo 0)
                printf "  Average confidence: %.2f\n" "$avg_confidence"
            fi
            
            echo ""
        done
        
        echo "========================================"
        echo "Test Results Summary"
        echo "========================================"
        local total=$(get_total_tests)
        local passed=$(get_passed_tests)
        local failed=$(get_failed_tests)
        echo "Total: $total"
        echo "Passed: $passed"
        echo "Failed: $failed"
        echo ""
        
    } > "$output_file"
    
    log_success "Summary report generated"
    
    # Display summary to console
    cat "$output_file"
}

# Run main function
main
exit_code=$?

# Cleanup
log_debug "Cleaning up..."

exit $exit_code
