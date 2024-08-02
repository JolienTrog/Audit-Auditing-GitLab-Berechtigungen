#!/bin/bash

echo "Starting the installation process..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root to install the manpage."
  exit 1
fi

# Check if PHP is installed
echo "Checking for PHP installation..."
if ! command -v php &> /dev/null; then
    echo "PHP is not installed. Please install PHP and try again."
    exit 1
else
    echo "PHP is installed."
fi

# Path to the audit.php script
SCRIPT_PATH="./audit.php"
echo "Checking for the presence of audit.php at: $SCRIPT_PATH"

# Check if the audit.php script exists
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "The audit.php script was not found."
    exit 1
else
    echo "audit.php script found."
fi

# Make the audit.php script executable
echo "Making audit.php executable..."
chmod +x "$SCRIPT_PATH"

# Copy the audit.php script to /usr/local/bin and rename it
echo "Copying audit.php to /usr/local/bin/audit.php..."
sudo cp "$SCRIPT_PATH" /usr/local/bin/audit.php

# Create a wrapper script that calls audit.php
echo "Creating a wrapper script..."
echo '#!/bin/bash' > audit
echo 'php /usr/local/bin/audit.php "$@"' >> audit

# Make the wrapper script executable and move it to /usr/local/bin
echo "Making the wrapper script executable and moving it to /usr/local/bin..."
chmod +x audit
sudo mv audit /usr/local/bin/

# Create the man directory if it doesn't exist
mkdir -p /usr/local/man/man1/

# Copy the manpage to the correct location
cp docs/audit.1 /usr/local/man/man1/
# Update the manpage database and filter the output
MAND_OUTPUT=$(mktemp)
LANG=en_US.UTF-8 mandb &> "$MAND_OUTPUT"
rm "$MAND_OUTPUT"

echo "Manpage installed successfully. For help call '-h' or '--help'"
echo "The script has been successfully installed and can now be called with 'audit'."