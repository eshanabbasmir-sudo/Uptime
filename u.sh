#!/usr/bin/env bash

# Default Settings
REFRESH_INTERVAL=60 # seconds
USER_AGENT="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36"

# Helper Function: Auto-Detect and Launch Browser
launch_browser() {
    local target_url="$1"
    
    if command -v xdg-open &> /dev/null; then
        xdg-open "$target_url" &> /dev/null &
    elif command -v open &> /dev/null; then
        open "$target_url" &> /dev/null &
    elif command -v google-chrome &> /dev/null; then
        google-chrome --user-agent="$USER_AGENT" "$target_url" &> /dev/null &
    elif command -v firefox &> /dev/null; then
        firefox "$target_url" &> /dev/null &
    else
        echo "Error: No compatible web browser found."
        return 1
    fi
    return 0
}

# Helper Function: Validate URL Format
format_url() {
    local input_url="$1"
    if [[ ! "$input_url" =~ ^https?:// ]]; then
        echo "https://$input_url"
    else
        echo "$input_url"
    fi
}

# Feature: Keep-Alive Loop (Pings URL in background to keep session active)
start_keep_alive() {
    local target_url="$1"
    echo "Keep-alive process started for: $target_url (Interval: ${REFRESH_INTERVAL}s)"
    
    while true; do
        sleep "$REFRESH_INTERVAL"
        # Background request to prevent timeout without stealing window focus
        curl -s -A "$USER_AGENT" -o /dev/null "$target_url"
        
        # Optional: Re-trigger browser focus/reload if needed
        launch_browser "$target_url"
    done
}

# Main Script Execution
clear
echo "=========================================="
echo "    Automated Website Keep-Alive Script   "
echo "=========================================="

read -rp "Enter the website URL: " raw_url

if [[ -z "$raw_url" ]]; then
    echo "URL cannot be empty. Exiting."
    exit 1
fi

TARGET_URL=$(format_url "$raw_url")

echo ""
echo "Target set to: $TARGET_URL"
echo "Opening browser..."

if launch_browser "$TARGET_URL"; then
    echo "Successfully launched."
else
    exit 1
fi

echo ""
echo "Options:"
echo "1) Keep website alive in background (Auto-refresh loop)"
echo "2) Change refresh interval (Current: ${REFRESH_INTERVAL}s)"
echo "3) Exit"

read -rp "Select an option [1-3]: " option

case $option in
    1)
        start_keep_alive "$TARGET_URL"
        ;;
    2)
        read -rp "Enter new refresh interval in seconds: " REFRESH_INTERVAL
        start_keep_alive "$TARGET_URL"
        ;;
    3)
        echo "Exiting script. Browser window remains open."
        exit 0
        ;;
    *)
        echo "Invalid selection. Running keep-alive with defaults..."
        start_keep_alive "$TARGET_URL"
        ;;
esac
