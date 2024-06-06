<?php

namespace Cognesy\Instructor\Extras\Module\Addons\Predict;

use BackedEnum;
use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Module\Core\DynamicModule;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Call\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\Call\Enums\CallStatus;
use Cognesy\Instructor\Extras\Module\Utils\InputOutputMapper;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Template;
use Exception;

class Predict extends DynamicModule
{
    protected Instructor $instructor;

    protected string $prompt;
    protected $options = [];
    protected $model = 'gpt-4o';
    protected $mode = Mode::Tools;
    protected array $examples = [];
    protected int $maxRetries = 3;

    protected string $defaultPrompt = 'Your job is to infer output argument values in input data based on specification: {signature} {description}';

    protected string|Signature|HasSignature $defaultSignature;

    protected ?object $signatureCarrier;

    public function __construct(
        string|Signature|HasSignature $signature,
        Instructor $instructor,
        string $model = 'gpt-4o',
        int $maxRetries = 3,
        array $options = [],
        array $examples = [],
        string $prompt = '',
        Mode $mode = Mode::Tools,
    ) {
        if ($signature instanceof HasSignature) {
            $this->signatureCarrier = $signature;
        }
        $this->defaultSignature = match(true) {
            $signature instanceof HasSignature => $signature->signature(),
            default => $signature,
        };
        $this->instructor = $instructor;
        $this->model = $model;
        $this->maxRetries = $maxRetries;
        $this->options = $options;
        $this->examples = $examples;
        $this->prompt = $prompt;
        $this->mode = $mode;
    }

    public function signature(): string|Signature {
        return $this->defaultSignature;
    }

    public function process(CanBeProcessed $call) : mixed {
        try {
            $call->changeStatus(CallStatus::InProgress);
            $values = $call->data()->input()->getValues();
            $targetObject = $this->signatureCarrier ?? $call->outputRef();
            $result = $this->forward($values, $targetObject);
            $outputs = InputOutputMapper::toOutputs($result, $this->outputNames());
            $call->setOutputs($outputs);
            $call->changeStatus(CallStatus::Completed);
        } catch (Exception $e) {
            $call->addError($e->getMessage(), ['exception' => $e]);
            $call->changeStatus(CallStatus::Failed);
            throw $e;
        }
        return $result;
    }

    public function forward(array $args, object $targetObject): mixed {
        $input = match(true) {
            count($args) === 0 => throw new \Exception('Empty input'),
            count($args) === 1 => reset($args),
            default => match(true) {
                is_array($args) => $args,
                is_array($args[0]) => $args[0],
                is_string($args[0]) => $args[0],
                default => throw new Exception('Invalid input - should be string or messages array'),
            }
        };

        $response = $this->instructor->respond(
            messages: $this->toMessages($input),
            responseModel: $targetObject,
            model: $this->model,
            maxRetries: $this->maxRetries,
            options: $this->options,
            examples: $this->examples,
            prompt: $this->prompt(),
            mode: $this->mode,
        );

        return $response;
    }

    public function prompt() : string {
        if (empty($this->prompt)) {
            $this->prompt = $this->renderPrompt($this->defaultPrompt);
        }
        return $this->prompt;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////

    private function toMessages(string|array|object $input) : array {
        $content = match(true) {
            is_string($input) => $input,
            $input instanceof Example => $input->input(),
            $input instanceof BackedEnum => $input->value,
            // ...how do we handle chat messages input?
            default => json_encode($input), // wrap in json
        };
        return [
            ['role' => 'user', 'content' => $this->prompt()],
            ['role' => 'assistant', 'content' => 'Provide input data.'],
            ['role' => 'user', 'content' => $content]
        ];
    }

    public function renderPrompt(string $template): string {
        return Template::render($template, [
            'signature' => $this->getSignature()->toSignatureString(),
            'description' => $this->getSignature()->toOutputSchema()->description(),
        ]);
    }
}
