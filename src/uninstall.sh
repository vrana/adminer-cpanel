#!/bin/sh
# Remove Adminer from cPanel. Run as root on the cPanel server:
#
#   sh uninstall.sh

set -e

FRONTEND=/usr/local/cpanel/base/frontend
UNINSTALL_PLUGIN=/usr/local/cpanel/scripts/uninstall_plugin
SOURCE=$(cd "$(dirname "$0")" && pwd)

if [ "$(id -u)" != 0 ]; then
	echo "Run this as root." >&2
	exit 1
fi

echo "Unregistering the icon"
"$UNINSTALL_PLUGIN" "$SOURCE" || echo "Unregistering failed, removing the files anyway." >&2

for theme in "$FRONTEND"/*/; do
	if [ -d "$theme/adminer" ]; then
		echo "Removing $theme/adminer"
		rm -rf "$theme/adminer"
	fi
done

echo "Done. No database or account was touched."
