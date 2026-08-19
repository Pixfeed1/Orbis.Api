#!/usr/bin/env bash
# Publishes the plugin to the WordPress.org SVN repository.
#
#   ./bin/svn-release.sh 1.6.0
#
# Requires an `svn` client and your WordPress.org SVN password
# (set it at https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password).
set -euo pipefail

cd "$(dirname "$0")/.."

SLUG="colisly"
SVN_USER="pixfeed"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
VERSION="${1:-}"

if [ -z "$VERSION" ]; then
  echo "Usage: $0 <version>   (e.g. $0 1.6.0)" >&2
  exit 1
fi

HEADER_VERSION=$(grep -oP '^\s*\*\s*Version:\s*\K[0-9.]+' "${SLUG}/${SLUG}.php")
README_VERSION=$(grep -oP '^Stable tag:\s*\K[0-9.]+' "${SLUG}/readme.txt")

if [ "$HEADER_VERSION" != "$VERSION" ] || [ "$README_VERSION" != "$VERSION" ]; then
  echo "Version mismatch: header=${HEADER_VERSION} readme=${README_VERSION} requested=${VERSION}" >&2
  echo "Update both before releasing." >&2
  exit 1
fi

CHECKOUT="$(mktemp -d)/${SLUG}-svn"
echo "==> Checking out ${SVN_URL}"
svn checkout "$SVN_URL" "$CHECKOUT" --depth immediates
svn update --set-depth infinity "$CHECKOUT/trunk"
svn update --set-depth infinity "$CHECKOUT/assets"

echo "==> Syncing trunk"
rsync -a --delete --exclude='.svn' "${SLUG}/" "$CHECKOUT/trunk/"

echo "==> Tagging ${VERSION}"
rm -rf "${CHECKOUT:?}/tags/${VERSION}"
mkdir -p "$CHECKOUT/tags"
cp -R "$CHECKOUT/trunk" "$CHECKOUT/tags/${VERSION}"
find "$CHECKOUT/tags/${VERSION}" -name '.svn' -type d -prune -exec rm -rf {} +

cd "$CHECKOUT"
# Stage additions and deletions.
svn add --force . --auto-props --parents --depth infinity -q
svn status | awk '/^!/ {print $2}' | xargs -r svn delete --force -q

echo "==> Pending changes"
svn status

echo "==> Committing"
svn commit -m "Release ${VERSION}" --username "$SVN_USER"

echo "==> Done. https://wordpress.org/plugins/${SLUG}"
