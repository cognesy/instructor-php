<?php declare(strict_types=1);

require dirname(__DIR__, 2).'/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchCancellationException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

/** @return array<string, string|bool> */
function batchLiveOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '--live') {
            $options['live'] = true;
            continue;
        }
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new InvalidArgumentException('Use --provider, --state, --model, --limit, or --cursor options.');
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, ['provider', 'state', 'model', 'limit', 'cursor'], true) || $value === '') {
            throw new InvalidArgumentException('Unknown or empty batch command option.');
        }
        $options[$name] = $value;
    }
    return $options;
}

/** @param array<string, string|bool> $options */
function batchLiveReference(array $options): BatchReference
{
    $path = $options['state'] ?? null;
    if (!is_string($path) || !is_file($path) || is_link($path) || filesize($path) > 65536) {
        throw new InvalidArgumentException('A readable saved --state file is required.');
    }
    $record = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($record) || ($record['connection'] ?? null) !== ($options['provider'] ?? null)
        || !is_array($record['reference'] ?? null)) {
        throw new InvalidArgumentException('Saved state does not match the selected provider.');
    }
    return BatchReference::fromArray($record['reference']);
}

/** @param array<string, string|bool> $options */
function batchLiveSaveReference(array $options, BatchReference $reference): void
{
    $path = $options['state'] ?? null;
    if (!is_string($path) || file_exists($path) || is_link($path) || !is_dir(dirname($path))) {
        throw new InvalidArgumentException('Submit needs a new --state path in an existing directory.');
    }
    $payload = json_encode([
        'connection' => $options['provider'],
        'reference' => $reference->toArray(),
    ], JSON_THROW_ON_ERROR);
    $temporary = tempnam(dirname($path), '.batch-reference-');
    if ($temporary === false) {
        throw new RuntimeException('Could not allocate the batch state file.');
    }
    try {
        chmod($temporary, 0600);
        if (file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload) || !link($temporary, $path)) {
            throw new RuntimeException('Could not atomically persist the batch reference.');
        }
    } finally {
        unlink($temporary);
    }
}

/** @param array<string, string|bool> $options */
function batchLiveSubmit(BatchInference $batches, array $options): void
{
    $path = $options['state'] ?? null;
    if (!is_string($path) || file_exists($path) || is_link($path) || !is_dir(dirname($path))) {
        throw new InvalidArgumentException('Submit needs a new --state path in an existing directory.');
    }
    $model = $options['model'] ?? null;
    $items = BatchItems::of(
        BatchItem::of('probe-1', new InferenceRequest(messages: Messages::fromString('Reply with one word: ready.'), model: is_string($model) ? $model : '')),
        BatchItem::of('probe-2', new InferenceRequest(messages: Messages::fromString('Reply with one word: done.'), model: is_string($model) ? $model : '')),
    );
    $job = $batches->submit($items);
    try {
        batchLiveSaveReference($options, $job->reference());
    } catch (Throwable $error) {
        throw new RuntimeException('Remote job '.$job->reference()->id()->toString().' exists but reference persistence failed.', previous: $error);
    }
    echo json_encode(['id' => $job->reference()->id()->toString(), 'status' => $job->status()->value], JSON_THROW_ON_ERROR)."\n";
}

function batchLiveStatus(BatchInference $batches, BatchReference $reference): void
{
    $job = $batches->retrieve($reference);
    echo json_encode([
        'id' => $reference->id()->toString(),
        'status' => $job->status()->value,
        'providerStatus' => $job->providerStatus(),
        'progress' => $job->progress()->toArray(),
        'resultsAvailability' => $job->resultsAvailability()->value,
    ], JSON_THROW_ON_ERROR)."\n";
}

function batchLiveResults(BatchInference $batches, BatchReference $reference): void
{
    $results = $batches->results($reference);
    echo json_encode(['availability' => $results->availability()->value, 'reason' => $results->unavailableReason()], JSON_THROW_ON_ERROR)."\n";
    if (!$results->isAvailable()) {
        return;
    }
    foreach ($results->items() as $item) {
        echo json_encode([
            'key' => $item->key(),
            'outcome' => $item->result()->isSuccess() ? 'success' : $item->result()->error()->kind()->value,
        ], JSON_THROW_ON_ERROR)."\n";
    }
}

