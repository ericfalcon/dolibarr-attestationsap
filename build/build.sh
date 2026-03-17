#!/bin/bash
# Génère un zip prêt à déployer dans Dolibarr
# Usage : cd build && bash build.sh
# Résultat : dist/module_attestationsap-VERSION.zip
# Le zip s'extrait en dossier "attestationsap/" directement utilisable dans htdocs/custom/

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

VERSION=$(grep "this->version" "$ROOT_DIR/core/modules/modAttestationSap.class.php" | head -1 | sed "s/.*= *'//;s/'.*//")
if [ -z "$VERSION" ]; then VERSION="2.1"; fi

ZIPNAME="module_attestationsap-${VERSION}.zip"
TMPDIR=$(mktemp -d)

echo "Version détectée : $VERSION"
echo "Construction de $ZIPNAME..."

mkdir -p "$TMPDIR/attestationsap"
rsync -a \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='build/' \
    --exclude='dist/' \
    --exclude='tools/' \
    --exclude='test/' \
    "$ROOT_DIR/" "$TMPDIR/attestationsap/"

mkdir -p "$ROOT_DIR/dist"
cd "$TMPDIR"
zip -r "$ROOT_DIR/dist/$ZIPNAME" attestationsap/
rm -rf "$TMPDIR"

echo ""
echo "✓ Créé : dist/$ZIPNAME"
echo "  → Dézipper dans htdocs/custom/ pour obtenir htdocs/custom/attestationsap/"
