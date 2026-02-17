<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Core;

use Cognesy\Experimental\ModPredict\Config\PredictorConfig;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Experimental\Signature\SignatureFactory;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Template\Template;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

class Predictor
{
    protected PredictorConfig $config;

    protected CanCreateStructuredOutput $structuredOutput;
    protected StructuredOutputRequest $requestInfo;
    protected CanCreateInference $inference;
    protected string $preset;

    protected ?Signature $signature;
    protected string $instructions;

    public function __construct(
        ?Signature $signature = null,
        ?string $description = null,
        ?string $instructions = null,
        ?StructuredOutputRequest $request = null,
        CanCreateStructuredOutput $structuredOutput,
        CanCreateInference $inference,
        ?PredictorConfig $config = null,
    ) {
        $this->inference = $inference;
        $this->structuredOutput = $structuredOutput;
        $this->requestInfo = match(true) {
            !is_null($request) => $request,
            !isset($this->requestInfo) => new StructuredOutputRequest(),
            default => $this->requestInfo,
        };
        $this->signature = match(true) {
            !empty($signature) => $this->makeSignature($signature, $description),
            default => throw new InvalidArgumentException('A valid signature is required'),
        };
        $this->instructions = match(true) {
            !is_null($instructions) => $instructions,
            !isset($this->instructions) => $this->signature->getDescription(),
            default => $this->instructions,
        };
        $this->config = $config ?? new PredictorConfig();
    }

    public static function fromSignature(
        string|Signature $signature,
        CanCreateStructuredOutput $structuredOutput,
        CanCreateInference $inference,
        string $description = '',
    ) : static {
        $resolvedSignature = match (true) {
            is_string($signature) => SignatureFactory::fromString($signature, $description),
            $signature instanceof Signature => $signature,
            default => throw new InvalidArgumentException('Invalid signature provided'),
        };

        return new static(
            signature: $resolvedSignature,
            structuredOutput: $structuredOutput,
            inference: $inference,
        );
    }

    public function predict(mixed ...$callArgs) : mixed {
        if (!empty($this->signatureDiff($callArgs))) {
            throw new InvalidArgumentException('Missing required arguments: ' . implode(', ', $this->signatureDiff($callArgs)));
        }
        return match(true) {
            $this->signature->hasTextOutput() => $this->predictText($callArgs),
            default => $this->predictStructure($callArgs),
        };
    }

    public function __invoke(mixed ...$callArgs) : mixed {
        return $this->predict(...$callArgs);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function makeSignature(string|Signature $signature, string $description) : Signature {
        return match(true) {
            is_string($signature) => SignatureFactory::fromString($signature, $description),
            $signature instanceof Signature => $signature,
            default => throw new InvalidArgumentException('Invalid signature provided'),
        };
    }

    // MUTATORS ////////////////////////////////////////////////////////////////

    public function with(
        ?Signature $signature = null,
        ?CanCreateStructuredOutput $structuredOutput = null,
        ?StructuredOutputRequest $request = null,
        ?CanCreateInference $inference = null,
        ?string $instructions = null,
    ) : static {
        return new static(
            signature: $signature ?? $this->signature,
            instructions: $instructions ?? $this->instructions,
            request: $request ?? $this->requestInfo,
            structuredOutput: $structuredOutput ?? $this->structuredOutput,
            inference: $inference ?? $this->inference,
            config: $this->config,
        );
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////

    public function instructions() : string {
        return match(true) {
            empty($this->instructions) => Arrays::flattenToString([
                $this->signature->getDescription(),
                $this->signature->toSignatureString(),
            ], PHP_EOL),
            default => $this->instructions,
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    protected function predictText(array $callArgs) : string {
        $prompt = match(true) {
            empty($this->requestInfo->prompt()) => Template::arrowpipe()
                ->from($this->config->textOutputTemplate)
                ->with(array_merge([
                    'instructions' => $this->instructions(),
                    'input' => $callArgs,
                ]))
                ->toText(),
            default => $this->requestInfo->prompt(),
        };
        $request = new InferenceRequest(
            messages: $prompt,
            model: $this->requestInfo->model(),
        );
        return $this->inference->create($request)->get();
    }

    protected function predictStructure(array $callArgs) : mixed {
        $request = $this->requestInfo
            ->withMessages(Json::encode($callArgs)) // TODO: make format configurable - JSON, YAML, XML
            ->withPrompt($this->instructions())
            ->withRequestedSchema(Signature::toStructure('prediction', $this->signature));
        return $this->structuredOutput->create($request)->get();
    }

    protected function signatureDiff(array $callArgs) : array {
        $expected = $this->signature->inputNames();
        $actual = array_keys($callArgs);
        return array_diff($expected, $actual);
    }
}
