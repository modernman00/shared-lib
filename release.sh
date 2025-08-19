#!/bin/bash

VERSION_FILE="config/version.php"
echo "📦 Current APP_VERSION: $CURRENT"

# --- Parse arguments ---
MODE="patch"
for arg in "$@"; do
    case $arg in
        --major) MODE="major" ;;
        --minor) MODE="minor" ;;
    esac
done

# --- Extract current version ---
CURRENT=$(grep "APP_VERSION" "$VERSION_FILE" | sed -E "s/.*'([^']+)'.*/\1/")
IFS='.' read -r MAJOR MINOR PATCH <<< "${CURRENT//v/}"

# --- Bump version based on mode ---
case "$MODE" in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
esac

NEW="v$MAJOR.$MINOR.$PATCH"

echo "🔧 Current version: $CURRENT"
echo "⏫ Bumping to: $NEW ($MODE)"

# Stop if there are no changes
if git diff --quiet && git diff --cached --quiet; then
  echo "⚠️ No changes to commit."
  exit 0
fi

# Stop if gh CLI is not installed
if ! command -v gh &> /dev/null; then
  echo "❌ GitHub CLI (gh) not found. Install it to publish releases."
  exit 1
fi


# --- Update version.php ---
sed -i '' "s/$CURRENT/$NEW/" "$VERSION_FILE"  # macOS
# Use: sed -i "s/$CURRENT/$NEW/" "$VERSION_FILE" on Linux

# --- Commit updated version ---
git add -A
git commit -m "🔖 Bump version to $NEW"

# --- Create tag and push ---
git tag "$NEW"
git push origin master
git push origin "$NEW"

# --- Generate changelog ---
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null)
if [ "$LAST_TAG" = "$NEW" ]; then
  LAST_TAG=$(git rev-list --tags --max-count=2 | tail -n1 | xargs git describe --tags --abbrev=0)
fi

NOTES="### Changelog\n\n"
NOTES+=$(git log "$LAST_TAG"..HEAD --pretty=format:"- %s _(by %an)_" --no-merges)
[ -z "$NOTES" ] && NOTES="Initial release."

# --- Publish GitHub release ---
gh release create "$NEW" \
  --title "Release $NEW" \
  --notes "$NOTES" \
  --verify-tag

echo "✅ Published $NEW with changelog and updated version.php!"
