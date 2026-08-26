# Cordis production dependency gate

Verified on 2026-08-27 for Tell's first scoped-resource feature.

## Release evidence

- Package: `cordis-php/cordis`
- Stable version: `v1.0.1`
- Source repository: `https://github.com/cognesy/cordis-php`
- Dereferenced tag commit: `8afc0b6683cef2b80a66c37118a07f214304948a`
- GitHub release: non-draft, non-prerelease, with source archive, checksums,
  and release manifest
- Release workflow and the tagged commit's repository test workflow: green
- Compatibility workflow: PHP 8.2, 8.3, 8.4, and 8.5 across supported Symfony
  YAML 7.3/8.0 and stable/lowest dependency lines

The package is registered on Packagist and its `v1.0.1` metadata resolves to
the same dereferenced commit. A clean PHP `^8.3` Composer consumer installed
the distribution archive from Packagist and autoloaded `CordisPhp\Runtime\Runtime`.

## Lifecycle evidence

Cordis's full local `just ci` gate passes. Its runtime regression suite covers:

- reverse and aggregate effect cleanup;
- provider unpublication before owned resource cleanup;
- dependent restart after service identity changes;
- cleanup of every previous long-lived attempt scope;
- child ownership and realm isolation;
- failed cleanup recovery; and
- disposal removing runtime registrations.

The executable examples additionally cover scoped lifecycle, service restart,
configuration validation before effects, redacted health, tenant isolation,
service swap, and interception.

## Tell dependency decision

Tell requires `cordis-php/cordis:^1.0.1` through the normal Composer repository.
Tell retains its PHP `^8.3` floor. There is no path repository, VCS reference,
vendored runtime, or commit pin. Ordinary Tell hosts do not boot Cordis; only
the opt-in resource host consumes it.
