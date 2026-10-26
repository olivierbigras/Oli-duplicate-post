#!/usr/bin/env bash
#
# Build an installable plugin ZIP under artifacts/.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -m1 "Version:" "$ROOT/oli-duplicate-post.php" | sed 's/.*Version:[[:space:]]*//')"
STAGE="$(mktemp -d)"
DEST="$STAGE/oli-duplicate-post"

mkdir -p "$DEST" "$ROOT/artifacts"

# Copy plugin files while honoring .distignore.
while IFS= read -r rel; do
	[ -z "$rel" ] && continue
	mkdir -p "$DEST/$(dirname "$rel")"
	cp -a "$ROOT/$rel" "$DEST/$rel"
done < <(
	cd "$ROOT"
	find . -type f \
		! -path './.git/*' \
		! -path './.github/*' \
		! -path './vendor/*' \
		! -path './node_modules/*' \
		! -path './tests/*' \
		! -path './bin/*' \
		! -path './artifacts/*' \
		! -name '.gitignore' \
		! -name '.distignore' \
		! -name '.gitattributes' \
		! -name 'composer.json' \
		! -name 'composer.lock' \
		! -name 'phpcs.xml.dist' \
		! -name 'phpunit.xml.dist' \
		! -name 'wp-env.json' \
		! -name 'package.json' \
		! -name 'package-lock.json' \
		! -name 'README.md' \
		! -name '.phpunit.result.cache' \
		! -name '*.zip' \
		| sed 's|^\./||'
)

(
	cd "$STAGE"
	zip -r "$ROOT/artifacts/oli-duplicate-post-${VERSION}.zip" oli-duplicate-post >/dev/null
)

cp -f "$ROOT/artifacts/oli-duplicate-post-${VERSION}.zip" "$ROOT/artifacts/oli-duplicate-post.zip"
rm -rf "$STAGE"

echo "Built artifacts/oli-duplicate-post-${VERSION}.zip"
echo "Copied artifacts/oli-duplicate-post.zip"
