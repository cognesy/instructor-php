<?php

namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Schema\Attributes\Description;
use Cognesy\Instructor\Schema\Utils\AttributeUtils;
use Cognesy\Instructor\Utils\Arrays;
use Exception;
use InvalidArgumentException;
use ReflectionClass;

class Prediction extends Module
{
    private Predictor $predictor;
    private Signature $signature;
    private string $outputName;

    public function __construct() {
        $this->setup();
    }

    public function signature() : Signature {
        return $this->signature;
    }

    public function for(mixed ...$callArgs) : mixed {
        if (!$this->signature->hasSingleOutput()) {
            return ($this)(...$callArgs);
        }
        return ($this)(...$callArgs)->get($this->outputName);
    }

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
            throw new Exception("PredictionModule must have a #[Signature] attribute");
        }
        $signatures = AttributeUtils::getValues($reflection, ModuleSignature::class, 'signature');
        $descriptions = AttributeUtils::getValues($reflection, Description::class, 'text');
        //$instructions = AttributeUtils::getValues($reflection, Instructions::class, 'text');
        $this->signature = SignatureFactory::fromString(Arrays::flatten($signatures), Arrays::flatten($descriptions));

        // OUTPUT NAME
        if (!$this->signature->hasSingleOutput()) {
            throw new InvalidArgumentException("PredictionModule must have a single output - you can implement custom Module to handle multiple outputs");
        }
        $this->outputName = $this->signature->outputNames()[0];

        // CREATE PREDICTOR
        $this->predictor = new Predictor(
            signature: $this->signature,
            //description: Arrays::flatten($instructions),
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
        // TODO: check argument types
    }
}
