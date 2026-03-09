# Adding a Package

This monorepo treats each package as a first-class split package.

Use this checklist when adding a new package.

## 1. Create The Package

Create the package directory under `packages/<name>` with:

- `src/`
- `tests/`
- `composer.json`
- `README.md`
- `CHEATSHEET.md`
- `docs/`

For docs, keep the initial structure small:

- `docs/_meta.yaml`
- `docs/overview.md`
- `docs/quickstart.md`

## 2. Register It In The Monorepo

Add the package to [packages.json](/Users/ddebowczyk/projects/instructor-php/packages.json):

```json
{
  "local": "packages/http-pool",
  "repo": "cognesy/instructor-http-pool",
  "github_name": "instructor-http-pool",
  "composer_name": "cognesy/instructor-http-pool"
}
```

Then update:

- root `composer.json` autoload and autoload-dev
- root `README.md`
- [CONTENTS.md](/Users/ddebowczyk/projects/instructor-php/CONTENTS.md)
- [CONTRIBUTOR_GUIDE.md](/Users/ddebowczyk/projects/instructor-php/CONTRIBUTOR_GUIDE.md)
- any docs registries under `packages/*/resources/config/docs.yaml` or `.yml`

If the package adds public docs, give it:

- a description in each docs config
- a stable place in package order

## 3. Regenerate Split Workflow

Run:

```bash
./scripts/update-split-yml.sh .
```

This regenerates the package matrix in `.github/workflows/split.yml` from `packages.json`.

## 4. Create The GitHub Repo

Create the split repository with `gh`:

```bash
gh repo create cognesy/instructor-http-pool \
  --public \
  --description "Concurrent HTTP request execution for Instructor"
```

Verify it exists:

```bash
gh repo view cognesy/instructor-http-pool --json name,url,visibility
```

## 5. Add The Package To Packagist

Default path: manual.

1. Open `https://packagist.org/packages/submit`
2. Submit the split repository URL, for example:
   `https://github.com/cognesy/instructor-http-pool`
3. Confirm the package name from `composer.json`
4. In Packagist package settings, enable the GitHub hook or auto-update

If you want to automate this later, use a Packagist token and their API. That is not part of the current default workflow.

## 6. Verify The Package Is Discoverable

Check:

- docs autodiscovery sees `packages/<name>/docs`
- split workflow contains the package
- root Composer autoload includes the namespace
- package `composer.json` is valid

Useful checks:

```bash
composer validate packages/http-pool/composer.json
rg "http-pool" packages.json .github/workflows/split.yml packages/*/resources/config
```

## 7. Run Focused QA

At minimum:

```bash
composer validate packages/http-pool/composer.json
php vendor/bin/pest packages/http-pool/tests
```

If the package changes shared infrastructure, also run:

```bash
composer test
```

## Notes

- `packages.json` is the source of truth for split-package registration.
- `update-split-yml.sh` should be used instead of editing `.github/workflows/split.yml` by hand.
- Keep package docs minimal at first. Add more only when the API stabilizes.
