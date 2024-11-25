Project Path: ToolUse

Source Tree:

```
ToolUse
├── Tools.php
├── Drivers
│   ├── ToolCalling
│   │   └── ToolCallingDriver.php
│   ├── ReAct
│   │   ├── ReActDriver.php
│   │   └── ReasoningStep.php
│   └── Instructor
│       └── InstructorDriver.php
├── Contracts
│   ├── ToolInterface.php
│   ├── CanUseTools.php
│   └── CanDecideToContinue.php
├── ContinuationCriteria
│   ├── ExecutionTimeLimit.php
│   ├── ErrorPresenceCheck.php
│   ├── TokenUsageLimit.php
│   ├── RetryLimit.php
│   ├── StepsLimit.php
│   └── ToolCallPresenceCheck.php
├── ToolExecution.php
├── Tools
│   ├── FunctionTool.php
│   ├── UpdateContextVariables.php
│   └── BaseTool.php
├── Exceptions
│   ├── InvalidToolException.php
│   ├── ToolUseTokenLimitException.php
│   ├── ToolUseStepLimitException.php
│   ├── ToolUseException.php
│   ├── ToolExecutionException.php
│   └── ToolUseTimeoutException.php
├── Traits
│   ├── ToolUse
│   │   ├── HandlesParameters.php
│   │   ├── HandlesAccess.php
│   │   └── HandlesContinuationCriteria.php
│   ├── Tools
│   │   ├── HandlesMutation.php
│   │   ├── HandlesFunctions.php
│   │   ├── HandlesTransformation.php
│   │   └── HandlesAccess.php
│   └── ToolUseStep
│       └── HandlesErrors.php
├── ToolUse.php
├── ToolExecutions.php
└── ToolUseStep.php

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Tools.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Extras\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

class Tools
{
    use Traits\Tools\HandlesAccess;
    use Traits\Tools\HandlesFunctions;
    use Traits\Tools\HandlesMutation;
    use Traits\Tools\HandlesTransformation;

    private bool $parallelToolCalls;
    /** @var ToolInterface[] */
    private array $tools = [];
    private $throwOnToolFailure = true;

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(
        array $tools = [],
        bool $parallelToolCalls = false
    ) {
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
        $this->parallelToolCalls = $parallelToolCalls;
    }

    public function useTool(ToolCall $toolCall) : ToolExecution {
        $startedAt = new DateTimeImmutable();
        $result = $this->execute($toolCall->name(), $toolCall->args());
        $endedAt = new DateTimeImmutable();
        $toolExecution = new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: $startedAt,
            endedAt: $endedAt,
        );
        if ($result->isFailure() && $this->throwOnToolFailure) {
            throw new ToolExecutionException($result->error());
        }
        return $toolExecution;
    }

    public function useTools(ToolCalls $toolCalls): ToolExecutions {
        $toolExecutions = new ToolExecutions();
        foreach ($toolCalls->all() as $toolCall) {
            $toolExecutions->add($this->useTool($toolCall));
        }
        return $toolExecutions;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function execute(string $name, array $args): Result {
        try {
            $result = $this->get($name)->use(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return $result;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Drivers/ToolCalling/ToolCallingDriver.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Drivers\ToolCalling;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\ToolUse\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\ToolUse\ToolExecution;
use Cognesy\Instructor\Extras\ToolUse\ToolExecutions;
use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Result\Failure;
use Cognesy\Instructor\Utils\Result\Result;
use Cognesy\Instructor\Utils\Result\Success;

class ToolCallingDriver implements CanUseTools
{
    private Inference $inference;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private Mode $mode;
    private array $options;
    private bool $parallelToolCalls = false;

    public function __construct(
        Inference    $inference = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        Mode         $mode = Mode::Tools,
    ) {
        $this->inference = $inference ?? new Inference();

        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
    }

    public function useTools(Messages $messages, Tools $tools) : ToolUseStep {
        $llmResponse = $this->inference->create(
            messages: $messages->toArray(),
            model: $this->model,
            tools: $tools->toToolSchema(),
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            options: array_merge(
                $this->options,
                ['parallel_tool_calls' => $this->parallelToolCalls]
            ),
            mode: $this->mode,
        )->response();

        $toolExecutions = $tools->useTools($llmResponse->toolCalls());
        $followUpMessages = $this->makeFollowUpMessages($toolExecutions);

        return new ToolUseStep(
            response: $llmResponse->content(),
            toolCalls: $llmResponse->toolCalls(),
            toolExecutions: $toolExecutions,
            messages: $followUpMessages,
            usage: $llmResponse->usage(),
            llmResponse: $llmResponse,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function makeFollowUpMessages(ToolExecutions $toolExecutions) : Messages {
        $messages = new Messages();
        foreach ($toolExecutions->all() as $toolExecution) {
            $messages->appendMessages($this->makeToolExecutionMessages($toolExecution));
        }
        return $messages;
    }

    protected function makeToolExecutionMessages(ToolExecution $toolExecution) : Messages {
        $messages = new Messages();
        $messages->appendMessage($this->makeToolInvocationMessage($toolExecution->toolCall()));
        $messages->appendMessage($this->makeToolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result()));
        return $messages;
    }

    protected function makeToolInvocationMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            metadata: [
                'tool_calls' => [$toolCall->toToolCallArray()]
            ]
        );
    }

    protected function makeToolExecutionResultMessage(ToolCall $toolCall, Result $result) : Message {
        return match(true) {
            $result instanceof Success => $this->makeToolExecutionSuccessMessage($toolCall, $result),
            $result instanceof Failure => $this->makeToolExecutionErrorMessage($toolCall, $result),
        };
    }

    protected function makeToolExecutionSuccessMessage(ToolCall $toolCall, Success $result) : Message {
        $value = $result->unwrap();
        return new Message(
            role: 'tool',
            content: match(true) {
                is_string($value) => $value,
                is_array($value) => Json::encode($value),
                is_object($value) => Json::encode($value),
                default => (string) $value,
            },
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $value
            ]
        );
    }

    protected function makeToolExecutionErrorMessage(ToolCall $toolCall, Failure $result) : Message {
        return new Message(
            role: 'tool',
            content: "Error in tool call: " . $result->errorMessage(),
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $result->error()
            ]
        );
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Drivers/ReAct/ReActDriver.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Drivers\ReAct;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Messages\Messages;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Yaml\Yaml;

#[Deprecated]
class ReActDriver implements CanUseTools
{
    private Instructor $instructor;

    public function __construct(
        Instructor $instructor
    ) {
        $this->instructor = $instructor;
    }

    public function useTools(Messages $messages, Tools $tools): ToolUseStep {
        $answer = $this->instructor->respond(
            messages: $messages->toArray(),
            system: $this->toSystem($tools),
            responseModel: ReasoningStep::class,
            mode: $this->mode,
        );
    }

    private function toSystem(Tools $tools): array {
        $system = [];
        foreach ($tools as $tool) {
            $system[$tool->name()] = $tool->toJsonSchema();
        }
        return $system;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Drivers/ReAct/ReasoningStep.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Drivers\ReAct;

use Cognesy\Instructor\Features\Schema\Attributes\Description;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ReasoningStep
{
    #[Description("Has the task been completed?")]
    public bool $taskCompleted;
    #[Description("What needs to be done at this point? Explain your reasoning.")]
    public ?string $thought;
    #[Description('If external tool is needed, choose tool name from the list')]
    public string $toolName;
    #[Description('Parameters for the tool, based on the tool schema definition')]
    /** @var array<string, string> */
    public array $toolParameters;
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Drivers/Instructor/InstructorDriver.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Drivers\Instructor;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Utils\Messages\Messages;

class InstructorDriver implements CanUseTools
{
    public function useTools(Messages $messages, Tools $tools): ToolUseStep {
        return new ToolUseStep(

        );
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Contracts/ToolInterface.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Utils\Result\Result;

interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function use(mixed ...$args) : Result;
    public function toToolSchema(): array;
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Contracts/CanUseTools.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Utils\Messages\Messages;

interface CanUseTools
{
    public function useTools(Messages $messages, Tools $tools): ToolUseStep;
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Contracts/CanDecideToContinue.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

interface CanDecideToContinue
{
    public function canContinue(ToolUseStep $step) : bool;
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ContinuationCriteria/ExecutionTimeLimit.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class ExecutionTimeLimit implements CanDecideToContinue
{
    private int $maxExecutionTime;
    private int $executionStartTime;

    public function __construct(int $maxExecutionTime) {
        $this->maxExecutionTime = $maxExecutionTime;
        $this->executionStartTime = time();
    }

    public function canContinue(ToolUseStep $step): bool {
        return ((time() - $this->executionStartTime) < $this->maxExecutionTime);
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ContinuationCriteria/ErrorPresenceCheck.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class ErrorPresenceCheck implements CanDecideToContinue
{
    public function canContinue(ToolUseStep $step): bool {
        return !$step->hasErrors();
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ContinuationCriteria/TokenUsageLimit.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class TokenUsageLimit implements CanDecideToContinue
{
    private int $maxTokens;
    private Usage $usage;

    public function __construct(int $maxTokens) {
        $this->maxTokens = $maxTokens;
        $this->usage = new Usage();
    }

    public function canContinue(ToolUseStep $step): bool {
        $this->usage->accumulate($step->usage());
        return ($this->usage->total() < $this->maxTokens);
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ContinuationCriteria/RetryLimit.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class RetryLimit implements CanDecideToContinue
{
    private int $maxRetries;
    private int $currentRetries = 0;

    public function __construct(int $maxRetries) {
        $this->maxRetries = $maxRetries;
    }

    public function canContinue(ToolUseStep $step): bool {
        if ($step->hasErrors()) {
            $this->currentRetries++;
            return ($this->currentRetries < $this->maxRetries);
        }
        return true;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ContinuationCriteria/StepsLimit.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class StepsLimit implements CanDecideToContinue
{
    private int $maxSteps;
    private int $currentStep = 0;

    public function __construct(int $maxSteps) {
        $this->maxSteps = $maxSteps;
    }

    public function canContinue(ToolUseStep $step): bool {
        $this->currentStep++;
        return ($this->currentStep < $this->maxSteps);
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ContinuationCriteria/ToolCallPresenceCheck.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

class ToolCallPresenceCheck implements CanDecideToContinue
{
    public function canContinue(ToolUseStep $step): bool {
        return $step->hasToolCalls();
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ToolExecution.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

class ToolExecution
{
    private ToolCall $toolCall;
    private Result $result;
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $endedAt;

    public function __construct(
        ToolCall $toolCall,
        Result $result,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
    ) {
        $this->toolCall = $toolCall;
        $this->result = $result;
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    public function toolCall() : ToolCall {
        return $this->toolCall;
    }

    public function startedAt() : DateTimeImmutable {
        return $this->startedAt;
    }

    public function endedAt() : DateTimeImmutable {
        return $this->endedAt;
    }

    public function name() : string {
        return $this->toolCall->name();
    }

    public function args() : array {
        return $this->toolCall->args();
    }

    public function result() : Result {
        return $this->result;
    }

    public function value() : mixed {
        return $this->result->unwrap();
    }

    public function error() : ?Throwable {
        return $this->result->error();
    }

    public function hasError() : bool {
        return $this->result->isFailure();
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Tools/FunctionTool.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Tools;

use Closure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Utils\Result\Result;
use Throwable;

class FunctionTool implements ToolInterface
{
    private string $name;
    private string $description;
    private array $jsonSchema;

    private Closure $callback;

    public function __construct(
        string $name,
        string $description,
        array $jsonSchema,
        Closure $callback,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->jsonSchema = $jsonSchema;
        $this->callback = $callback;
    }

    public static function fromCallable(callable $function): self {
        $structure = StructureFactory::fromCallable($function);
        return new self(
            name: $structure->name(),
            description: $structure->description(),
            jsonSchema: $structure->toJsonSchema(),
            callback: Closure::fromCallable($function)
        );
    }

    public function name(): string {
        return $this->name;
    }

    public function description(): string {
        return $this->description;
    }

    public function function(): Closure {
        return $this->callback;
    }

    public function withName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function withDescription(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function use(mixed ...$args): Result {
        try {
            $result = $this->__invoke(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return Result::success($result);
    }

    public function __invoke(mixed ...$args): mixed {
        return ($this->callback)(...$args);
    }

    public function toJsonSchema() : array {
        return $this->jsonSchema;
    }

    public function toToolSchema() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Tools/UpdateContextVariables.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Tools;

class UpdateContextVariables extends BaseTool
{
    public function __invoke(array $variableValues): mixed {
        foreach ($variableValues as $name => $value) {
            $this->context->set($name, $value);
        }
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Tools/BaseTool.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Tools;

use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Utils\Result\Result;
use Throwable;

abstract class BaseTool implements ToolInterface
{
    protected string $name;
    protected string $description;
    protected array $jsonSchema;

    public function name(): string {
        return $this->name ?? static::class;
    }

    public function description(): string {
        return $this->description ?? '';
    }

    public function use(mixed ...$args): Result {
        try {
            $value = $this->__invoke(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return Result::success($value);
    }

    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function toJsonSchema(): array {
        if (isset($this->jsonSchema)) {
            $this->jsonSchema = StructureFactory::fromMethodName(static::class, '__invoke')
                ->toSchema()
                ->toJsonSchema();
        }
        return $this->jsonSchema;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Exceptions/InvalidToolException.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Exceptions;

class InvalidToolException extends ToolUseException {}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Exceptions/ToolUseTokenLimitException.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Exceptions;

class ToolUseTokenLimitException extends ToolUseException {}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Exceptions/ToolUseStepLimitException.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Exceptions;

class ToolUseStepLimitException extends ToolUseException {}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Exceptions/ToolUseException.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Exceptions;

use RuntimeException;

class ToolUseException extends RuntimeException {}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Exceptions/ToolExecutionException.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Exceptions;

class ToolExecutionException extends ToolUseException {}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Exceptions/ToolUseTimeoutException.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Exceptions;

class ToolUseTimeoutException extends ToolUseException {}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/ToolUse/HandlesParameters.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Utils\Messages\Messages;

trait HandlesParameters
{
    public function withDriver(ToolCallingDriver $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function withTools(array|Tools $tools) : self {
        if (is_array($tools)) {
            $tools = new Tools($tools);
        }
        $this->tools = $tools;
        return $this;
    }

    public function withMessages(string|array $messages) : self {
        $messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            is_array($messages) => $messages,
            default => []
        };
        $this->messages = Messages::fromArray($messages);
        return $this;
    }

    public function withMaxSteps(int $maxSteps) : self {
        $this->maxSteps = $maxSteps;
        return $this;
    }

    public function withMaxTokens(int $maxTokens) : self {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : self {
        $this->maxRetries = $maxRetries;
        return $this;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/ToolUse/HandlesAccess.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Features\LLM\Data\Usage;

trait HandlesAccess
{
    /** @var ToolUseStep[] */
    public function steps() : array {
        return $this->steps;
    }

    public function stepCount() : int {
        return count($this->steps);
    }

    public function currentStep() : ?ToolUseStep {
        return $this->currentStep;
    }

    public function usage() : Usage {
        return $this->usage;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/ToolUse/HandlesContinuationCriteria.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\ErrorPresenceCheck;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\RetryLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\ToolCallPresenceCheck;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

trait HandlesContinuationCriteria
{
    public function withContinuationCriteria(array $continuationCriteria) : self {
        $this->continuationCriteria = $continuationCriteria;
        return $this;
    }

    public function withDefaultContinuationCriteria(
        int $maxSteps = 3,
        int $maxTokens = 8192,
        int $maxExecutionTime = 30,
        int $maxRetries = 3
    ) : self {
        $this->continuationCriteria = [
            new StepsLimit($maxSteps),
            new TokenUsageLimit($maxTokens),
            new ExecutionTimeLimit($maxExecutionTime),
            new RetryLimit($maxRetries),
            new ErrorPresenceCheck(),
            new ToolCallPresenceCheck(),
        ];
        return $this;
    }

    public function hasNextStep() : bool {
        if ($this->currentStep === null) {
            return true;
        }
        return $this->canContinue($this->currentStep);
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseStep $step) : bool {
        // replace with continuation criteria
        foreach ($this->continuationCriteria as $criterion) {
            if (!$criterion->canContinue($step)) {
                return false;
            }
        }
        return true;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/Tools/HandlesMutation.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\Tools;

use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;

trait HandlesMutation
{
    public function withParallelCalls(bool $parallelToolCalls = true): self {
        $this->parallelToolCalls = $parallelToolCalls;
        return $this;
    }

    public function withTool(ToolInterface $tool): self {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function addTool(ToolInterface $tool): self {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function removeTool(string $name): self {
        unset($this->tools[$name]);
        return $this;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/Tools/HandlesFunctions.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\Tools;

use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Extras\ToolUse\Tools\FunctionTool;

trait HandlesFunctions
{
    /**
     * @param callable[] $functions
     */
    public static function fromFunctions(array $functions): Tools {
        $tools = new self();
        foreach ($functions as $function) {
            $tools->addFunction($function);
        }
        return $tools;
    }

    public function addFunction(callable $function, string $name = '', string $description = ''): self {
        $tool = FunctionTool::fromCallable($function);
        $name = $name ?: $tool->name();
        $description = $description ?: $tool->description();
        $tool->withName($name)->withDescription($description);
        $this->tools[$name] = $tool;
        return $this;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/Tools/HandlesTransformation.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\Tools;

trait HandlesTransformation
{
    public function toToolSchema() : array {
        $schema = [];
        foreach ($this->tools as $tool) {
            $schema[] = $tool->toToolSchema();
        }
        return $schema;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/Tools/HandlesAccess.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\Tools;

use Cognesy\Instructor\Extras\ToolUse\Exceptions\InvalidToolException;
use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;

trait HandlesAccess
{
    public function has(string $name): bool {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ToolInterface {
        if (!$this->has($name)) {
            throw new InvalidToolException("Tool '$name' not found.");
        }
        return $this->tools[$name];
    }

    /**
     * @return string[]
     */
    public function missing(?ToolCalls $toolCalls): array {
        $missing = [];
        foreach ($toolCalls?->all() as $toolCall) {
            if (!$this->has($toolCall->name())) {
                $missing[] = $toolCall->name();
            }
        }
        return $missing;
    }

    public function canExecute(ToolCalls $toolCalls) : bool {
        foreach ($toolCalls->all() as $toolCall) {
            if (!$this->has($toolCall->name())) {
                return false;
            }
        }
        return true;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/Traits/ToolUseStep/HandlesErrors.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUseStep;

use Cognesy\Instructor\Extras\ToolUse\ToolExecutions;
use Throwable;

trait HandlesErrors
{
    public function hasErrors() : bool {
        return match($this->toolExecutions) {
            null => false,
            default => $this->toolExecutions->hasErrors(),
        };
    }

    /**
     * @return Throwable[]
     */
    public function errors() : array {
        return $this->toolExecutions?->errors() ?? [];
    }

    public function errorsAsString() : string {
        return implode("\n", array_map(
            callback: fn(Throwable $e) => $e->getMessage(),
            array: $this->errors(),
        ));
    }

    public function errorExecutions() : ToolExecutions {
        return match($this->toolExecutions) {
            null => new ToolExecutions(),
            default => new ToolExecutions($this->toolExecutions->withErrors()),
        };
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ToolUse.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanUseTools;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Messages\Messages;
use Generator;

class ToolUse {
    use Traits\ToolUse\HandlesAccess;
    use Traits\ToolUse\HandlesContinuationCriteria;
    use Traits\ToolUse\HandlesParameters;

    private CanUseTools $driver;
    private Tools $tools;
    private Messages $messages;
    private array $continuationCriteria = [];

    /** @var ToolUseStep[] */
    private array $steps = [];
    private ?ToolUseStep $currentStep = null;

    private Usage $usage;

    public function __construct(
        Tools $tools = null,
    ) {
        $this->tools = $tools ?? new Tools();

        $this->messages = new Messages();
        $this->usage = new Usage();
    }

    public function nextStep() : ToolUseStep {
        $step = $this->driver->useTools(
            $this->messages,
            $this->tools,
        );

        // process step results
        $this->usage->accumulate($step->usage());
        $this->currentStep = $step;
        $this->steps[] = $step;

        // decide on follow up action
        $this->messages->appendMessages($step->messages());

        return $step;
    }

    public function finalStep() : ToolUseStep {
        while ($this->hasNextStep()) {
            $this->nextStep();
        }
        return $this->currentStep;
    }

    /** @return Generator<ToolUseStep> */
    public function iterator() : iterable {
        while ($this->hasNextStep()) {
            yield $this->nextStep();
        }
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ToolExecutions.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Throwable;

class ToolExecutions
{
    /** @var ToolExecution[] */
    private array $toolExecutions;

    /**
     * @param ToolExecution[] $toolExecutions
     */
    public function __construct(array $toolExecutions = []) {
        $this->toolExecutions = $toolExecutions;
    }

    public function add(ToolExecution $toolExecution): self {
        $this->toolExecutions[] = $toolExecution;
        return $this;
    }

    public function hasExecutions() : bool {
        return count($this->toolExecutions) > 0;
    }

    /**
     * @return ToolExecution[]
     */
    public function all(): array {
        return $this->toolExecutions;
    }

    public function hasErrors(): bool {
        return count($this->withErrors()) > 0;
    }

    /**
     * @return ToolExecution[]
     */
    public function withErrors(): array {
        return array_filter($this->toolExecutions, fn(ToolExecution $toolExecution) => $toolExecution->hasError());
    }

    /**
     * @return Throwable[]
     */
    public function errors() : array {
        $errors = [];
        foreach($this->toolExecutions as $toolExecution) {
            if ($toolExecution->hasError()) {
                $errors[] = $toolExecution->error();
            }
        }
        return $errors;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Extras/ToolUse/ToolUseStep.php`:

