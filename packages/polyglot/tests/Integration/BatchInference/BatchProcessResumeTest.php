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
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class ProcessSubmitHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        expect($request->url())->toBe('https://api.openai.com/v1/batches');
        $body = file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/openai/job-submitted.json');
        return HttpResponse::sync(200, [], (string) $body);
    }
}

it('submits in one PHP process and retrieves keyed output and errors in another', function () {
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $uploader = new class () implements CanUploadBatchFile {
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            return new BatchFileUploadResponse(200, '{"id":"file-input"}');
        }
    };
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: new ProcessSubmitHttp(), uploader: $uploader));
    $submitted = $batches->submit(BatchItems::of(
        BatchItem::of('item-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('item-expired', new InferenceRequest(messages: Messages::fromString('B'))),
    ));

    $worker = dirname(__DIR__, 2).'/Support/BatchInference/resume-worker.php';
    $process = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    expect($process)->not->toBeFalse();
    fwrite($pipes[0], json_encode($submitted->reference()->toArray(), JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    expect($exit)->toBe(0, $error)
        ->and($error)->toBe('');
    $resumed = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    expect($resumed['status'])->toBe('completed')
        ->and($resumed['reference'])->toBe($submitted->reference()->toArray())
        ->and($resumed['items'])->toBe(['item-ok' => 'success', 'item-expired' => 'expired'])
        ->and($resumed['requests'])->toBe([
            'https://api.openai.com/v1/batches/batch_test',
            'https://api.openai.com/v1/batches/batch_test',
            'https://api.openai.com/v1/files/file-output/content',
            'https://api.openai.com/v1/files/file-error/content',
        ]);
});
