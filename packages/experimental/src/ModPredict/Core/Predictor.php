<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Core;

use Cognesy\Experimental\ModPredict\Config\PredictorConfig;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Experimental\Signature\SignatureFactory;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Template\Template;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

class Predictor
{
    protected PredictorConfig $config;

    protected StructuredOutput $structuredOutput;
    protected StructuredOutputRequest $requestInfo;
    protected Inference $inference;
    protected string $preset;

    protected ?Signature $signature;
    protected string $instructions;

    public function __construct(
        ?Signature $signature = null,
        ?string $description = null,
        ?string $instructions = null,
        ?StructuredOutputRequest $request = null,
        ?StructuredOutput $structuredOutput = null,
        ?Inference $inference = null,
        ?PredictorConfig $config = null,
    ) {
        $this->structuredOutput = match(true) {
            !is_null($structuredOutput) => $structuredOutput,
            !isset($this->structuredOutput) => new StructuredOutput(),
            default => $this->structuredOutput,
        };
        $this->inference = match(true) {
            !is_null($inference) => $inference,
            !isset($this->inference) => new Inference(),
            default => $this->inference,
        };
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
        string $description = ''
    ) : static {
        $instance = new static;
        $instance->with(signature: $instance->makeSignature($signature, $description));
        return $instance;
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
        ?StructuredOutput $structuredOutput = null,
        ?StructuredOutputRequest $request = null,
        ?Inference $inference = null,
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
        $this->requestInfo->withPrompt(match(true) {
            empty($this->requestInfo->prompt()) => Template::arrowpipe()
                ->from($this->config->textOutputTemplate)
                ->with(array_merge([
                    'instructions' => $this->instructions(),
                    'input' => $callArgs,
                ]))
                ->toText(),
            default => $this->requestInfo->prompt(),
        });
        return $this->inference->with(messages: $this->requestInfo->prompt())->get();
    }

    protected function predictStructure(array $callArgs) : mixed {
        $this->requestInfo->withMessages(Json::encode($callArgs)); // TODO: make format configurable - JSON, YAML, XML
        $this->requestInfo->withPrompt($this->instructions());
        $this->requestInfo->withRequestedSchema(Signature::toStructure('prediction', $this->signature));
        return $this->structuredOutput->withRequest($this->requestInfo)->get();
    }

    protected function signatureDiff(array $callArgs) : array {
        $expected = $this->signature->inputNames();
        $actual = array_keys($callArgs);
        return array_diff($expected, $actual);
    }
}
