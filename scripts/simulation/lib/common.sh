#!/bin/bash
# Common functions and utilities for simulation tests

# Colors for output
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[1;33m'
export RED='\033[0;31m'
export NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

log_header() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

log_scenario() {
    echo -e "${BLUE}$1${NC}"
}

# Path utilities
get_project_root() {
    # Assume scripts are in scripts/simulation/lib, so project root is ../../../
    echo "$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
}

# Validation functions
check_file_exists() {
    local file=$1
    local description=$2
    
    if [ ! -f "$file" ]; then
        log_error "$description not found: $file"
        return 1
    fi
    return 0
}

check_command_exists() {
    local cmd=$1
    
    if ! command -v "$cmd" &> /dev/null; then
        log_error "Required command not found: $cmd"
        return 1
    fi
    return 0
}

# Test result tracking
SCENARIO_PASSED=0
SCENARIO_FAILED=0
SCENARIO_TOTAL=0

increment_passed() {
    SCENARIO_PASSED=$((SCENARIO_PASSED + 1))
    SCENARIO_TOTAL=$((SCENARIO_TOTAL + 1))
}

increment_failed() {
    SCENARIO_FAILED=$((SCENARIO_FAILED + 1))
    SCENARIO_TOTAL=$((SCENARIO_TOTAL + 1))
}

print_summary() {
    echo ""
    log_header "Test Summary"
    echo ""
    echo "  Total:  $SCENARIO_TOTAL"
    echo "  Passed: ${GREEN}$SCENARIO_PASSED${NC}"
    echo "  Failed: ${RED}$SCENARIO_FAILED${NC}"
    echo ""
    
    if [ $SCENARIO_FAILED -eq 0 ]; then
        log_success "All tests passed!"
        return 0
    else
        log_error "$SCENARIO_FAILED test(s) failed"
        return 1
    fi
}

# Python availability check
check_python_modules() {
    local required_modules="cv2 numpy"
    local missing=""
    
    for module in $required_modules; do
        if ! python3 -c "import $module" 2>/dev/null; then
            missing="$missing $module"
        fi
    done
    
    if [ -n "$missing" ]; then
        log_error "Missing Python modules:$missing"
        log_info "Install with: pip3 install opencv-python numpy"
        return 1
    fi
    
    return 0
}

# Export functions for use in other scripts
export -f log_info log_success log_error log_warn log_header log_scenario
export -f get_project_root check_file_exists check_command_exists
export -f increment_passed increment_failed print_summary
export -f check_python_modules
