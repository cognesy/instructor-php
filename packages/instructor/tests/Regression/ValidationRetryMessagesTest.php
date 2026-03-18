<?php

declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Utils\Collection\ArrayList;
use Cognesy\Utils\Str;

class ValidationRetryUser
{
    use ValidationMixin;

    public string $name;

    /** @var string[] */
    public array $details;

    public function validate(): ValidationResult
    {
        $data = implode("\n", $this->details);
        $hasPii = Str::contains($data, 'ssn=', false) || Str::contains($data, 'phone=', false);
        $hasUnnormalizedRole = Str::contains($data, 'role=contractor', false);

        return match ($hasPii) {
            true => ValidationResult::fieldError(
                field: 'details',
                value: $data,
                message: 'Details contain PII, remove it from the response.',
            ),
            false => match ($hasUnnormalizedRole) {
                true => ValidationResult::fieldError(
                    field: 'details',
                    value: $data,
                    message: 'Role must be normalized to engineer.',
                ),
                false => ValidationResult::valid(),
            },
        };
    }
}

final class RecordingInferenceRequestDriver implements CanProcessInferenceRequest
{
    /** @var ArrayList<InferenceResponse> */
    private ArrayList $responses;

    /** @var ArrayList<Messages> */
    private ArrayList $requests;

    /** @param InferenceResponse[] $responses */
    public function __construct(array $responses)
    {
        $this->responses = ArrayList::fromArray($responses);
        $this->requests = ArrayList::empty();
    }

    /** @return ArrayList<Messages> */
    public function recordedRequests(): ArrayList
    {
        return $this->requests;
    }

    public function makeResponseFor(InferenceRequest $request): InferenceResponse
    {
        $this->requests = $this->requests->withAppended($request->messages());

        return match ($this->responses->isEmpty()) {
            true => new InferenceResponse(content: ''),
            false => $this->dequeueResponse(),
        };
    }

    /** @return iterable<PartialInferenceDelta> */
    public function makeStreamDeltasFor(InferenceRequest $request): iterable
    {
        return [];
    }

    public function capabilities(?string $model = null): DriverCapabilities
    {
        return new DriverCapabilities(
            streaming: false,
            toolCalling: true,
            toolChoice: true,
            responseFormatJsonObject: true,
            responseFormatJsonSchema: true,
            responseFormatWithTools: true,
        );
    }

    private function dequeueResponse(): InferenceResponse
    {
        $response = $this->responses->itemAt(0);
        $this->responses = $this->responses->withRemovedAt(0);

        return $response;
    }
}

function messageIndexOf(Messages $messages, string $needle): int
{
    foreach ($messages->toArray() as $index => $message) {
        $content = $message['content'] ?? '';
        $text = match (true) {
            is_array($content) => json_encode($content),
            default => (string) $content,
        };
        if (Str::contains($text, $needle, false)) {
            return $index;
        }
    }

    return -1;
}

it('carries forward each validation error into subsequent retry message sequences', function () {
    $responses = [
        new InferenceResponse(content: json_encode([
            'name' => 'Jason',
            'details' => [
                'name=Jason',
                'age=25',
                'role=developer',
                'phone=+1 123 34 45',
                'ssn=123-45-6789',
            ],
        ])),
        new InferenceResponse(content: json_encode([
            'name' => 'Jason',
            'details' => [
                'name=Jason',
                'age=25',
                'role=contractor',
            ],
        ])),
        new InferenceResponse(content: json_encode([
            'name' => 'Jason',
            'details' => [
                'name=Jason',
                'age=25',
                'role=engineer',
            ],
        ])),
    ];
    $driver = new RecordingInferenceRequestDriver($responses);

    $result = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        outputMode: OutputMode::Json,
        maxRetries: 2,
    )))
        ->withMessages('Extract details.')
        ->withResponseClass(ValidationRetryUser::class)
        ->getObject();

    expect($result)->toBeInstanceOf(ValidationRetryUser::class);

    $requests = $driver->recordedRequests();
    expect($requests->count())->toBe(3);

    $secondMessages = $requests->itemAt(1);
    $firstResponseIndex = messageIndexOf($secondMessages, 'ssn=123-45-6789');
    $firstErrorIndex = messageIndexOf($secondMessages, 'Details contain PII');

    expect($firstResponseIndex)->toBeGreaterThanOrEqual(0);
    expect($firstErrorIndex)->toBeGreaterThan($firstResponseIndex);

    $thirdMessages = $requests->itemAt(2);
    $thirdFirstResponseIndex = messageIndexOf($thirdMessages, 'ssn=123-45-6789');
    $thirdFirstErrorIndex = messageIndexOf($thirdMessages, 'Details contain PII');
    $thirdSecondResponseIndex = messageIndexOf($thirdMessages, 'role=contractor');
    $thirdSecondErrorIndex = messageIndexOf($thirdMessages, 'Role must be normalized to engineer.');

    expect($thirdFirstResponseIndex)->toBeGreaterThanOrEqual(0);
    expect($thirdFirstErrorIndex)->toBeGreaterThan($thirdFirstResponseIndex);
    expect($thirdSecondResponseIndex)->toBeGreaterThan($thirdFirstErrorIndex);
    expect($thirdSecondErrorIndex)->toBeGreaterThan($thirdSecondResponseIndex);
});
