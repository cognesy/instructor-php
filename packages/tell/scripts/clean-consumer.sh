#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_ROOT="$(cd "$PACKAGE_DIR/../.." && pwd)"
PROOF_ROOT="$(mktemp -d)"
PROOF_VERSION="2.10.1"

cleanup() {
    rm -rf "$PROOF_ROOT"
}
trap cleanup EXIT

ARTIFACTS="$PROOF_ROOT/artifacts"
mkdir -p "$ARTIFACTS"

archive_package() {
    local source_dir="$1"
    local proof_name="$2"
    local archive_name="$3"
    local source_copy="$PROOF_ROOT/source-$proof_name"

    mkdir -p "$source_copy"
    rsync -a --exclude vendor --exclude composer.lock "$source_dir/" "$source_copy/"
    composer validate --working-dir="$source_copy" --strict --no-check-publish
    jq --arg version "$PROOF_VERSION" '. + {version: $version}' "$source_copy/composer.json" > "$source_copy/composer.versioned.json"
    mv "$source_copy/composer.versioned.json" "$source_copy/composer.json"

    composer archive \
        --working-dir="$source_copy" \
        --format=zip \
        --dir="$ARTIFACTS" \
        --file="$archive_name" \
        --no-interaction \
        --quiet
}

# Archive the complete package train so every transitive internal dependency is
# resolved from distribution artifacts, not from a monorepo path repository.
for source_dir in "$PROJECT_ROOT"/packages/*; do
    if [[ -f "$source_dir/composer.json" ]]; then
        package_slug="$(basename "$source_dir")"
        archive_package "$source_dir" "$package_slug" "$package_slug"
    fi
done

SMOKE_SCRIPT="$PROOF_ROOT/smoke.php"
cat > "$SMOKE_SCRIPT" <<'PHP'
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Cognesy\Tell\Testing\TellTestFactory;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobApprovals;
use Cognesy\Tell\Composition\Standalone\Profile\ShellJob\StandardTellShellJobProfile;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellShellJobRequest;
use Cognesy\Utils\Cli\CliMarkdown;

if (!class_exists(CliMarkdown::class)) {
    throw new RuntimeException('Tell resolved an incompatible instructor-utils package.');
}

$project = sys_get_temp_dir().'/tell-clean-consumer-'.bin2hex(random_bytes(6));
mkdir($project, 0755, true);
$tell = TellTestFactory::responses('clean consumer answer')->open($project);
$result = $tell->run(TellRequest::prompt('local deterministic smoke'));
if (trim($result->text()) !== 'clean consumer answer') {
    throw new RuntimeException('Unexpected clean-consumer result.');
}

$host = StandardTellShellJobProfile::builder(
    project: $project,
    approval: TellShellJobApprovals::allowAll(),
)->boot();
try {
    $job = $host->jobs()->start(TellShellJobRequest::command('printf shell-job-host'));
    $finished = $host->jobs()->wait($job->id, 2_000);
    if ($finished->exitCode !== 0 || $host->jobs()->read($job->id)->text() !== 'shell-job-host') {
        throw new RuntimeException('Unexpected clean-consumer shell-job-host result.');
    }
} finally {
    $host->dispose();
}

echo "clean-consumer-smoke: ok\n";
PHP

for platform in 8.3.0 8.4.0 8.5.0; do
    for resolution in lowest highest; do
        consumer="$PROOF_ROOT/php-$platform-$resolution"
        mkdir -p "$consumer"
        jq -n \
            --arg artifacts "$ARTIFACTS" \
            --arg version "$PROOF_VERSION" \
            --arg platform "$platform" \
            '{
                name: "tell/proof-consumer",
                repositories: [{type: "artifact", url: $artifacts}],
                require: {"cognesy/instructor-tell": $version},
                config: {"platform": {php: $platform}, "allow-plugins": {"pestphp/pest-plugin": true}},
                "minimum-stability": "stable",
                "prefer-stable": true
            }' > "$consumer/composer.json"
        update=(composer update --working-dir="$consumer" --no-interaction --no-progress --prefer-dist --no-audit --quiet)
        if [[ "$resolution" == "lowest" ]]; then
            update+=(--prefer-lowest)
        fi
        "${update[@]}"
        composer show --working-dir="$consumer" --locked cognesy/instructor-tell | grep -F "versions : * $PROOF_VERSION"
        cp "$SMOKE_SCRIPT" "$consumer/smoke.php"
        php "$consumer/smoke.php"
    done
done

SMOKE_CONSUMER="$PROOF_ROOT/php-8.5.0-highest"
php "$SMOKE_CONSUMER/vendor/bin/tell" --version

echo "clean-consumer-matrix: php 8.3/8.4/8.5 x lowest/highest resolved and ran without path repositories"
