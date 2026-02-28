# Overview

This directory contains the source code for the object to schema conversion
used by Instructor library.

## Quality Gate

Run package-level static analysis for release checks:

```bash
vendor/bin/phpstan analyse packages/schema/src --level=max
```

Run schema regression suites required for 2.0.0 release readiness:

```bash
vendor/bin/pest packages/schema/tests/Regression
vendor/bin/pest packages/instructor/tests/Regression/TransitiveObjectDefsToolCallSchemaTest.php
vendor/bin/pest packages/instructor/tests/Regression/StructuredOutputSchemaRendererRefsIntegrationTest.php
```
