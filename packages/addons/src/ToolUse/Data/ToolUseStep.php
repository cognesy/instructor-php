<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\Core\StepContracts\HasStepErrors;
use Cognesy\Addons\Core\StepContracts\HasStepMessages;
use Cognesy\Addons\Core\StepContracts\HasStepUsage;
use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Enums\StepType;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

final readonly class ToolUseStep implements HasStepUsage, HasStepMessages, HasStepErrors
{
    public string $id;
    public DateTimeImmutable $createdAt;

    private Messages $inputMessages;
    private Messages $outputMessages;
    private Usage $usage;

    private ToolCalls $toolCalls;
    private ToolExecutions $toolExecutions;
    private InferenceResponse $inferenceResponse;
    private StepType $stepType;

    /** @var Throwable[] */
    private array $errors;

    public function __construct(
        ?Messages          $inputMessages = null,
        ?Messages          $outputMessages = null,
        ?Usage             $usage = null,
        ?ToolCalls         $toolCalls = null,
        ?ToolExecutions    $toolExecutions = null,
        ?InferenceResponse $inferenceResponse = null,
        ?StepType          $stepType = null,
        array              $errors = [],

        ?string            $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        $this->inputMessages = $inputMessages ?? Messages::empty();
        $this->outputMessages = $outputMessages ?? Messages::empty();
        $this->usage = $usage ?? Usage::none();

        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->toolExecutions = $toolExecutions ?? new ToolExecutions();
        $this->inferenceResponse = $inferenceResponse ?? new InferenceResponse();
        $this->stepType = $stepType ?? self::inferStepType(
            $this->inferenceResponse,
            $this->toolExecutions,
        );

        $normalizedErrors = $this->normalizeErrors($errors);
        $this->errors = $normalizedErrors !== []
            ? $normalizedErrors
            : $this->toolExecutions->errors();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromArray(array $data) : self {
        return new self(
            inputMessages: isset($data['inputMessages']) ? Messages::fromArray($data['inputMessages']) : Messages::empty(),
            outputMessages: isset($data['outputMessages']) ? Messages::fromArray($data['outputMessages']) : Messages::empty(),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            toolCalls: isset($data['toolCalls']) ? ToolCalls::fromArray($data['toolCalls']) : null,
            toolExecutions: isset($data['toolExecutions']) ? ToolExecutions::fromArray($data['toolExecutions']) : null,
            inferenceResponse: isset($data['inferenceResponse']) ? InferenceResponse::fromArray($data['inferenceResponse']) : null,
            stepType: isset($data['stepType']) ? StepType::from($data['stepType']) : null,
            errors: $data['errors'] ?? [],
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
        );
    }

    public static function failure(Messages $inputMessages, Throwable $error): self
    {
        $normalized = $error instanceof Throwable ? $error : new ToolExecutionException('Unknown tool-use error');

        return new self(
            inputMessages: $inputMessages,
            outputMessages: Messages::empty(),
            usage: Usage::none(),
            toolCalls: new ToolCalls(),
            toolExecutions: new ToolExecutions(),
            inferenceResponse: null,
            stepType: StepType::Error,
            errors: [$normalized],
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function outputMessages() : Messages {
        return $this->outputMessages;
    }

    public function inputMessages() : Messages {
        return $this->inputMessages;
    }

    public function toolExecutions() : ToolExecutions {
        return $this->toolExecutions;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function finishReason() : ?InferenceFinishReason {
        return $this->inferenceResponse?->finishReason();
    }

    public function inferenceResponse() : ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function stepType() : StepType {
        return $this->stepType;
    }

    // HANDLE TOOL CALLS ////////////////////////////////////////////

    public function toolCalls() : ToolCalls {
        return $this->toolCalls;
    }

    public function hasToolCalls() : bool {
        return $this->toolCalls()->count() > 0;
    }

    // HANDLE ERRORS ////////////////////////////////////////////////

    public function hasErrors() : bool
    {
        return $this->errors !== [];
    }

    /** @return Throwable[] */
    public function errors() : array
    {
        return $this->errors;
    }

    public function errorsAsString() : string
    {
        if ($this->errors === []) {
            return '';
        }

        return implode("\n", array_map(
            fn(Throwable $error): string => $error->getMessage(),
            $this->errors,
        ));
    }

    public function errorExecutions() : ToolExecutions
    {
        return new ToolExecutions(...$this->toolExecutions->havingErrors());
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'inputMessages' => $this->inputMessages->toArray(),
            'outputMessages' => $this->outputMessages->toArray(),
            'toolCalls' => $this->toolCalls->toArray(),
            'toolExecutions' => $this->toolExecutions->toArray(),
            'errors' => array_map(
                fn(Throwable $error): array => [
                    'message' => $error->getMessage(),
                    'class' => get_class($error),
                ],
                $this->errors,
            ),
            'usage' => $this->usage->toArray(),
            'inferenceResponse' => $this->inferenceResponse->toArray(),
            'stepType' => $this->stepType->value,
        ];
    }

    public function toString() : string {
        return ($this->outputMessages->toString() ?: '(no response)')
            . ' ['
            . ($this->hasToolCalls() ? $this->toolCalls->toString() : '(-)')
            . ']';
    }

    private static function inferStepType(InferenceResponse $response, ToolExecutions $executions) : StepType
    {
        return match(true) {
            $executions->hasErrors() => StepType::Error,
            $response->hasToolCalls() => StepType::ToolExecution,
            default => StepType::FinalResponse,
        };
    }

    /**
     * @param array<int, array{message?:string,class?:string}|Throwable> $errors
     * @return Throwable[]
     */
    private function normalizeErrors(array $errors): array
    {
        $normalized = [];
        foreach ($errors as $error) {
            if ($error instanceof Throwable) {
                $normalized[] = $error;
                continue;
            }

            if (!is_array($error)) {
                continue;
            }

            $message = isset($error['message']) && is_string($error['message'])
                ? $error['message']
                : 'Unknown tool-use error';
            $class = isset($error['class']) && is_string($error['class'])
                ? $error['class']
                : ToolExecutionException::class;

            if (is_a($class, Throwable::class, true)) {
                try {
                    $normalized[] = new $class($message);
                    continue;
                } catch (Throwable) {
                    // fall through to default case
                }
            }

            $normalized[] = new ToolExecutionException($message);
        }

        return $normalized;
    }
}
