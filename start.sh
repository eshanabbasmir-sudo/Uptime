#!/bin/bash
# start.sh – 24/7 Browser Host
# Usage: chmod +x start.sh && ./start.sh

set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐  24/7 Browser  –  never close"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# ask for port
read -p "⚡ Enter port number (e.g. 8080): " USER_PORT

# validate port
if [[ -z "$USER_PORT" ]]; then
    echo "❌ No port provided. Using default 8080."
    PORT=8080
elif [[ "$USER_PORT" =~ ^[0-9]+$ ]] && [ "$USER_PORT" -ge 1024 ] && [ "$USER_PORT" -le 65535 ]; then
    PORT=$USER_PORT
else
    echo "⚠️  Invalid port (must be between 1024-65535). Using default 8080."
    PORT=8080
fi

# check if index.html exists
if [ ! -f "index.html" ]; then
    echo "❌ index.html not found in current directory."
    echo "   Make sure this script is in the same folder as index.html"
    exit 1
fi

echo ""
echo "🚀 Starting server on http://localhost:$PORT"
echo "📂 Serving: $(pwd)/index.html"
echo "🔄 Press Ctrl+C to stop the server"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# use python3 if available, fallback to python
if command -v python3 &> /dev/null; then
    PYTHON_CMD="python3"
elif command -v python &> /dev/null; then
    PYTHON_CMD="python"
else
    echo "❌ Python not found. Please install Python to host this website."
    exit 1
fi

# start http server
$PYTHON_CMD -m http.server $PORT
