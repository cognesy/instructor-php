<?php

namespace Cognesy\Instructor\Experimental\Module\Modules\Code;

//use Cognesy\Instructor\Experimental\Module\Core\Module;
use Cognesy\Instructor\Experimental\Module\Modules\Prediction;
use Cognesy\Instructor\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Experimental\Module\Signature\Attributes\ModuleSignature;

//use Cognesy\Instructor\Experimental\Module\Core\Predictor;

#[ModuleSignature('code -> language')]
#[ModuleDescription("Identify the programming language of the code. Return only the language name.")]
class GuessProgrammingLanguage extends Prediction
{
//    private Predictor $guessLanguage;
//
//    public function __construct() {
//        $this->guessLanguage = new Predictor(
//            signature: 'code -> language',
//            description: "Identify the programming language of the code. Return only the language name."
//        );
//    }
//
//    public function for(string $code): string {
//        return ($this)(code: $code)->get('language');
//    }
//
//    protected function forward(...$callArgs): array {
//        $code = $callArgs['code'];
//        $language = $this->guessLanguage->predict(code: $code);
//        return [
//            'language' => $language
//        ];
//    }
}