function batchLiveCancel(BatchInference $batches, BatchReference $reference): void
{
    $receipt = $batches->cancel($reference);
    echo json_encode([
        'id' => $reference->id()->toString(),
        'acknowledged' => $receipt->acknowledged(),
        'alreadyTerminal' => $receipt->alreadyTerminal(),
        'providerStatus' => $receipt->providerStatus(),
    ], JSON_THROW_ON_ERROR)."\n";
}

/** @param array<string, string|bool> $options */
function batchLiveList(BatchInference $batches, array $options): void
{
    $limit = filter_var($options['limit'] ?? '10', FILTER_VALIDATE_INT);
    if (!is_int($limit) || $limit < 1 || $limit > 1000) {
        throw new InvalidArgumentException('--limit must be between 1 and 1000.');
    }
    $encodedCursor = $options['cursor'] ?? null;
    $cursor = null;
    if (is_string($encodedCursor)) {
        $decoded = json_decode(base64_decode($encodedCursor, true) ?: '', true, flags: JSON_THROW_ON_ERROR);
        $cursor = BatchCursor::fromArray($decoded);
    }
    $page = $batches->listJobs($limit, $cursor);
    foreach ($page->jobs() as $job) {
        echo json_encode(['id' => $job->reference()->id()->toString(), 'status' => $job->status()->value], JSON_THROW_ON_ERROR)."\n";
    }
    echo json_encode([
        'nextCursor' => $page->nextCursor() === null ? null : base64_encode(json_encode($page->nextCursor()->toArray(), JSON_THROW_ON_ERROR)),
    ], JSON_THROW_ON_ERROR)."\n";
}

try {
    $action = $argv[1] ?? '';
    $options = batchLiveOptions(array_slice($argv, 2));
    $provider = $options['provider'] ?? null;
    if (!in_array($action, ['submit', 'status', 'results', 'cancel', 'list'], true)
        || !is_string($provider)
        || !in_array($provider, ['openai', 'openai-responses', 'anthropic', 'mistral', 'gemini', 'qwen', 'xai', 'groq', 'together'], true)
        || ($options['live'] ?? false) !== true) {
        throw new InvalidArgumentException('Choose an action and supported --provider, and pass --live to permit provider I/O.');
    }
    $inference = LLMConfig::fromPreset($provider);
    if ($inference->apiKey === '' || str_contains($inference->apiKey, '${')) {
        throw new InvalidArgumentException('The selected provider API key is not configured.');
    }
    $batches = BatchInference::fromConfig(BatchConfig::fromLLMConfig($inference));
    $reference = $action === 'list' || $action === 'submit' ? null : batchLiveReference($options);
    match ($action) {
        'submit' => batchLiveSubmit($batches, $options),
        'status' => batchLiveStatus($batches, $reference),
        'results' => batchLiveResults($batches, $reference),
        'cancel' => batchLiveCancel($batches, $reference),
        'list' => batchLiveList($batches, $options),
    };
} catch (BatchSubmissionException $error) {
    fwrite(STDERR, json_encode([
        'error' => 'batch_submission',
        'stage' => $error->stage(),
        'certainty' => $error->certainty()->value,
        'artifactIds' => $error->artifactIds(),
    ], JSON_THROW_ON_ERROR)."\n");
    exit(1);
} catch (BatchCancellationException $error) {
    fwrite(STDERR, json_encode([
        'error' => 'batch_cancellation',
        'id' => $error->reference()->id()->toString(),
        'certainty' => $error->certainty()->value,
    ], JSON_THROW_ON_ERROR)."\n");
    exit(1);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(2);
} catch (Throwable $error) {
    fwrite(STDERR, 'Batch command failed: '.$error::class."\n");
    exit(1);
}
