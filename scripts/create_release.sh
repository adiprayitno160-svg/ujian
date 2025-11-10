#!/bin/bash
# Script untuk membuat release baru di GitHub
# Usage: ./create_release.sh <version> [release_name] [release_body]

VERSION=$1
RELEASE_NAME=$2
RELEASE_BODY=$3

if [ -z "$VERSION" ]; then
    echo "Usage: ./create_release.sh <version> [release_name] [release_body]"
    echo "Example: ./create_release.sh 1.0.8 \"Release v1.0.8\" \"Perbaikan bug dan peningkatan fitur\""
    exit 1
fi

# Validate version format
if ! [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Invalid version format. Use X.Y.Z format (e.g., 1.0.8)"
    exit 1
fi

TAG_NAME="v$VERSION"
RELEASE_NAME=${RELEASE_NAME:-"Release $TAG_NAME"}
RELEASE_BODY=${RELEASE_BODY:-"Release $TAG_NAME\n\nPerbaikan dan peningkatan fitur."}

echo "Creating release $TAG_NAME..."

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_DIR="$(dirname "$SCRIPT_DIR")"

cd "$REPO_DIR" || exit 1

# Check if git is available
if ! command -v git &> /dev/null; then
    echo "Error: Git is not installed"
    exit 1
fi

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "Error: Not a git repository"
    exit 1
fi

# Check if tag already exists
if git rev-parse "$TAG_NAME" >/dev/null 2>&1; then
    echo "Warning: Tag $TAG_NAME already exists"
    read -p "Do you want to delete and recreate it? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git tag -d "$TAG_NAME" 2>/dev/null
        git push origin ":refs/tags/$TAG_NAME" 2>/dev/null
    else
        echo "Aborted"
        exit 1
    fi
fi

# Create annotated tag
echo "Creating tag $TAG_NAME..."
git tag -a "$TAG_NAME" -m "$RELEASE_NAME" || {
    echo "Error: Failed to create tag"
    exit 1
}

# Push tag to remote
echo "Pushing tag to remote..."
git push origin "$TAG_NAME" || {
    echo "Error: Failed to push tag"
    exit 1
}

echo ""
echo "Tag $TAG_NAME created and pushed successfully!"
echo ""
echo "Next steps:"
echo "1. Go to https://github.com/adiprayitno160-svg/ujian/releases/new"
echo "2. Select tag: $TAG_NAME"
echo "3. Release title: $RELEASE_NAME"
echo "4. Description: $RELEASE_BODY"
echo "5. Click 'Publish release'"
echo ""
echo "Or use GitHub CLI (if installed):"
echo "gh release create $TAG_NAME --title \"$RELEASE_NAME\" --notes \"$RELEASE_BODY\""

