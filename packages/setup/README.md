# Overview

This directory contains the source code for the setup tool for helping with initial
installation and configuration of Instructor library.

## Config Publish Command

Use:

```bash
./vendor/bin/instructor-setup config:publish <target-dir>
```

This publishes package-owned config files from `packages/*/resources/config` into:

```text
<target-dir>/<package>/...
```

The legacy `publish` command is still available and continues to copy full `resources` directories.

## Validation

Use:

```bash
./vendor/bin/instructor-setup config:validate <target-dir>
```

Recommended workflow:

```bash
./vendor/bin/instructor-setup config:publish config
./vendor/bin/instructor-setup config:validate config
./vendor/bin/instructor-setup config:cache config --cache-path=var/cache/instructor-config.php
```
