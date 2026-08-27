#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
AGENTS_DIR="$(cd "$PACKAGE_DIR/../agents" && pwd)"
POLYGLOT_DIR="$(cd "$PACKAGE_DIR/../polyglot" && pwd)"
PROOF_ROOT="$(mktemp -d)"
PROOF_VERSION="2.8.4"

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

    if [[ "$proof_name" == "tell" ]]; then
        jq --arg version "$PROOF_VERSION" '
            .require["cognesy/agents"] = $version
            | .require["cognesy/instructor-polyglot"] = $version
        ' "$source_copy/composer.json" > "$source_copy/composer.proof.json"
        mv "$source_copy/composer.proof.json" "$source_copy/composer.json"
    fi

    composer archive \
        --working-dir="$source_copy" \
        --format=zip \
        --dir="$ARTIFACTS" \
        --file="$archive_name" \
        --no-interaction \
        --quiet
}

# Tell depends on the Agents constructor contract and Polyglot provider catalogue
# shipped by the same release train. Archive all three as distribution artifacts;
# this deliberately proves installability without a monorepo path repository.
archive_package "$POLYGLOT_DIR" polyglot instructor-polyglot
archive_package "$AGENTS_DIR" agents agents
archive_package "$PACKAGE_DIR" tell instructor-tell

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
    done
done

SMOKE_CONSUMER="$PROOF_ROOT/php-8.5.0-highest"
cat > "$SMOKE_CONSUMER/smoke.php" <<'PHP'
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Cognesy\Tell\Tell;
use Cognesy\Tell\Resource\TellShellJobApprovals;
use Cognesy\Tell\Resource\TellResourceHost;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Shell\TellShellJobRequest;

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

$resources = TellResourceHost::shellJobs(
    project: $project,
    approval: TellShellJobApprovals::allowAll(),
)->boot();
try {
    $job = $resources->jobs()->start(TellShellJobRequest::command('printf resource-host'));
    $finished = $resources->jobs()->wait($job->id, 2_000);
    if ($finished->exitCode !== 0 || $resources->jobs()->read($job->id)->text() !== 'resource-host') {
        throw new RuntimeException('Unexpected clean-consumer resource-host result.');
    }
} finally {
    $resources->dispose();
}

echo "clean-consumer-smoke: ok\n";
PHP
php "$SMOKE_CONSUMER/smoke.php"
php "$SMOKE_CONSUMER/vendor/bin/tell" --version

echo "clean-consumer-matrix: php 8.3/8.4/8.5 x lowest/highest resolved without path repositories"
