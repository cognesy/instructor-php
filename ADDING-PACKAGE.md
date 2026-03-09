# Adding a Package

This monorepo treats each package as a first-class split package.

Use this checklist when adding a new package.

This is the sequence that actually works.

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

The package must have a valid `composer.json` before it can be published to Packagist.

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
- docs landing pages if the package should appear there
- examples/docs pointers if this package splits functionality out of another package

If the package adds public docs, give it:

- a description in each docs config
- a stable place in package order

## 3. Regenerate Split Workflow

Run:

```bash
./scripts/update-split-yml.sh .
```

This regenerates the package matrix in `.github/workflows/split.yml` from `packages.json`.

Do not edit the split matrix by hand unless the generator is broken.

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

## 5. Make Sure The Split Repo Has Real Package Contents

Packagist will reject a new repo if `main` does not contain `composer.json`.

Before submitting to Packagist:

- push the monorepo changes that introduce the package, then let the split workflow publish it
- or bootstrap the split repo manually with the package contents if you need it immediately

The important check is:

```bash
gh repo view cognesy/instructor-http-pool --web
```

Then confirm the repo `main` branch already contains:

- `composer.json`
- `src/`
- `README.md`

## 6. Add The Package To Packagist

Preferred path in this repo: Packagist API token from `.env`.

```bash
PACKAGIST_API_USERNAME=$(sed -n "s/^PACKAGIST_API_USERNAME='\\(.*\\)'$/\\1/p" .env)
PACKAGIST_API_TOKEN=$(sed -n "s/^PACKAGIST_API_TOKEN='\\(.*\\)'$/\\1/p" .env)

curl -X POST \
  -H 'content-type: application/json' \
  "https://packagist.org/api/create-package?username=${PACKAGIST_API_USERNAME}&apiToken=${PACKAGIST_API_TOKEN}" \
  -d '{"repository":{"url":"https://github.com/cognesy/instructor-http-pool"}}'
```

Expected response:

```json
{"status":"success"}
```

Then verify:

```bash
curl -I https://packagist.org/packages/cognesy/instructor-http-pool
curl -I https://repo.packagist.org/p2/cognesy/instructor-http-pool.json
```

Manual fallback:

1. Open `https://packagist.org/packages/submit`
2. Submit the split repository URL
3. Confirm the package name from `composer.json`
4. Enable the GitHub hook or auto-update

## 7. Verify The Package Is Discoverable

Check:

- docs autodiscovery sees `packages/<name>/docs`
- split workflow contains the package
- root Composer autoload includes the namespace
- package `composer.json` is valid
- Packagist can resolve the package
- the split repository exists and is public

Useful checks:

```bash
composer validate packages/http-pool/composer.json
rg "http-pool" packages.json .github/workflows/split.yml packages/*/resources/config
gh repo view cognesy/instructor-http-pool --json url,defaultBranchRef
```

## 8. Run Focused QA

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
- Packagist submission depends on the split repo, not the monorepo. If the split repo is empty, submission fails.
- Keep package docs minimal at first. Add more only when the API stabilizes.
