#!/bin/bash

# Cleanup Script for OMR Test Files
# This removes all generated PDFs, PNGs, coordinates, and test artifacts

echo "🧹 Cleaning OMR test files..."

# Navigate to project root
cd "$(dirname "$0")/../../.."

# Remove PDFs
echo "  Removing PDFs..."
rm -f storage/omr-output/*.pdf
rm -f storage/omr-output/*.jpg

# Remove PNGs
echo "  Removing PNGs..."
rm -f storage/omr-output/*.png

# Remove coordinates
echo "  Removing coordinates..."
rm -f storage/app/omr/coords/*.json

# Remove meta files
echo "  Removing meta files..."
rm -f storage/omr-output/*.meta.json

# Remove test artifacts
echo "  Removing test artifacts..."
rm -rf storage/app/tests/artifacts/appreciation

# Recreate artifacts directory
mkdir -p storage/app/tests/artifacts/appreciation

echo "✅ Cleanup complete!"
echo ""
echo "File counts:"
echo "  PDFs: $(ls -1 storage/omr-output/*.pdf 2>/dev/null | wc -l | tr -d ' ')"
echo "  PNGs: $(ls -1 storage/omr-output/*.png 2>/dev/null | wc -l | tr -d ' ')"
echo "  Coords: $(ls -1 storage/app/omr/coords/*.json 2>/dev/null | wc -l | tr -d ' ')"
