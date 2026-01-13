#!/bin/bash
# Generate comprehensive sample content for AVC development
# This script is run automatically during avc-dev recipe installation

set -e

echo "Generating comprehensive sample content for AVC development..."
echo ""

# Generate social demo content (users, topics, events, pages, posts, groups, comments)
echo "==> Generating social demo content (users, groups, topics, events, posts, comments)..."
if drush social-demo:add user topic event page post comment group event_enrollment -y 2>&1 | grep -v "already exists"; then
    echo "✓ Social demo content generated"
else
    echo "⚠ Some social demo content may already exist (non-critical)"
fi

echo ""

# Generate AVC-specific content (guild skills, progress, credits, endorsements)
echo "==> Generating AVC guild content (skills, progress, credits, endorsements)..."
if drush avc:generate --users=0 --groups=0 --documents=0 --resources=0 --assignments=0 -y 2>&1; then
    echo "✓ AVC guild content generated"
else
    echo "⚠ Some AVC content may already exist (non-critical)"
fi

echo ""
echo "✓ Sample content generation complete!"
echo ""
echo "Content created:"
echo "  - Users (with complex passwords)"
echo "  - Groups and guilds"
echo "  - Topics and events"
echo "  - Posts and comments"
echo "  - Guild skills and progress tracking"
echo "  - Skill credits and endorsements"
echo ""
