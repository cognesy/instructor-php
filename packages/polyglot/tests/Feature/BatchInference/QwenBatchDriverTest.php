<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\QwenBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class QwenBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param list<HttpResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        return array_shift($this->responses) ?? throw new LogicException('No scripted Qwen response remains.');
    }
}

function qwenBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/qwen/'.$name);
}

function qwenBatchConfig(string $apiBase, string $model): BatchConfig
{
    return BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: $apiBase,
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: $model,
        driver: 'qwen',
    ));
}

it('runs a Beijing Qwen batch with top-level thinking, keyed outcomes and regional pagination', function () {
    $completed = qwenBatchFixture('job-completed.json');
    $http = new QwenBatchScriptedHttpClient([
        HttpResponse::sync(200, [], qwenBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], '{"id":"batch_qwen_test","endpoint":"/v1/chat/completions","status":"cancelling"}'),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::streamingFromIterable(200, [], str_split(qwenBatchFixture('output.jsonl'), 11)),
        HttpResponse::streamingFromIterable(200, [], str_split(qwenBatchFixture('error.jsonl'), 7)),
        HttpResponse::sync(200, [], qwenBatchFixture('list.json')),
        HttpResponse::sync(200, [], '{"object":"list","data":[],"has_more":false}'),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public ?BatchFileUpload $request = null;
        public string $body = '';

        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, '{"id":"file_qwen_input"}');
        }
    };
    $config = qwenBatchConfig('https://dashscope.aliyuncs.com/compatible-mode/v1', 'qwen3.8-max');
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, uploader: $uploader));
    $items = BatchItems::of(
        BatchItem::of('qwen-ok', new InferenceRequest(messages: Messages::fromString('A'), options: ['enable_thinking' => false, 'thinking_budget' => 50])),
        BatchItem::of('qwen-failed', new InferenceRequest(messages: Messages::fromString('B'), options: ['enable_thinking' => false, 'thinking_budget' => 50])),
    );

    $submitted = $batches->submit($items, new QwenBatchOptions('2d', 'Research run'));
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $receipt = $batches->cancel($reference);
    $finished = $batches->retrieve($reference);
    $outcomes = iterator_to_array($batches->results($reference)->items());
    $page = $batches->listJobs(limit: 2);
    $batches->listJobs(limit: 2, cursor: $page->nextCursor());

    $line = json_decode(explode("\n", $uploader->body)[0], true, flags: JSON_THROW_ON_ERROR);
    $createBody = json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($config->scope())->toContain('dashscope.aliyuncs.com')
        ->and($submitted->status())->toBe(BatchStatus::Pending)
        ->and($receipt->providerStatus())->toBe('cancelling')
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finished->progress()->failed())->toBe(1)
        ->and($outcomes[0]->key())->toBe('qwen-ok')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toContain('Hello from Qwen')
        ->and($outcomes[1]->result()->isFailure())->toBeTrue()
        ->and($line['body']['enable_thinking'])->toBeFalse()
        ->and($line['body']['thinking_budget'])->toBe(50)
        ->and(array_key_exists('extra_body', $line['body']))->toBeFalse()
        ->and($uploader->request->url())->toBe('https://dashscope.aliyuncs.com/compatible-mode/v1/files')
        ->and($createBody['completion_window'])->toBe('2d')
        ->and($createBody['metadata']['ds_name'])->toBe('Research run')
        ->and($page->nextCursor()?->token())->toBe('batch_qwen_test')
        ->and($http->requests[7]->url())->toBe('https://dashscope.aliyuncs.com/compatible-mode/v1/batches?limit=2&after=batch_qwen_test');
});

