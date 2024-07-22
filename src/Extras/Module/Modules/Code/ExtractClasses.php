<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Code;

use Cognesy\Instructor\Extras\Module\Modules\Prediction;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

//use Cognesy\Instructor\Extras\Module\Core\Predictor;

#[ModuleSignature('language, code -> classes')]
#[ModuleDescription("Extract classes from the code written in given programming language.")]
class ExtractClasses extends Prediction
{
//    private Predictor $extractClasses;
//
//    public function __construct() {
//        $this->extractClasses = new Predictor(
//            signature: 'language, code -> classes',
//            description: "Extract classes from the code written in given programming language."
//        );
//    }
//
//    public function for(string $language, string $code): array {
//        return ($this)(language: $language, code: $code)->get('classes');
//    }
//
//    protected function forward(mixed ...$callArgs): array {
//        $language = $callArgs['language'];
//        $code = $callArgs['code'];
//        $classes = $this->extractClasses->predict(language: $language, code: $code);
//        return [
//            'classes' => $classes
//        ];
//    }
}