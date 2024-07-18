#!/bin/bash

# Check if running as root
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root to install the manpage."
  exit 1
fi

# Create the man directory if it doesn't exist
mkdir -p /usr/local/man/man1/

# Copy the manpage to the correct location
cp docs/audit.1 /usr/local/man/man1/

# Update the manpage database
mandb

echo "Manpage installed successfully."
