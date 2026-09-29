#!/usr/bin/env bash
#
# Installs every LC module on a fresh Drupal site with the standard profile,
# first one at a time, then all together.
#
# Usage: scripts/install-test.sh [--force] <drupal-root>
#   <drupal-root> is a Composer project (e.g. drupal/recommended-project) with
#   drush and the LC modules already required. SQLite is used as database.
#   --force wipes an existing installation of <drupal-root> first: never use
#   it on a real site.

set -uo pipefail

FORCE=0
if [ "${1:-}" = "--force" ]; then
  FORCE=1
  shift
fi

ROOT=$(cd "${1:?Usage: $0 [--force] <drupal-root>}" && pwd)
DRUSH="$ROOT/vendor/bin/drush"
cd "$ROOT"

SITE_DIR="$ROOT/web/sites/default"
DB_FILE="$SITE_DIR/files/.ht.sqlite"
SNAPSHOT="$ROOT/fresh-install.sqlite"

if [ -f "$SITE_DIR/settings.php" ] && grep -q "^\$databases\['default'\]" "$SITE_DIR/settings.php"; then
  if [ $FORCE -eq 0 ]; then
    echo "$ROOT is already installed. Use --force to wipe it (test sites only)." >&2
    exit 2
  fi
  chmod u+w "$SITE_DIR" "$SITE_DIR/settings.php"
  cp "$SITE_DIR/default.settings.php" "$SITE_DIR/settings.php"
  rm -f "$DB_FILE"
fi

"$DRUSH" -y site:install standard \
  --db-url="sqlite://localhost/sites/default/files/.ht.sqlite" \
  --account-pass=admin --site-name="LC install test" >/dev/null || exit 1
cp "$DB_FILE" "$SNAPSHOT"

MODULES=$("$DRUSH" pm:list --package=LC --field=name | sort)
echo "Drupal $("$DRUSH" status --field=drupal-version), modules: $(echo $MODULES)"

failed=0
try_install() {
  cp "$SNAPSHOT" "$DB_FILE"
  if output=$("$DRUSH" -y pm:install "$@" 2>&1); then
    echo "OK    $*"
  else
    echo "FAIL  $*"
    echo "$output" | sed 's/^/      /'
    failed=1
  fi
}

for module in $MODULES; do
  try_install "$module"
done
try_install $MODULES

exit $failed
