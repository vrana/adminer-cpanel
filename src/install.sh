#!/bin/sh
# Install Adminer into cPanel. Run as root on the cPanel server:
#
#   sh install.sh
#
# Copies the application into every installed theme and registers the icon in the
# Databases group. Re-running it upgrades an existing installation in place.

set -e

FRONTEND=/usr/local/cpanel/base/frontend
INSTALL_PLUGIN=/usr/local/cpanel/scripts/install_plugin
SOURCE=$(cd "$(dirname "$0")" && pwd)

if [ "$(id -u)" != 0 ]; then
	echo "Run this as root." >&2
	exit 1
fi

if [ ! -d "$FRONTEND" ]; then
	echo "$FRONTEND not found - this does not look like a cPanel server." >&2
	exit 1
fi

if [ ! -f "$SOURCE/adminer/adminer.php" ]; then
	echo "$SOURCE/adminer/adminer.php is missing - unpack the released archive, don't run this from the repository." >&2
	exit 1
fi

themes=$(find "$FRONTEND" -mindepth 1 -maxdepth 1 -type d)
if [ -z "$themes" ]; then
	echo "No theme found in $FRONTEND." >&2
	exit 1
fi

for theme in $themes; do
	echo "Installing into $theme/adminer"
	rm -rf "$theme/adminer"
	mkdir -p "$theme/adminer"
	cp -r "$SOURCE/adminer/." "$theme/adminer/"
	find "$theme/adminer" -type d -exec chmod 755 {} +
	find "$theme/adminer" -type f -exec chmod 644 {} +
done

echo "Registering the icon"
"$INSTALL_PLUGIN" "$SOURCE"

cat <<'EOF'

Done. The icon appears in the Databases group of cPanel.

It is bound to the "adminer" feature, so it only shows for accounts whose feature
list has it enabled - add it in WHM > Packages > Feature Manager. Until you do,
existing feature lists leave it off.
EOF