it('rejects a model unavailable in Singapore before upload and keeps regions separate', function () {
    $http = new QwenBatchScriptedHttpClient([]);
    $uploader = new class () implements CanUploadBatchFile {
        public int $calls = 0;
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->calls++;
            throw new LogicException('Upload must not happen.');
        }
    };
    $singapore = qwenBatchConfig('https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'qwen3.8-max');
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($singapore, http: $http, uploader: $uploader));
    $one = BatchItems::of(BatchItem::of('one', new InferenceRequest(messages: Messages::fromString('A'))));
    $beijing = qwenBatchConfig('https://dashscope.aliyuncs.com/compatible-mode/v1', 'qwen3.8-max');
    $foreign = new BatchReference(new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_foreign'), 'qwen', $beijing->scope(), '/v1/chat/completions', 'qwen-chat');

    expect(fn () => $batches->submit($one))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $batches->retrieve($foreign))->toThrow(InvalidArgumentException::class)
        ->and($uploader->calls)->toBe(0)
        ->and($http->requests)->toBe([]);
});

it('rejects mixed Qwen thinking modes before any remote mutation', function () {
    $http = new QwenBatchScriptedHttpClient([]);
    $uploader = new class () implements CanUploadBatchFile {
        public int $calls = 0;
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->calls++;
            throw new LogicException('Upload must not happen.');
        }
    };
    $config = qwenBatchConfig('https://dashscope.aliyuncs.com/compatible-mode/v1', 'qwen3.8-max');
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, uploader: $uploader));
    $items = BatchItems::of(
        BatchItem::of('a', new InferenceRequest(messages: Messages::fromString('A'), options: ['enable_thinking' => true])),
        BatchItem::of('b', new InferenceRequest(messages: Messages::fromString('B'), options: ['enable_thinking' => false])),
    );

    expect(fn () => $batches->submit($items))->toThrow(InvalidArgumentException::class)
        ->and($uploader->calls)->toBe(0)
        ->and($http->requests)->toBe([]);
});

it('keeps regional Qwen failures distinct from retained results after expiry', function () {
    foreach ([
        ['https://dashscope.aliyuncs.com/compatible-mode/v1', 'job-expired-artifacts.json', 'batch_qwen_expired', BatchStatus::Expired, BatchResultsAvailability::Final],
        ['https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'job-failed-no-artifacts.json', 'batch_qwen_failed', BatchStatus::Failed, BatchResultsAvailability::Unavailable],
    ] as [$apiBase, $fixture, $id, $status, $availability]) {
        $response = qwenBatchFixture($fixture);
        $responses = [HttpResponse::sync(200, [], $response), HttpResponse::sync(200, [], $response)];
        if ($availability === BatchResultsAvailability::Final) {
            $responses[] = HttpResponse::streamingFromIterable(200, [], [qwenBatchFixture('output.jsonl')]);
            $responses[] = HttpResponse::streamingFromIterable(200, [], [qwenBatchFixture('error.jsonl')]);
        }
        $http = new QwenBatchScriptedHttpClient($responses);
        $config = qwenBatchConfig($apiBase, 'qwen-turbo');
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $reference = new BatchReference(
            new \Cognesy\Polyglot\BatchInference\Data\BatchJobId($id),
            'qwen',
            $config->scope(),
            '/v1/chat/completions',
            'qwen-chat',
            2,
            true,
        );

        $job = $batches->retrieve($reference);
        $results = $batches->results($reference);
        expect($job->status())->toBe($status)
            ->and($job->resultsAvailability())->toBe($availability)
            ->and($results->availability())->toBe($availability)
            ->and($http->requests[0]->url())->toStartWith($apiBase);
        if ($availability === BatchResultsAvailability::Final) {
            $outcomes = iterator_to_array($results->items());
            expect($outcomes)->toHaveCount(2)
                ->and($outcomes[0]->key())->toBe('qwen-ok')
                ->and($outcomes[1]->result()->isFailure())->toBeTrue();
            continue;
        }
        expect($job->failureCode())->toBe('input_file_invalid')
            ->and(fn () => iterator_to_array($results->items()))->toThrow(LogicException::class, 'Batch results are not available.')
            ->and($http->requests)->toHaveCount(2);
    }
});
