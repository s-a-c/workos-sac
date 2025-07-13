#!/bin/bash

# Script to add "Back to Top" links to remaining Chinook documentation files
# This script processes files that don't already have back to top links

# Set the base directory
BASE_DIR=".ai/guides/chinook"

# Function to extract the main heading from a markdown file and create anchor
get_main_heading_anchor() {
    local file="$1"
    # Get the first level 1 heading and convert to GitHub anchor format
    grep -m 1 "^# " "$file" | sed 's/^# //' | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9 -]//g' | sed 's/ /-/g' | sed 's/--*/-/g' | sed 's/^-\|-$//g'
}

# Function to add back to top link to a file
add_back_to_top_link() {
    local file="$1"

    # Check if file already has a back to top link
    if grep -q "⬆️ Back to Top" "$file"; then
        echo "Skipping $file - already has back to top link"
        return
    fi

    # Get the main heading anchor
    local heading_anchor=$(get_main_heading_anchor "$file")

    if [ -z "$heading_anchor" ]; then
        echo "Warning: Could not find main heading in $file"
        return
    fi

    # Add the back to top link at the end
    echo "" >> "$file"
    echo "[⬆️ Back to Top](#$heading_anchor)" >> "$file"

    echo "Added back to top link to: $file"
}

# Process remaining Filament subdirectory files that need back to top links
files_to_process=(
    "$BASE_DIR/filament/internationalization/000-internationalization-index.md"
    "$BASE_DIR/filament/internationalization/010-translatable-models-setup.md"
    "$BASE_DIR/filament/diagrams/000-diagrams-index.md"
    "$BASE_DIR/filament/diagrams/010-entity-relationship-diagrams.md"
    "$BASE_DIR/filament/features/000-features-index.md"
    "$BASE_DIR/filament/models/000-models-index.md"
    "$BASE_DIR/filament/models/090-taxonomy-integration.md"
    "$BASE_DIR/filament/deployment/000-deployment-index.md"
    "$BASE_DIR/filament/deployment/010-deployment-guide.md"
)

# Process each file
for file in "${files_to_process[@]}"; do
    if [ -f "$file" ]; then
        add_back_to_top_link "$file"
    else
        echo "File not found: $file"
    fi
done

echo "Batch back to top link addition complete!"
