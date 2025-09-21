<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Enums\StepType;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

final class ReActDriver implements CanUseTools
{
    private LLMProvider $llm;
    private ?HttpClient $httpClient = null;
    private string $model;
    private array $options;
    private bool $finalViaInference;
    private ?string $finalModel;
    private array $finalOptions;
    private int $maxRetries;
    private OutputMode $mode;

    public function __construct(
        ?LLMProvider $llm = null,
        ?HttpClient $httpClient = null,
        string $model = '',
        array $options = [],
        bool $finalViaInference = false,
        ?string $finalModel = null,
        array $finalOptions = [],
        int $maxRetries = 2,
        OutputMode $mode = OutputMode::Json,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->model = $model;
        $this->options = $options;
        $this->finalViaInference = $finalViaInference;
        $this->finalModel = $finalModel;
        $this->finalOptions = $finalOptions;
        $this->maxRetries = $maxRetries;
        $this->mode = $mode;
    }

    public function useTools(ToolUseState $state, Tools $tools, ToolExecutor $executor): ToolUseStep {
        $messages = $state->messages();
        $system = ReActPrompt::buildSystemPrompt($tools);
        [$decisionStructure, $toolArgumentStructures] = $this->buildDecisionStructures($tools);

        $extraction = Result::try(fn() => $this->extractDecisionWithUsage(
            messages: $messages,
            system: $system,
            decisionStructure: $decisionStructure,
        ));
        if ($extraction->isFailure()) {
            return $this->buildExtractionFailureStep($extraction->error(), $messages);
        }

        /** @var PendingStructuredOutput $pendingDecision */
        $pendingDecision = $extraction->unwrap();

        /**
         * NOTE/TODO (design choice):
         * Currently we call `$pendingDecision->get()` directly. If StructuredOutput fails
         * to deserialize/validate the model (e.g., malformed or non-JSON content), it throws
         * a ValidationException after configured retries. That exception is allowed to bubble
         * up to the caller. An alternative behavior would be to treat such extraction failures
         * as part of the ReAct control flow, convert them into a ToolExecution-style observation,
         * and continue (or stop) deterministically. We already have `buildExtractionFailureStep()`
         * for failures occurring during `extractDecisionWithUsage()`, but failures raised by
         * `$pendingDecision->get()` happen later in the flow. If we decide to change semantics,
         * we can wrap the line below in a try/catch and map the exception to
         * `buildExtractionFailureStep($e)` for better resilience and observability.
         * For now, we preserve strict failure semantics to surface invalid decision extraction
         * to the caller explicitly.
         */
        /** @var Structure $structuredDecision */
        $structuredDecision = $pendingDecision->get();
        $validation = $structuredDecision->validate();
        if ($validation->isInvalid()) {
            return $this->buildValidationFailureStep($validation, $messages);
        }

        $decision = $this->makeDecision($structuredDecision, $toolArgumentStructures);
        $inferenceResponse = $pendingDecision->response();
        $usage = $inferenceResponse->usage();

        return match (true) {
            $decision->isCall() => $this->buildToolCallStep($decision, $usage, $inferenceResponse, $state, $executor, $messages),
            default => $this->buildFinalAnswerStep($decision, $usage, $inferenceResponse, $state, $messages),
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Extracts a ReAct decision via StructuredOutput and returns usage data.
     */
    private function extractDecisionWithUsage(Messages $messages, string $system, Structure $decisionStructure): PendingStructuredOutput {
        $structured = (new StructuredOutput())
            ->withSystem($system)
            ->withMessages($messages)
            ->withResponseModel($decisionStructure->clone())
            ->withOutputMode($this->mode)
            ->withModel($this->model)
            ->withOptions($this->options)
            ->withMaxRetries($this->maxRetries)
            ->withLLMProvider($this->llm);
        if ($this->httpClient !== null) {
            $structured = $structured->withHttpClient($this->httpClient);
        }
        return $structured->create();
    }

    /** Builds a failure step when decision fails validation. */
    private function buildValidationFailureStep(ValidationResult $validation, Messages $context): ToolUseStep {
        $formatter = new ReActFormatter();
        $error = new \RuntimeException($validation->getErrorMessage());
        $messagesErr = $formatter->decisionExtractionErrorMessages($error);
        $exec = new ToolExecution(
            new ToolCall('decision_validation', []),
            Result::failure($error),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new ToolUseStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            usage: null,
            toolCalls: null,
            toolExecutions: $executions,
            inferenceResponse: null,
            stepType: StepType::Error,
        );
    }

    /** Builds a failure step when decision extraction fails. */
    private function buildExtractionFailureStep(\Throwable $e, Messages $context): ToolUseStep {
        $formatter = new ReActFormatter();
        $messagesErr = $formatter->decisionExtractionErrorMessages($e);
        $exec = new ToolExecution(
            new ToolCall('decision_extraction', []),
            Result::failure($e),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new ToolUseStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            usage: null,
            toolCalls: null,
            toolExecutions: $executions,
            inferenceResponse: null,
            stepType: StepType::Error,
        );
    }

    /** Builds a step for a call_tool decision (executes the tool and formats follow-ups). */
    private function buildToolCallStep(
        ReActDecision $decision,
        ?Usage $usage,
        ?InferenceResponse $inferenceResponse,
        ToolUseState $state,
        ToolExecutor $executor,
        Messages $context,
    ): ToolUseStep {
        $call = new ToolCall($decision->tool() ?? '', $decision->args());
        $execution = $executor->useTool($call, $state);
        $executions = (new ToolExecutions())->withAddedExecution($execution);

        $formatter = new ReActFormatter();
        $followUps = Messages::empty()
            ->appendMessage($formatter->assistantThoughtActionMessage($decision))
            ->appendMessage($formatter->observationMessage($execution));

        return new ToolUseStep(
            inputMessages: $context,
            outputMessages: $followUps,
            usage: $usage,
            toolCalls: new ToolCalls($call),
            toolExecutions: $executions,
            inferenceResponse: $inferenceResponse,
            stepType: StepType::ToolExecution,
        );
    }

    /** Builds a step for a final_answer decision (optionally finalizes via Inference). */
    private function buildFinalAnswerStep(
        ReActDecision $decision,
        ?Usage $usage,
        ?InferenceResponse $inferenceResponse,
        ToolUseState $state,
        Messages $messages,
    ): ToolUseStep {
        $finalText = $decision->answer();
        if ($this->finalViaInference) {
            $pending = $this->finalizeAnswerViaInference($messages);
            $inferenceResponse = $pending->response();
            $finalText = $inferenceResponse->content();
            $usage = $inferenceResponse->usage();
        }
        return new ToolUseStep(
            inputMessages: $messages,
            outputMessages: Messages::empty()->appendMessage(Message::asAssistant($finalText)),
            usage: $usage,
            toolCalls: null,
            toolExecutions: null,
            inferenceResponse: $inferenceResponse,
            stepType: StepType::FinalResponse,
        );
    }

    /**
     * Builds decision and tool argument structures using dynamic Structure API.
     *
     * @return array{0: Structure, 1: array<string, Structure>}
     */
    private function buildDecisionStructures(Tools $tools): array {
        $toolSchemas = $tools->toToolSchema();
        $toolArgumentStructures = $this->buildToolArgumentStructures($toolSchemas);
        $toolNames = array_keys($toolArgumentStructures);

        $thoughtField = Field::string('thought', 'Brief reasoning for the next action.')->required();
        $typeField = Field::option('type', ['call_tool', 'final_answer'], 'Decision type.')->required();
        $toolField = match (empty($toolNames)) {
            true => Field::string('tool', 'Tool name to call when type=call_tool.')->optional(),
            false => Field::option('tool', $toolNames, 'Tool name to call when type=call_tool.')->optional(),
        };

        $argsField = Field::structure(
            'args',
            fn() => $this->buildArgsFields($toolArgumentStructures),
            'Arguments for the selected tool.',
        )->optional();
        $answerField = Field::string('answer', 'Final answer when type=final_answer.')->optional();

        $decision = Structure::define(
            'react_decision',
            [$thoughtField, $typeField, $toolField, $argsField, $answerField],
            'ReAct decision payload.',
        );
        $decision->validator(fn(Structure $structure) => $this->validateDecisionStructure($structure, $toolNames, $toolArgumentStructures));

        return [$decision, $toolArgumentStructures];
    }

    /**
     * Builds per-tool argument structures derived from tool JSON schemas.
     *
     * @param array<int, array<string, mixed>> $toolSchemas
     * @return array<string, Structure>
     */
    private function buildToolArgumentStructures(array $toolSchemas): array {
        $structures = [];
        foreach ($toolSchemas as $definition) {
            $name = $definition['function']['name'] ?? '';
            $parameters = $definition['function']['parameters'] ?? null;
            if ($name === '' || !is_array($parameters)) {
                continue;
            }
            $structures[$name] = StructureFactory::fromJsonSchema([
                ...$parameters,
                'x-title' => $parameters['x-title'] ?? $name . '_arguments',
                'description' => $parameters['description'] ?? ('Arguments for ' . $name),
            ]);
        }
        return $structures;
    }

    /**
     * Flattens tool argument structures into a shared args structure definition.
     *
     * @param array<string, Structure> $toolArgumentStructures
     * @return array<int, Field>
     */
    private function buildArgsFields(array $toolArgumentStructures): array {
        $fields = [];
        foreach ($toolArgumentStructures as $structure) {
            foreach ($structure->fields() as $field) {
                $name = $field->name();
                if (!isset($fields[$name])) {
                    $fields[$name] = $field->clone()->optional();
                }
            }
        }
        return array_values($fields);
    }

    /** Validates decision structure using Structure validator. */
    private function validateDecisionStructure(Structure $structure, array $toolNames, array $toolArgumentStructures): ValidationResult {
        $type = (string)($structure->get('type') ?? '');
        if ($type === 'call_tool') {
            return $this->validateCallDecision($structure, $toolNames, $toolArgumentStructures);
        }
        if ($type === 'final_answer') {
            return $this->validateFinalDecision($structure);
        }
        return ValidationResult::fieldError('type', $type, 'Decision type must be call_tool or final_answer.');
    }

    /** Ensures call_tool decisions reference an available tool and provide args. */
    private function validateCallDecision(Structure $structure, array $toolNames, array $toolArgumentStructures): ValidationResult {
        $tool = (string)($structure->get('tool') ?? '');
        if ($tool === '') {
            return ValidationResult::fieldError('tool', $tool, 'Tool name is required when type=call_tool.');
        }
        if ($toolNames !== [] && !in_array($tool, $toolNames, true)) {
            return ValidationResult::fieldError('tool', $tool, 'Requested tool is not available.');
        }
        $args = $structure->get('args');
        $hasArgs = match (true) {
            $args instanceof Structure => $args->toArray() !== [],
            is_array($args) => $args !== [],
            default => $args !== null,
        };
        $requiresArgs = $this->toolRequiresArgs($toolArgumentStructures[$tool] ?? null);
        if ($requiresArgs && !$hasArgs) {
            return ValidationResult::fieldError('args', $args, 'Arguments are required when type=call_tool.');
        }
        return ValidationResult::valid();
    }

    /** Ensures final_answer decisions contain an answer. */
    private function validateFinalDecision(Structure $structure): ValidationResult {
        $answer = (string)($structure->get('answer') ?? '');
        if ($answer === '') {
            return ValidationResult::fieldError('answer', $answer, 'Answer is required when type=final_answer.');
        }
        return ValidationResult::valid();
    }

    /** Converts structured decision into a ReActDecision value object. */
    private function makeDecision(Structure $structuredDecision, array $toolArgumentStructures): ReActDecision {
        $data = $structuredDecision->toArray();
        $args = $data['args'] ?? [];
        $normalizedArgs = $this->normalizeArgs($args);
        $toolName = $data['tool'] ?? '';
        $data['args'] = $this->normalizeArgsForTool($toolName, $normalizedArgs, $toolArgumentStructures);
        return ReActDecision::fromArray($data);
    }

    /** Normalizes arguments emitted by the model into an associative array. */
    private function normalizeArgs(mixed $args): array {
        if ($args instanceof Structure) {
            return $this->normalizeArgs($args->toArray());
        }
        if (is_array($args)) {
            if (array_is_list($args) && count($args) === 1 && is_array($args[0])) {
                return $this->normalizeArgs($args[0]);
            }
            return $args;
        }
        if (is_string($args) && $args !== '') {
            $decoded = json_decode($args, true);
            if (is_array($decoded)) {
                return $this->normalizeArgs($decoded);
            }
        }
        return [];
    }

    /** Applies tool-specific schema to sanitize argument payload. */
    private function normalizeArgsForTool(string $toolName, array $args, array $toolArgumentStructures): array {
        if ($toolName === '' || !isset($toolArgumentStructures[$toolName])) {
            return $args;
        }
        $structure = $toolArgumentStructures[$toolName]->clone();
        foreach ($args as $key => $value) {
            if ($structure->has($key)) {
                $structure->set($key, $value);
            }
        }
        $normalized = $structure->toArray();
        return array_filter(
            $normalized,
            static fn($value) => $value !== null,
        );
    }

    private function toolRequiresArgs(?Structure $argsStructure): bool
    {
        if ($argsStructure === null) {
            return true;
        }
        foreach ($argsStructure->fields() as $field) {
            if ($field->isRequired()) {
                return true;
            }
        }
        return false;
    }

    /** Generates a plain-text final answer via Inference and returns PendingInference. */
    private function finalizeAnswerViaInference(Messages $messages): PendingInference {
        $finalMessages = Messages::fromArray([
            ['role' => 'system', 'content' => 'Return only the final answer as plain text.'],
            ...$messages->toArray(),
        ]);
        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            ->withMessages($finalMessages->toArray())
            ->withModel($this->finalModel ?: $this->model)
            ->withOptions($this->finalOptions ?: $this->options)
            ->withOutputMode(OutputMode::Text);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference->create();
    }
}
