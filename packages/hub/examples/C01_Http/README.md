# HTTP Examples Sync Policy

Source of truth: `examples/C01_Http`.

When root HTTP examples change, sync hub copies with:

```bash
packages/hub/examples/sync-http-client-examples.sh
```

Do not edit `packages/hub/examples/C01_Http/*/run.php` directly unless you also update root examples and re-sync.
