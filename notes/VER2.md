## Branch Management

  - main = active 2.x development
  - v1.x = 1.x maintenance and patches
  - Both branches can release independently using the same workflow

## Create Branch Structure

```sh
# Create 1.x maintenance branch from current main
git checkout -b v1.x origin/main
```

## Script Changes

Only modify publish-ver.sh for v1.x branch:

```sh
# Line 98: Change push target for v1.x maintenance
git push origin v1.x && git push origin "v$VERSION"
```
Keep main branch scripts unchanged - they already push to origin main

## Release Process

### For 1.x patches (maintenance):

  1. git checkout v1.x
  2. Make patch changes
  3. ./scripts/publish-ver.sh 1.x.y
  4. Creates tag v1.x.y and pushes to v1.x branch

### For 2.x development (main):

  1. git checkout main
  2. Make changes
  3. ./scripts/publish-ver.sh 2.x.y
  4. Creates tag v2.x.y and pushes to main branch

## Benefits of This Approach

  - Minimal changes - only one script modification needed
  - Main stays primary - preserves main as the default development branch
  - Clean separation - 1.x maintenance isolated on v1.x branch
  - No workflow changes - split.yml handles both branches automatically