```php
<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Messages\Messages;

class ToolUseStep
{
    use Traits\ToolUseStep\HandlesErrors;

    private mixed $response;
    private ?ToolCalls $toolCalls;
    private ?ToolExecutions $toolExecutions;
    private ?Messages $messages;
    private ?Usage $usage;
    private ?LLMResponse $llmResponse;

    public function __construct(
        mixed          $response = null,
        ToolCalls      $toolCalls = null,
        ToolExecutions $toolExecutions = null,
        Messages       $messages = null,
        Usage          $usage = null,
        LLMResponse    $llmResponse = null,
    ) {
        $this->response = $response;
        $this->toolCalls = $toolCalls;
        $this->toolExecutions = $toolExecutions;
        $this->messages = $messages;
        $this->usage = $usage;
        $this->llmResponse = $llmResponse;
    }

    public function response() : mixed {
        return $this->response ?? null;
    }

    public function messages() : Messages {
        return $this->messages ?? new Messages();
    }

    public function toolCalls() : ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function hasToolCalls() : bool {
        return $this->toolCalls()->count() > 0;
    }

    public function toolExecutions() : ToolExecutions {
        return $this->toolExecutions ?? new ToolExecutions();
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }

    public function llmResponse() : ?LLMResponse {
        return $this->llmResponse;
    }
}

```