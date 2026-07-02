#!/usr/bin/env bash
# Release helper for graystackit/laravel-testo-api.
#
# Usage:
#   scripts/release.sh [patch|minor|major|X.Y.Z]
#
# Defaults to "patch". The first argument is either a bump type
# (patch|minor|major) or an explicit semver version (e.g. 1.0.0).
# The script:
#   1. Determines the new version, either by bumping the latest semver tag
#      (vX.Y.Z) from git or by using the explicit version supplied.
#   2. Updates CHANGELOG.md:
#        - If a "## [X.Y.Z]" heading for the target version already exists,
#          the changelog is left untouched (it is assumed to be prepared).
#        - Otherwise the leading "## [Unreleased]" block is rewritten to
#          "## [X.Y.Z] - YYYY-MM-DD" and a fresh empty "## [Unreleased]"
#          block is inserted above it. Aborts if the Unreleased block has
#          no entries.
#   3. Commits any CHANGELOG change, creates an annotated tag, and pushes
#      both the branch and the tag to origin.

set -euo pipefail

BUMP="${1:-patch}"

EXPLICIT_VERSION=""
case "$BUMP" in
    patch|minor|major) ;;
    [0-9]*.[0-9]*.[0-9]*)
        if [[ ! "$BUMP" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "error: invalid version '$BUMP' (expected: X.Y.Z)" >&2
            exit 1
        fi
        EXPLICIT_VERSION="$BUMP"
        ;;
    *)
        echo "error: unknown argument '$BUMP' (expected: patch, minor, major, or X.Y.Z)" >&2
        exit 1
        ;;
esac

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

CHANGELOG="CHANGELOG.md"

if [[ ! -f "$CHANGELOG" ]]; then
    echo "error: $CHANGELOG not found in $REPO_ROOT" >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "error: working tree is not clean — commit or stash changes first" >&2
    git status --short >&2
    exit 1
fi

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$BRANCH" != "main" ]]; then
    echo "error: must be on 'main' to release (currently on '$BRANCH')" >&2
    exit 1
fi

echo "→ fetching tags from origin"
git fetch --tags origin >/dev/null

if [[ -n "$EXPLICIT_VERSION" ]]; then
    NEW_VERSION="$EXPLICIT_VERSION"
else
    LATEST_TAG="$(git tag --list 'v[0-9]*.[0-9]*.[0-9]*' --sort=-v:refname | head -n1 || true)"
    if [[ -z "$LATEST_TAG" ]]; then
        CURRENT="0.0.0"
    else
        CURRENT="${LATEST_TAG#v}"
    fi

    IFS='.' read -r MAJOR MINOR PATCH <<<"$CURRENT"

    case "$BUMP" in
        patch) PATCH=$((PATCH + 1)) ;;
        minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
        major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
    esac

    NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
fi

NEW_TAG="v${NEW_VERSION}"
TODAY="$(date +%Y-%m-%d)"

if git rev-parse "$NEW_TAG" >/dev/null 2>&1; then
    echo "error: tag $NEW_TAG already exists" >&2
    exit 1
fi

if [[ -n "$EXPLICIT_VERSION" ]]; then
    echo "→ releasing explicit version ${NEW_VERSION}"
else
    echo "→ bumping ${CURRENT} → ${NEW_VERSION} (${BUMP})"
fi

# If the changelog already documents this version, leave it untouched.
if grep -qE "^## \[${NEW_VERSION//./\\.}\]" "$CHANGELOG"; then
    echo "→ CHANGELOG already contains [${NEW_VERSION}] — leaving it untouched"
else
    # Extract the body between "## [Unreleased]" and the next "## [" heading,
    # fail if there are no real entries (only whitespace).
    UNRELEASED_BODY="$(awk '
        /^## \[Unreleased\]/ { capture = 1; next }
        capture && /^## \[/ { exit }
        capture { print }
    ' "$CHANGELOG")"

    if ! grep -qE '\S' <<<"$UNRELEASED_BODY"; then
        echo "error: [Unreleased] block in $CHANGELOG is empty — nothing to release" >&2
        exit 1
    fi

    # Replace the FIRST "## [Unreleased]" line with a fresh Unreleased block
    # followed by the new dated heading. Awk handles this with a single pass so
    # we don't depend on GNU vs BSD sed differences.
    TMP="$(mktemp)"
    awk -v new_heading="## [${NEW_VERSION}] - ${TODAY}" '
        !done && /^## \[Unreleased\]/ {
            print "## [Unreleased]"
            print ""
            print new_heading
            done = 1
            next
        }
        { print }
    ' "$CHANGELOG" >"$TMP"
    mv "$TMP" "$CHANGELOG"

    echo "→ committing CHANGELOG"
    git add "$CHANGELOG"
    git commit -m "chore: release ${NEW_VERSION}"
fi

echo "→ creating tag ${NEW_TAG}"
git tag -a "$NEW_TAG" -m "Release ${NEW_VERSION}"

echo "→ pushing main and ${NEW_TAG} to origin"
git push origin "$BRANCH"
git push origin "$NEW_TAG"

echo "✓ released ${NEW_TAG}"
