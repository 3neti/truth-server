#!/usr/bin/env python3
"""Extract ballot cast format from votes.json for Laravel command execution.

Usage:
    python extract_ballot_cast.py votes.json
    python extract_ballot_cast.py votes.json --output ballot-cast.sh
    python extract_ballot_cast.py votes.json --command-only  # Output just the string for piping
"""

import sys
import json
import argparse


def main():
    """Extract ballot_cast_format from appreciation output JSON."""
    parser = argparse.ArgumentParser(
        description='Extract ballot cast format from OMR appreciation output'
    )
    parser.add_argument('input', help='Path to votes.json file')
    parser.add_argument('--output', '-o', help='Output file path (default: stdout)')
    parser.add_argument('--command-only', '-c', action='store_true',
                       help='Output only the ballot string without command wrapper')
    parser.add_argument('--pipe-mode', '-p', action='store_true',
                       help='Use pipe mode (echo | php artisan election:cast)')
    
    args = parser.parse_args()
    
    # Read input JSON
    try:
        with open(args.input, 'r') as f:
            data = json.load(f)
    except Exception as e:
        print(f"Error reading input file: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Extract ballot_cast_format
    ballot_cast = data.get('ballot_cast_format', '')
    if not ballot_cast:
        print("Error: No ballot_cast_format found in input", file=sys.stderr)
        sys.exit(1)
    
    # Format output based on mode
    if args.command_only:
        output_line = ballot_cast
    elif args.pipe_mode:
        output_line = f'echo "{ballot_cast}" | php artisan election:cast'
    else:
        output_line = f'php artisan election:cast-ballot "{ballot_cast}"'
    
    # Write output
    if args.output:
        try:
            with open(args.output, 'w') as f:
                f.write(output_line + '\n')
            print(f"Ballot cast command written to {args.output}", file=sys.stderr)
        except Exception as e:
            print(f"Error writing output file: {e}", file=sys.stderr)
            sys.exit(1)
    else:
        print(output_line)


if __name__ == '__main__':
    main()
