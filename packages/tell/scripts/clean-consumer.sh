#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
AGENTS_DIR="$(cd "$PACKAGE_DIR/../agents" && pwd)"
CONFIG_DIR="$(cd "$PACKAGE_DIR/../config" && pwd)"
POLYGLOT_DIR="$(cd "$PACKAGE_DIR/../polyglot" && pwd)"
UTILS_DIR="$(cd "$PACKAGE_DIR/../utils" && pwd)"
PROOF_ROOT="$(mktemp -d)"
PROOF_VERSION="2.9.0"

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

# Tell depends on Config, Agents, Polyglot, and Utils contracts shipped by the
# same release train. Archive them as distribution artifacts;
# this deliberately proves installability without a monorepo path repository.
archive_package "$POLYGLOT_DIR" polyglot instructor-polyglot
archive_package "$AGENTS_DIR" agents agents
archive_package "$CONFIG_DIR" config instructor-config
archive_package "$UTILS_DIR" utils instructor-utils
archive_package "$PACKAGE_DIR" tell instructor-tell

SMOKE_SCRIPT="$PROOF_ROOT/smoke.php"
cat > "$SMOKE_SCRIPT" <<'PHP'
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Cognesy\Tell\Tell;
use Cognesy\Tell\Shell\TellShellJobApprovals;
use Cognesy\Tell\Shell\TellShellJobHost;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellShellJobRequest;
use Cognesy\Utils\Cli\CliMarkdown;

if (!class_exists(CliMarkdown::class)) {
    throw new RuntimeException('Tell resolved an incompatible instructor-utils package.');
}

$project = sys_get_temp_dir().'/tell-clean-consumer-'.bin2hex(random_bytes(6));
mkdir($project, 0755, true);
$tell = Tell::testing($project, 'clean consumer answer');
try {
    $result = $tell->run(TellRequest::prompt('local deterministic smoke'));
    if (trim($result->text()) !== 'clean consumer answer') {
        throw new RuntimeException('Unexpected clean-consumer result.');
    }
    if ($tell->host()->describe()->profile !== 'standard') {
        throw new RuntimeException('Tell SDK did not boot the standard host.');
    }
} finally {
    $tell->dispose();
}

$host = TellShellJobHost::shellJobs(
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
