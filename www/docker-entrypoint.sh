#!/bin/bash
set -e

# Check if node_modules is empty or doesn't have api-client
if [ ! -d "node_modules/@stonescript" ] || [ ! -d "node_modules/@stonescript/api-client" ]; then
    echo "Installing npm dependencies (api-client not found)..."
    npm install
    echo "Dependencies installed successfully!"
fi

# Execute the main command
exec "$@"
