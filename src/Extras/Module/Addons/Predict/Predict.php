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
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Template;
use Exception;

class Predict extends DynamicModule
{
    protected Instructor $instructor;

    protected string $predictionPrompt;
    protected string $extractionPrompt;
    protected $options = [];
    protected $model = 'gpt-4o';
    protected $mode = Mode::Tools;
    protected array $examples = [];
    protected int $maxRetries = 3;

    protected string $defaultPredictionPrompt = 'Your job is to infer output argument values in input data based on specification: {signature} {description}';

    protected string|Signature|HasSignature $defaultSignature;

    protected ?object $signatureCarrier;

    public function __construct(
        string|Signature|HasSignature $signature,
        Instructor $instructor,
        string $model = '',
        int $maxRetries = 3,
        array $options = [],
        array $examples = [],
        string $predictionPrompt = '',
        string $extractionPrompt = '',
        Mode $mode = Mode::Json,
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
        $this->predictionPrompt = $predictionPrompt;
        $this->extractionPrompt = $extractionPrompt;
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
            prompt: $this->extractionPrompt,
            mode: $this->mode,
        );

        return $response;
    }

    protected function predictionPrompt() : string {
        if (empty($this->prompt)) {
            $this->predictionPrompt = $this->renderPrompt($this->defaultPredictionPrompt);
        }
        return $this->predictionPrompt;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////

    private function toMessages(string|array|object $input) : array {
        $content = match(true) {
            is_string($input) => $input,
            is_array($input) => Json::encode($input),
            $input instanceof Example => $input->input(),
            $input instanceof BackedEnum => $input->value,
            method_exists($input, 'toJson') => match(true) {
                is_string($input->toJson()) => $input->toJson(),
                default => Json::encode($input->toJson()),
            },
            method_exists($input, 'toArray') => Json::encode($input->toArray()),
            method_exists($input, 'toString') => $input->toString(),
            // ...how do we handle chat messages input?
            default => Json::encode($input), // wrap in json
        };
        return [
            ['role' => 'user', 'content' => $this->predictionPrompt()],
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
