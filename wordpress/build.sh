#!/bin/sh
# Empaqueta el sitio como plugin de WordPress: dist/costagrafica-landing.zip
set -e
cd "$(dirname "$0")/.."

name=costagrafica-landing
tmp=$(mktemp -d)
mkdir -p "$tmp/$name/site" dist

cp wordpress/$name.php "$tmp/$name/"
cp index.html "$tmp/$name/site/"
cp -R logo fonts "$tmp/$name/site/"
find "$tmp" -name .DS_Store -delete

rm -f dist/$name.zip
(cd "$tmp" && zip -qr - "$name") > dist/$name.zip
rm -rf "$tmp"
echo "dist/$name.zip"
