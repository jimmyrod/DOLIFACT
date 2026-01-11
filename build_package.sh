#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_ROOT="${DOLI_WEB_ROOT:-$ROOT_DIR/htdocs}"
MODULE_DIR="$WEB_ROOT/custom/mod_facturaelectronicaec"
DIST_DIR="$ROOT_DIR/dist"

if [[ ! -d "$MODULE_DIR" ]]; then
  echo "Module directory not found: $MODULE_DIR" >&2
  exit 1
fi

VERSION=$(DOLI_MODULE_PATH="$MODULE_DIR/modFacturaElectronicaEC.class.php" php <<'PHP'
<?php
$path = getenv('DOLI_MODULE_PATH');
if (!file_exists($path)) {
    fwrite(STDERR, "Module descriptor not found: {$path}\n");
    exit(1);
}
$src = file_get_contents($path);
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
  cd "$(dirname "$MODULE_DIR")"
  zip -r "$ARCHIVE" mod_facturaelectronicaec \
    -x "mod_facturaelectronicaec/logs/*" \
    -x "mod_facturaelectronicaec/**/*.log" \
    -x "mod_facturaelectronicaec/**/*.zip"
)

echo "Paquete generado: $ARCHIVE"
