<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

require __DIR__.'/autoload.php';

final class ModeResumeHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string, string> $fixtures */
    public function __construct(private array $fixtures)
    {
    }

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $url = $request->url();
        $this->requests[] = $url;
        $fixture = $this->fixtures[$url] ?? throw new RuntimeException('Unexpected resumed batch request.');
        $body = file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/'.$fixture);
        if ($body === false) {
            throw new RuntimeException('Missing resumed batch fixture.');
        }
        return str_ends_with($fixture, '.jsonl')
            ? HttpResponse::streamingFromIterable(200, [], str_split($body, 13))
            : HttpResponse::sync(200, [], $body);
    }
}

$input = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($input) || !is_string($input['mode'] ?? null) || !is_array($input['reference'] ?? null)) {
    throw new RuntimeException('Mode resume worker needs a mode and serialized reference.');
}
$mode = $input['mode'];
$reference = BatchReference::fromArray($input['reference']);
[$apiUrl, $endpoint, $model, $driver, $fixtures] = match ($mode) {
    'mistral-file', 'mistral-inline' => [
        'https://api.mistral.ai/v1', '/chat/completions', 'mistral-test', 'mistral', [
            'https://api.mistral.ai/v1/batch/jobs/job_test' => 'mistral/job-success.json',
            'https://api.mistral.ai/v1/batch/jobs/job_inline' => 'mistral/job-inline-completed.json',
            'https://api.mistral.ai/v1/batch/jobs/job_inline?inline=true' => 'mistral/job-inline-completed.json',
            'https://api.mistral.ai/v1/files/file-output/content' => 'mistral/output.jsonl',
            'https://api.mistral.ai/v1/files/file-error/content' => 'mistral/error.jsonl',
            'https://api.mistral.ai/v1/files/file-inline-error/content' => 'mistral/error.jsonl',
        ],
    ],
    'gemini-file', 'gemini-inline' => [
        'https://generativelanguage.googleapis.com/v1beta', '/models/{model}:generateContent', 'gemini-2.5-flash-lite', 'gemini', [
            'https://generativelanguage.googleapis.com/v1beta/batches/gemini-file' => 'gemini/file-completed.json',
            'https://generativelanguage.googleapis.com/v1beta/batches/gemini-inline' => 'gemini/inline-completed.json',
            'https://generativelanguage.googleapis.com/download/v1beta/files/gemini-output:download?alt=media' => 'gemini/file-results.jsonl',
        ],
    ],
    'qwen-file' => [
        'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', '/chat/completions', 'qwen-turbo', 'qwen', [
            'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/batches/batch_qwen_test' => 'qwen/job-completed.json',
            'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/files/file_qwen_output/content' => 'qwen/output.jsonl',
            'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/files/file_qwen_error/content' => 'qwen/error.jsonl',
        ],
    ],
    default => throw new RuntimeException('Unknown mode resume worker mode.'),
};
$config = BatchConfig::fromLLMConfig(new LLMConfig(
    apiUrl: $apiUrl, apiKey: 'test-secret', endpoint: $endpoint, model: $model, driver: $driver,
));
$http = new ModeResumeHttp($fixtures);
$batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
$job = $batches->retrieve($reference);
$items = [];
foreach ($batches->results($reference)->items() as $item) {
    $items[$item->key()] = $item->result()->isSuccess() ? 'success' : 'failure';
}
echo json_encode([
    'status' => $job->status()->value,
    'reference' => $job->reference()->toArray(),
    'items' => $items,
    'requests' => $http->requests,
], JSON_THROW_ON_ERROR);
