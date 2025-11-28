#!/bin/bash
# Setup Claude Code hooks for bd/bv integration
# This script configures .claude/settings.local.json and .claude/hooks/

set -e

echo "🔧 Setting up Claude Code hooks for bd/bv..."
echo ""

# Create .claude directory if it doesn't exist
if [ ! -d ".claude" ]; then
    echo "⚠️  .claude directory not found. Creating it..."
    mkdir -p .claude
fi

# Create hooks directory
mkdir -p .claude/hooks

# Create session-start.sh hook for Claude Code for Web
echo "📝 Creating session-start.sh hook..."
cat > .claude/hooks/session-start.sh << 'EOF'
#!/bin/bash
# Auto-install bd in every Claude Code for Web session

# Install bd globally from npm
npm install -g @beads/bd

# Initialize bd if not already done
if [ ! -d .beads ]; then
  bd init --quiet
fi

# Show current work
echo ""
echo "📋 Ready work:"
bd ready --limit 5 || echo "No ready work found"
EOF

chmod +x .claude/hooks/session-start.sh
echo "✅ Created .claude/hooks/session-start.sh"

# Handle settings.local.json
if [ -f ".claude/settings.local.json" ]; then
    echo ""
    echo "⚠️  .claude/settings.local.json already exists"
    echo ""
    echo "You need to manually merge these settings:"
    echo ""
    echo "1. Add to 'permissions.allow' array:"
    echo '   "Bash(bd:*)",'
    echo '   "Bash(bv:*)"'
    echo ""
    echo "2. Add 'hooks' section:"
    cat << 'EOF'
  "hooks": {
    "SessionStart": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ],
    "PreCompact": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ]
  }
EOF
    echo ""
    echo "Example merged file saved to: .claude/settings.local.json.example"

    # Create example file
    cat > .claude/settings.local.json.example << 'EOF'
{
  "permissions": {
    "allow": [
      "Bash(bd:*)",
      "Bash(bv:*)"
    ],
    "deny": []
  },
  "hooks": {
    "SessionStart": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ],
    "PreCompact": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ]
  }
}
EOF

else
    # Create new settings.local.json
    echo "📝 Creating .claude/settings.local.json..."
    cat > .claude/settings.local.json << 'EOF'
{
  "permissions": {
    "allow": [
      "Bash(bd:*)",
      "Bash(bv:*)"
    ],
    "deny": []
  },
  "hooks": {
    "SessionStart": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ],
    "PreCompact": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ]
  }
}
EOF
    echo "✅ Created .claude/settings.local.json"
fi

echo ""
echo "✅ Claude Code hooks setup complete!"
echo ""
echo "Next steps:"
echo "1. Restart your Claude Code session for hooks to take effect"
echo "2. Run 'bd ready' to see available work"
echo "3. Read docs-internal/bd-bv/SETUP.md for full documentation"
