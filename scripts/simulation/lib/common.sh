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

log_section() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

log_debug() {
    if [ "${LOG_LEVEL:-INFO}" = "DEBUG" ]; then
        echo -e "${YELLOW}[DEBUG]${NC} $1"
    fi
}

log_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Path utilities
get_project_root() {
    # Assume scripts are in scripts/simulation/lib, so project root is ../../../
    echo "$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
}

validate_path() {
    local path="$1"
    local msg="${2:-Path not found}"
    
    if [ ! -e "$path" ]; then
        log_error "$msg: $path"
        return 1
    fi
    return 0
}

get_file_size() {
    local file="$1"
    if [ -f "$file" ]; then
        stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null || echo "0"
    else
        echo "0"
    fi
}

command_exists() {
    command -v "$1" &>/dev/null
}

check_python_module() {
    local module="$1"
    python3 -c "import $module" 2>/dev/null
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
TEST_RESULTS=()
TEST_PASSED=0
TEST_FAILED=0

record_success() {
    local test_name="$1"
    TEST_RESULTS+=("PASS: $test_name")
    TEST_PASSED=$((TEST_PASSED + 1))
}

record_failure() {
    local test_name="$1"
    TEST_RESULTS+=("FAIL: $test_name")
    TEST_FAILED=$((TEST_FAILED + 1))
}

get_total_tests() {
    echo $((TEST_PASSED + TEST_FAILED))
}

get_passed_tests() {
    echo $TEST_PASSED
}

get_failed_tests() {
    echo $TEST_FAILED
}

show_test_results() {
    for result in "${TEST_RESULTS[@]}"; do
        if [[ "$result" == PASS:* ]]; then
            log_success "${result#PASS: }"
        else
            log_error "${result#FAIL: }"
        fi
    done
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
export -f log_info log_success log_error log_warn log_warning log_header log_scenario log_section log_debug
export -f get_project_root validate_path get_file_size command_exists check_python_module
export -f check_file_exists check_command_exists check_python_modules
export -f record_success record_failure get_total_tests get_passed_tests get_failed_tests show_test_results
