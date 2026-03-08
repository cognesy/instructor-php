# Overview

This directory contains the source code for the setup tool for helping with initial
installation and configuration of Instructor library.

## Publish Command

Use:

```bash
./vendor/bin/instructor-setup publish <target-dir>
```

This publishes package-scoped resources from `packages/*/resources` into:

```text
<target-dir>/<package>/...
```
