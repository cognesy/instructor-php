<?php

namespace Cognesy\Experimental\Module\Modules\Code;

use Cognesy\Experimental\Module\Modules\Prediction;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleSignature;

//use Cognesy\Experimental\Module\Core\Predictor;

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