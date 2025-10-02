<?php
namespace Cognesy\Experimental\Module\Modules;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Module\Core\Predictor;
use Cognesy\Experimental\Signature\Attributes\ModuleSignature;
use Cognesy\Experimental\Signature\Contracts\HasSignature;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Experimental\Signature\SignatureFactory;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Utils\AttributeUtils;
use Cognesy\Utils\Arrays;
use Exception;
use InvalidArgumentException;
use ReflectionClass;

class Prediction extends Module implements HasSignature
{
    protected Predictor $predictor;
    protected Signature $signature;
    protected string $outputName;

    public function __construct() {
        $this->setup();
    }

    #[\Override]
    public function signature() : Signature {
        return $this->signature;
    }

    public function for(mixed ...$callArgs) : mixed {
        return match(true) {
            $this->signature->hasSingleOutput() => ($this)(...$callArgs)->get($this->outputName),
            default => ($this)(...$callArgs),
        };
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    #[\Override]
    protected function forward(mixed ...$callArgs) : array {
        $this->validateArgs($callArgs, $this->signature->inputNames());
        $result = $this->predictor->predict(...$callArgs);
        return [
            $this->outputName => $result
        ];
    }

    private function setup() : void {
        // CREATE SIGNATURE
        $reflection = new ReflectionClass($this);
        $hasSignature = AttributeUtils::hasAttribute($reflection, ModuleSignature::class);
        if (!$hasSignature) {
            throw new Exception("Prediction module must have a #[Signature] attribute");
        }
        $signatures = AttributeUtils::getValues($reflection, ModuleSignature::class, 'signature');
        $descriptions = AttributeUtils::getValues($reflection, Description::class, 'text');
        //$instructions = AttributeUtils::getValues($reflection, Instructions::class, 'text');
        $this->signature = SignatureFactory::fromString(Arrays::flattenToString($signatures), Arrays::flattenToString($descriptions));

        // OUTPUT NAME
        if (!$this->signature->hasSingleOutput()) {
            throw new InvalidArgumentException("Prediction module must have a single output - you can implement custom Module to handle multiple outputs");
        }
        $this->outputName = $this->signature->outputNames()[0];

        // CREATE PREDICTOR
        $this->predictor = new Predictor(
            signature: $this->signature,
        );
    }

    private function validateArgs(array $args, array $expectedNames) : void {
        // check if input provided
        $argNames = array_keys($args);
        if (count($argNames) === 0) {
            throw new InvalidArgumentException("No input arguments provided");
        }
        // check if keys are strings
        $areKeysStrings = array_reduce($argNames, fn($carry, $item) => $carry && is_string($item), true);
        if (!$areKeysStrings) {
            throw new InvalidArgumentException("Input argument names must be strings");
        }
        // check for unexpected arguments
        $diff = array_diff($argNames, $expectedNames);
        if (count($diff) > 0) {
            throw new InvalidArgumentException("Unexpected input fields: " . implode(', ', $diff));
        }
        // check for missing arguments
        foreach ($expectedNames as $name) {
            if (!array_key_exists($name, $args)) {
                throw new InvalidArgumentException("Missing input field: $name");
            }
        }
        // TODO: check argument types?
    }
}
