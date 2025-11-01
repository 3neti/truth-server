#!/bin/bash
# Template generation from election config

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Generate coordinates.json from election config
# Args: config_dir, output_file
generate_template() {
    local config_dir=$1
    local output_file=$2
    local log_file="${3:-/dev/null}"
    
    log_info "Generating ballot template from config..."
    
    # Validate inputs
    if [ ! -d "$config_dir" ]; then
        log_error "Config directory not found: $config_dir"
        return 1
    fi
    
    # Required config files
    local required_files=(
        "$config_dir/election.json"
        "$config_dir/precinct.yaml"
        "$config_dir/mapping.yaml"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "Missing config file: $file"
            return 1
        fi
    done
    
    # Run Laravel command
    local project_root=$(get_project_root)
    cd "$project_root" || return 1
    
    if php artisan election:generate-template \
        --config-path="$config_dir" \
        --output="$output_file" > "$log_file" 2>&1; then
        
        # Verify output file exists
        if [ ! -f "$output_file" ]; then
            log_error "Template generation succeeded but output file not found"
            return 1
        fi
        
        # Count bubbles
        local bubble_count=$(python3 -c "import json; print(len(json.load(open('$output_file'))['bubble']))" 2>/dev/null || echo "0")
        
        log_success "Template generated ($bubble_count bubbles)"
        return 0
    else
        log_error "Template generation failed"
        if [ "$log_file" != "/dev/null" ] && [ -f "$log_file" ]; then
            cat "$log_file"
        fi
        return 1
    fi
}

# Validate generated coordinates
# Args: coords_file
validate_coordinates() {
    local coords_file=$1
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file"
        return 1
    fi
    
    # Use Python to validate JSON structure and check page bounds
    python3 <<PYVALIDATE
import json
import sys

try:
    with open('$coords_file') as f:
        coords = json.load(f)
    
    # Check required fields
    required = ['ballot_size', 'bubble', 'fiducial']
    for field in required:
        if field not in coords:
            print(f"Missing required field: {field}", file=sys.stderr)
            sys.exit(1)
    
    # Check page bounds
    page_width = coords['ballot_size']['width_mm']
    page_height = coords['ballot_size']['height_mm']
    
    max_x = max(b['center_x'] for b in coords['bubble'].values())
    max_y = max(b['center_y'] for b in coords['bubble'].values())
    
    if max_x >= page_width or max_y >= page_height:
        print(f"Bubbles overflow page bounds! Max: ({max_x:.1f}, {max_y:.1f}), Page: ({page_width}, {page_height})", file=sys.stderr)
        sys.exit(1)
    
    bubble_count = len(coords['bubble'])
    fiducial_count = len(coords['fiducial'])
    
    print(f"  Bubbles: {bubble_count}")
    print(f"  Fiducials: {fiducial_count}")
    print(f"  Page: {page_width}mm x {page_height}mm")
    print(f"  Max position: ({max_x:.1f}, {max_y:.1f})mm")
    
    sys.exit(0)
    
except Exception as e:
    print(f"Validation error: {e}", file=sys.stderr)
    sys.exit(1)
PYVALIDATE

    if [ $? -eq 0 ]; then
        log_success "Coordinates validated"
        return 0
    else
        log_error "Coordinate validation failed"
        return 1
    fi
}

# Generate ballot PDF from coordinates
# Args: coords_file, output_pdf
generate_ballot_pdf() {
    local coords_file=$1
    local output_pdf=$2
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file"
        return 1
    fi
    
    # Use ballot renderer to create PNG first, then convert to PDF
    local temp_png="${output_pdf%.pdf}.png"
    
    # Source ballot renderer for render_blank_ballot function
    source "${SCRIPT_DIR}/ballot-renderer.sh"
    
    # Render blank ballot as PNG
    if ! render_blank_ballot "$coords_file" "$temp_png"; then
        log_error "Failed to render ballot PNG"
        return 1
    fi
    
    # Convert PNG to PDF using ImageMagick
    if command -v convert >/dev/null 2>&1; then
        if convert "$temp_png" -quality 100 "$output_pdf" 2>/dev/null; then
            log_success "Ballot PDF generated: $output_pdf"
            rm -f "$temp_png"  # Clean up temp PNG
            return 0
        else
            log_warning "ImageMagick convert failed, keeping PNG only"
            # Keep PNG as fallback
            return 1
        fi
    else
        log_warning "ImageMagick not available, skipping PDF generation"
        log_info "Blank ballot PNG available: $temp_png"
        return 1
    fi
}

# Export functions
export -f generate_template validate_coordinates generate_ballot_pdf
