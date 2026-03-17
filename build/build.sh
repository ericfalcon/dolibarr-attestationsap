#!/bin/bash
# Génère le ZIP de distribution AttestationSAP pour Dolibarr
# Usage : cd build && bash build.sh
# Résultat : dist/module_attestationsap-VERSION.zip

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

VERSION=$(grep "this->version" "$ROOT_DIR/core/modules/modAttestationSap.class.php" \
          | head -1 | sed "s/.*= *'//;s/'.*//")
if [ -z "$VERSION" ]; then VERSION="2.1.0"; fi

ZIPNAME="module_attestationsap-${VERSION}.zip"
TMPDIR=$(mktemp -d)
DESTDIR="$TMPDIR/attestationsap"

echo "Version : $VERSION"
echo "Construction de $ZIPNAME..."

mkdir -p "$DESTDIR"

# Copier les fichiers du module (sans git, build, dist, tools)
for item in admin class core help.php img index.php install_guide.php \
            langs README.md INSTALL.md setup.php sql tiers_tab.php; do
    [ -e "$ROOT_DIR/$item" ] && cp -r "$ROOT_DIR/$item" "$DESTDIR/"
done

# Créer le dossier dist
mkdir -p "$ROOT_DIR/dist"

# Créer le ZIP
cd "$TMPDIR"
zip -r "$ROOT_DIR/dist/$ZIPNAME" attestationsap/
rm -rf "$TMPDIR"

echo ""
echo "✓ Créé : dist/$ZIPNAME ($(du -sh "$ROOT_DIR/dist/$ZIPNAME" | cut -f1))"
echo "  → Installer via Dolibarr : Configuration → Modules → Déployer/installer un module externe"
