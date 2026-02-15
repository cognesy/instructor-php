<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Collection\ArrayList;
use Cognesy\Utils\Str;

class ValidationRetryUser
{
    use ValidationMixin;

    public string $name;
    /** @var string[] */
    public array $details;

    public function validate() : ValidationResult {
        $data = implode("\n", $this->details);
        $hasPii = Str::contains($data, 'ssn=', false) || Str::contains($data, 'phone=', false);
        return match($hasPii) {
            true => ValidationResult::fieldError(
                field: 'details',
                value: $data,
                message: 'Details contain PII, remove it from the response.',
            ),
            false => ValidationResult::valid(),
        };
    }
}

final class RecordingInferenceDriver implements CanHandleInference
{
    /** @var ArrayList<InferenceResponse> */
    private ArrayList $responses;
    /** @var ArrayList<array> */
    private ArrayList $requests;

    /** @param InferenceResponse[] $responses */
    public function __construct(array $responses) {
        $this->responses = ArrayList::fromArray($responses);
        $this->requests = ArrayList::empty();
    }

    /** @return ArrayList<array> */
    public function recordedRequests(): ArrayList {
        return $this->requests;
    }

    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $this->requests = $this->requests->withAppended($request->messages());
        return match($this->responses->isEmpty()) {
            true => new InferenceResponse(content: ''),
            false => $this->dequeueResponse(),
        };
    }

    /** @return iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        return [];
    }

    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            outputModes: OutputMode::cases(),
            streaming: false,
            toolCalling: true,
            jsonSchema: true,
            responseFormatWithTools: true,
        );
    }

    private function dequeueResponse(): InferenceResponse {
        $response = $this->responses->itemAt(0);
        $this->responses = $this->responses->withRemovedAt(0);
        return $response;
    }
}

function messageIndexOf(array $messages, string $needle): int {
    foreach ($messages as $index => $message) {
        $content = $message['content'] ?? '';
        $text = match(true) {
            is_array($content) => json_encode($content),
            default => (string) $content,
        };
        if (Str::contains($text, $needle, false)) {
            return $index;
        }
    }
    return -1;
}

it('includes validation errors in retry messages for the next LLM attempt', function () {
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
                'role=developer',
            ],
        ])),
    ];
    $driver = new RecordingInferenceDriver($responses);

    $result = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('Extract details.')
        ->withResponseClass(ValidationRetryUser::class)
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(1)
        ->getObject();

    expect($result)->toBeInstanceOf(ValidationRetryUser::class);

    $requests = $driver->recordedRequests();
    expect($requests->count())->toBe(2);

    $secondMessages = $requests->itemAt(1);
    $feedbackIndex = messageIndexOf($secondMessages, 'FEEDBACK:');
    $errorIndex = messageIndexOf($secondMessages, 'Details contain PII');
    $correctedIndex = messageIndexOf($secondMessages, 'CORRECTED RESPONSE:');

    expect($feedbackIndex)->toBeGreaterThanOrEqual(0);
    expect($errorIndex)->toBeGreaterThan($feedbackIndex);
    expect($correctedIndex)->toBeGreaterThan($errorIndex);
});
