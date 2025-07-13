#!/bin/bash

# Script to add "Back to Top" links to Chinook documentation files
# This script adds consistent back to top links to all markdown files that don't already have them

# Set the base directory
BASE_DIR=".ai/guides/chinook"

# Function to extract the main heading from a markdown file
get_main_heading() {
    local file="$1"
    # Get the first level 1 heading and convert to anchor format
    grep -m 1 "^# " "$file" | sed 's/^# //' | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9 -]//g' | sed 's/ /-/g' | sed 's/--*/-/g' | sed 's/^-\|-$//g'
}

# Function to add back to top link to a file
add_back_to_top() {
    local file="$1"
    local heading_anchor="$2"
    
    # Check if file already has a back to top link
    if grep -q "⬆️ Back to Top" "$file"; then
        echo "Skipping $file - already has back to top link"
        return
    fi
    
    # Get the last few lines to determine where to add the link
    local last_lines=$(tail -5 "$file")
    
    # Add the back to top link at the end
    echo "" >> "$file"
    echo "[⬆️ Back to Top](#$heading_anchor)" >> "$file"
    
    echo "Added back to top link to: $file"
}

# Process all markdown files in the chinook directory
find "$BASE_DIR" -name "*.md" -type f | while read -r file; do
    echo "Processing: $file"
    
    # Get the main heading anchor
    heading_anchor=$(get_main_heading "$file")
    
    if [ -n "$heading_anchor" ]; then
        add_back_to_top "$file" "$heading_anchor"
    else
        echo "Warning: Could not find main heading in $file"
    fi
done

echo "Back to top link addition complete!"
