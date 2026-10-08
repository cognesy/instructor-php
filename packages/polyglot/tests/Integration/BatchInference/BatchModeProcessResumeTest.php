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
use Cognesy\Polyglot\BatchInference\Config\GeminiBatchOptions;
use Cognesy\Polyglot\BatchInference\Config\MistralBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Enums\GeminiBatchInputMode;
use Cognesy\Polyglot\BatchInference\Enums\MistralBatchInputMode;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class ModeSubmitHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
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
        return array_shift($this->responses) ?? throw new LogicException('Unexpected batch submission request.');
    }
}

final class ModeSubmitUpload implements CanUploadBatchFile
{
    public function __construct(private string $mode)
    {
    }

    public function upload(BatchFileUpload $request): BatchFileUploadResponse
    {
        $body = match ($this->mode) {
            'mistral-file' => '{"id":"file-input"}',
            'qwen-file' => '{"id":"file_qwen_input"}',
            default => throw new LogicException('Unexpected batch file upload.'),
        };
        return new BatchFileUploadResponse(200, $body);
    }
}

final class ModeSubmitBodySender implements CanSendBatchFileBody
{
    public function __construct(private string $mode)
    {
    }

    public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
    {
        $body = match ($this->mode) {
            'mistral-inline' => '{"id":"job_inline","status":"QUEUED","endpoint":"/v1/chat/completions","model":"mistral-test"}',
            'gemini-file' => '{"file":{"name":"files/gemini-input"}}',
            'gemini-inline' => (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/gemini/inline-submitted.json'),
            default => throw new LogicException('Unexpected batch file-body submission.'),
        };
        return new BatchFileUploadResponse(200, $body);
    }
}

it('recovers keyed results in a fresh PHP process for every Mistral Gemini and Qwen input mode', function () {
    foreach ([
        'mistral-file' => ['https://api.mistral.ai/v1', '/chat/completions', 'mistral-test', 'mistral', 'job_test', ['item-ok' => 'success', 'item-bad' => 'failure']],
        'mistral-inline' => ['https://api.mistral.ai/v1', '/chat/completions', 'mistral-test', 'mistral', 'job_inline', ['item-ok' => 'success', 'item-bad' => 'failure']],
        'gemini-file' => ['https://generativelanguage.googleapis.com/v1beta', '/models/{model}:generateContent', 'gemini-2.5-flash-lite', 'gemini', 'batches/gemini-file', ['gemini-ok' => 'success', 'gemini-failed' => 'failure']],
        'gemini-inline' => ['https://generativelanguage.googleapis.com/v1beta', '/models/{model}:generateContent', 'gemini-2.5-flash-lite', 'gemini', 'batches/gemini-inline', ['gemini-ok' => 'success', 'gemini-failed' => 'failure']],
        'qwen-file' => ['https://dashscope-intl.aliyuncs.com/compatible-mode/v1', '/chat/completions', 'qwen-turbo', 'qwen', 'batch_qwen_test', ['qwen-ok' => 'success', 'qwen-failed' => 'failure']],
    ] as $mode => [$apiUrl, $endpoint, $model, $driver, $id, $expected]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl, apiKey: 'test-secret', endpoint: $endpoint, model: $model, driver: $driver,
        ));
        $fixtures = dirname(__DIR__, 2).'/Fixtures/BatchInference/';
        $responses = match ($mode) {
            'mistral-file' => [HttpResponse::sync(200, [], (string) file_get_contents($fixtures.'mistral/job-submitted.json'))],
            'gemini-file' => [
                HttpResponse::sync(200, ['X-Goog-Upload-URL' => 'https://generativelanguage.googleapis.com/upload/v1beta/files?upload_id=test'], ''),
                HttpResponse::sync(200, [], (string) file_get_contents($fixtures.'gemini/file-submitted.json')),
            ],
            'qwen-file' => [HttpResponse::sync(200, [], (string) file_get_contents($fixtures.'qwen/job-submitted.json'))],
            default => [],
        };
        $keys = array_keys($expected);
        $items = BatchItems::of(
            BatchItem::of($keys[0], new InferenceRequest(messages: Messages::fromString('A'))),
            BatchItem::of($keys[1], new InferenceRequest(messages: Messages::fromString('B'))),
        );
        $options = match ($mode) {
            'mistral-inline' => new MistralBatchOptions(MistralBatchInputMode::Inline),
            'gemini-inline' => new GeminiBatchOptions(GeminiBatchInputMode::Inline, 'resumed-inline'),
            default => null,
        };
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(
            $config,
            http: new ModeSubmitHttp($responses),
            uploader: new ModeSubmitUpload($mode),
            bodySender: new ModeSubmitBodySender($mode),
        ));
        $submitted = $batches->submit($items, $options);
        expect($submitted->reference()->id()->toString())->toBe($id);

        $worker = dirname(__DIR__, 2).'/Support/BatchInference/resume-modes-worker.php';
        $process = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        expect($process)->not->toBeFalse();
        fwrite($pipes[0], json_encode(['mode' => $mode, 'reference' => $submitted->reference()->toArray()], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        expect($exit)->toBe(0, $mode.': '.$error);
        $resumed = json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
        expect($resumed['status'])->toBe('completed')
            ->and($resumed['reference'])->toBe($submitted->reference()->toArray())
            ->and($resumed['items'])->toBe($expected);
    }
});
