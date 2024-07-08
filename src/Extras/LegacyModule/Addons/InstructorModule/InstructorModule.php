<?php

namespace Cognesy\Instructor\Extras\Module\Addons\InstructorModule;

use Cognesy\Experimental\Module\Signature\Attributes\ModuleSignature as SignatureAttribute;
use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Extras\Module\Core\ModuleWithSignature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Schema\Attributes\Instructions;
use Cognesy\Instructor\Schema\Utils\AttributeUtils;
use Cognesy\Instructor\Utils\TemplateUtil;
use ReflectionClass;

abstract class InstructorModule extends ModuleWithSignature
{
    private Instructor $instructor;
    private ?string $prompt = null;

    private ?array $examples = [];
    private string|array|object $responseModel;
    private array $validators = [];

    private string $signatureString;
    private string $signatureInstructions;

    public function __construct(
        string        $signature = '',
        string        $prompt = '',
        array|Example $examples = [],
        array         $validators = [],
        Instructor    $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
        $this->instructor->addValidators($validators);
        $this->signatureString = $this->signatureFromAttribute() ?: $signature;
        $this->signatureInstructions = $this->instructionsFromAttribute() ?: $prompt;
        $this->withExamples($examples);
        $this->responseModel = $this->outputModelFromSignature();
    }

    public function signature() : string|Signature {
        return $this->signatureString;
    }

    public function withSignature(string $signature, string $instructions = ''): static {
        $this->signatureString = $signature;
        $this->signatureInstructions = $instructions;
        return $this;
    }

    public function prompt(): string {
        return $this->prompt ?? $this->promptFromSignature();
    }

    public function withPrompt(string $prompt): static {
        $this->prompt = $prompt;
        return $this;
    }

    public function withExamples(array|Example $examples): static {
        $this->examples = is_array($examples) ? $examples : [$examples];
        return $this;
    }

    public function withInstructor(Instructor $instructor): static {
        $this->instructor = $instructor;
        return $this;
    }

    public function responseModel(): string|array|object {
        return $this->responseModel;
    }

    // OVERRIDABLE BY USER /////////////////////////////////////////////

    protected function toResult(mixed $response): mixed {
        return $response;
    }

    protected function examples(): ?array {
        $builtInExample = $this->example();
        if (!$builtInExample && !$this->examples) {
            return [];
        }
        return $this->examples ?: [$builtInExample];
    }

    protected function example(): ?Example {
        $input = $this->exampleInput();
        $output = $this->exampleOutput();
        if (!$input || !$output) {
            return null;
        }
        return new Example(
            input: $input,
            output: $output,
        );
    }

    protected function exampleInput(): mixed {
        return null;
    }

    protected function exampleOutput(): mixed {
        return null;
    }

    // INTERNAL /////////////////////////////////////////////////////////////

    protected function useInstructor(mixed ...$inputs) : mixed {
        $signature = $this->getSignature();
        $params = array_combine($signature->inputNames(), $inputs);
        $result = $this->instructor->respond(
            input: $params,
            responseModel: $this->responseModel(),
            prompt: TemplateUtil::render($this->prompt(), $params),
            examples: $this->examples(),
        );
        if ($result instanceof Structure && $result->actsAsScalar()) {
            return $result->asScalar();
        }
        return $result;
    }

    protected function promptFromSignature(): string {
        return trim(implode("\n", array_filter([
            $this->signatureString,
            $this->signatureInstructions,
        ])));
    }

    protected function outputModelFromSignature() : string|array|object {
        $schema = $this->getSignature()->toOutputSchema();
        return StructureFactory::fromSchema(
            name: $schema->name(),
            schema: $schema,
        );
    }

    protected function signatureFromAttribute(): string {
        // get Signature attribute from the class reflection
        $reflection = new ReflectionClass($this);
        if (!AttributeUtils::hasAttribute($reflection, SignatureAttribute::class)) {
            return '';
        }

        // get signature
        $signature = AttributeUtils::getValues($reflection, SignatureAttribute::class, 'signature');
        if (empty($signature)) {
            throw new \Exception('Signature attribute must have a signature property specified');
        }
        if (count($signature) > 1) {
            throw new \Exception('Dynamic module must have only one Signature attribute');
        }
        return $signature[0];
    }

    protected function instructionsFromAttribute(): string {
        // get Signature attribute from the class reflection
        $reflection = new ReflectionClass($this);
        if (!AttributeUtils::hasAttribute($reflection, Instructions::class)) {
            return '';
        }

        // get instructions
        $instructions = AttributeUtils::getValues($reflection, SignatureAttribute::class, 'instructions');
        if (empty($instructions)) {
            return '';
        }
        return array_reduce($instructions, fn($carry, $item) => $carry . $item, '');
    }
}
