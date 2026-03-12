<?php declare(strict_types=1);

/**
 * Full-stack Instructor benchmarks using MockHttpDriver.
 *
 * Unlike InstructorBench (which uses FakeInferenceDriver and skips HTTP),
 * these benchmarks exercise the entire pipeline: HTTP request building,
 * driver adapter serialization, HTTP response parsing, JSON extraction,
 * deserialization, and object hydration.
 *
 * ## How to run
 *
 *   composer bench -- --filter=FullStackBench
 *   composer bench -- --filter=benchFullStack
 *   composer bench -- --report=aggregate
 */

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Enums\OutputMode;

require_once __DIR__ . '/BenchModels.php';

final class FullStackBench
{
    // ========================================================================
    // Data generators
    // ========================================================================

    /** Build JSON for a single object, padded to target size */
    private function makeObjectJson(int $targetBytes): string {
        $base = [
            'fullName' => 'Dr. Jonathan Doe',
            'age' => 42,
            'bio' => '',
            'email' => 'jdoe@example.com',
            'phone' => '+1-555-0142',
        ];
        $baseJson = json_encode($base, JSON_THROW_ON_ERROR);
        $remaining = max(0, $targetBytes - strlen($baseJson));
        $base['bio'] = str_repeat('A seasoned engineer. ', (int) ceil($remaining / 21));
        $base['bio'] = substr($base['bio'], 0, $remaining);
        return json_encode($base, JSON_THROW_ON_ERROR);
    }

    /** Wrap object JSON in an OpenAI-style chat completion response */
    private function makeOpenAIResponse(string $contentJson): array {
        return [
            'choices' => [[
                'message' => ['content' => $contentJson],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150,
            ],
        ];
    }

    /** Build JSON for a Sequence */
    private function makeSequenceJson(int $itemCount): string {
        $items = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = ['id' => $i, 'name' => "item-$i"];
        }
        return json_encode(['list' => $items], JSON_THROW_ON_ERROR);
    }

    /** Split JSON string into ~20-char chunks */
    private function tokenize(string $json, int $chunkSize = 20): array {
        return str_split($json, $chunkSize);
    }

    /** Create an HttpClient with a mock that always returns the given OpenAI sync response */
    private function makeSyncHttpClient(array $openAIResponse): \Cognesy\Http\Contracts\CanSendHttpRequests {
        return (new HttpClientBuilder())
            ->withMock(function ($mock) use ($openAIResponse) {
                $mock->on()
                    ->post()
                    ->replyJson($openAIResponse);
            })
            ->create();
    }

    /**
     * Convert content chunks into OpenAI SSE delta payloads.
     * Last chunk gets finish_reason: 'stop'.
     */
    private function makeSSEPayloads(array $contentChunks): array {
        $payloads = [];
        $last = count($contentChunks) - 1;
        foreach ($contentChunks as $i => $chunk) {
            $payloads[] = [
                'choices' => [[
                    'delta' => ['content' => $chunk],
                    'finish_reason' => $i === $last ? 'stop' : null,
                ]],
            ];
        }
        return $payloads;
    }

    /** Create an HttpClient with a mock that returns SSE stream */
    private function makeStreamHttpClient(array $ssePayloads): \Cognesy\Http\Contracts\CanSendHttpRequests {
        return (new HttpClientBuilder())
            ->withMock(function ($mock) use ($ssePayloads) {
                $mock->on()
                    ->post()
                    ->withStream(true)
                    ->replySSEFromJson($ssePayloads);
            })
            ->create();
    }

    /** Run a full-stack sync extraction */
    private function runSync(int $targetBytes): void {
        $json = $this->makeObjectJson($targetBytes);
        $http = $this->makeSyncHttpClient($this->makeOpenAIResponse($json));
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(httpClient: $http, llmDriver: 'openai'))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class, model: 'gpt-4o-mini')
            ->get();
        assert($result->fullName !== '');
    }

    /** Run a full-stack streaming object extraction via partials() */
    private function runStreamPartials(int $targetBytes): void {
        $json = $this->makeObjectJson($targetBytes);
        $chunks = $this->tokenize($json);
        $http = $this->makeStreamHttpClient($this->makeSSEPayloads($chunks));
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(httpClient: $http, llmDriver: 'openai', outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class, model: 'gpt-4o-mini')
            ->withStreaming(true)
            ->stream();
        $count = 0;
        foreach ($stream->partials() as $partial) {
            $count++;
        }
        assert($count > 0);
    }

    /** Run a full-stack streaming sequence extraction */
    private function runStreamSequence(int $itemCount): int {
        $json = $this->makeSequenceJson($itemCount);
        $chunks = $this->tokenize($json);
        $http = $this->makeStreamHttpClient($this->makeSSEPayloads($chunks));
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(httpClient: $http, llmDriver: 'openai', outputMode: OutputMode::Json))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class), model: 'gpt-4o-mini')
            ->withStreaming(true)
            ->stream();
        $received = 0;
        foreach ($stream->sequence() as $item) {
            $received++;
        }
        return $received;
    }

    // ========================================================================
    // FULL STACK SYNC: Single object — scaling with JSON size
    // ========================================================================

    /**
     * @Revs(100)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "sync", "object"})
     */
    public function benchFullStackSync128B(): void {
        $this->runSync(128);
    }

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "sync", "object"})
     */
    public function benchFullStackSync1KB(): void {
        $this->runSync(1024);
    }

    /**
     * @Revs(10)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "sync", "object"})
     */
    public function benchFullStackSync10KB(): void {
        $this->runSync(10240);
    }

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "sync", "object"})
     */
    public function benchFullStackSync50KB(): void {
        $this->runSync(51200);
    }

    /**
     * @Revs(3)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "sync", "object"})
     */
    public function benchFullStackSync100KB(): void {
        $this->runSync(102400);
    }

    // ========================================================================
    // FULL STACK STREAMING: Object partials — scaling with JSON size
    // Each ~20-char content chunk is wrapped in an OpenAI SSE delta event
    // ========================================================================

    /**
     * @Revs(100)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "stream", "object", "partials"})
     */
    public function benchFullStackStreamPartials128B(): void {
        $this->runStreamPartials(128);
    }

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "stream", "object", "partials"})
     */
    public function benchFullStackStreamPartials1KB(): void {
        $this->runStreamPartials(1024);
    }

    /**
     * @Revs(1)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "stream", "object", "partials"})
     */
    public function benchFullStackStreamPartials10KB(): void {
        $this->runStreamPartials(10240);
    }

    /**
     * @Revs(1)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "stream", "object", "partials"})
     */
    public function benchFullStackStreamPartials50KB(): void {
        $this->runStreamPartials(51200);
    }

    // NOTE: 100KB streaming partials OOMs at 128MB — IncrementalJsonParser
    // re-parses the full accumulated JSON on every ~20-char delta (~5120 times).
    // This is a known scaling limitation for large flat-object streaming.

    // ========================================================================
    // FULL STACK STREAMING: Sequence — scaling with item count
    // ========================================================================

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "stream", "sequence"})
     */
    public function benchFullStackStreamSequence1KChunks(): void {
        $received = $this->runStreamSequence(650);
        assert($received === 650);
    }

    /**
     * @Revs(1)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"fullstack", "stream", "sequence"})
     */
    public function benchFullStackStreamSequence5KChunks(): void {
        $received = $this->runStreamSequence(3300);
        assert($received === 3300);
    }
}
