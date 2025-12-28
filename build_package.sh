#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
MODULE_DIR="$ROOT_DIR/htdocs/custom/mod_facturaelectronicaec"
DIST_DIR="$ROOT_DIR/dist"

if [[ ! -d "$MODULE_DIR" ]]; then
  echo "Module directory not found: $MODULE_DIR" >&2
  exit 1
fi

VERSION=$(php <<'PHP'
<?php
$src = file_get_contents('htdocs/custom/mod_facturaelectronicaec/modFacturaElectronicaEC.class.php');
if (preg_match('/\\$this->version\\s*=\\s*["\\\']([^"\\\']+)/i', $src, $m)) {
    echo $m[1];
    exit;
}
fwrite(STDERR, "Unable to detect module version\n");
exit(1);
PHP
)

mkdir -p "$DIST_DIR"
ARCHIVE="$DIST_DIR/mod_facturaelectronicaec-${VERSION}.zip"

rm -f "$ARCHIVE"

(
  cd "$MODULE_DIR/.."
  zip -r "$ARCHIVE" mod_facturaelectronicaec \
    -x "mod_facturaelectronicaec/logs/*" \
    -x "mod_facturaelectronicaec/**/*.log" \
    -x "mod_facturaelectronicaec/**/*.zip"
)

echo "Paquete generado: $ARCHIVE"
