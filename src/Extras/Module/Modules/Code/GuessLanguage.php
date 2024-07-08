<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Code;

//use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Core\Prediction;
//use Cognesy\Instructor\Extras\Module\Core\Predictor;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('code -> language')]
#[ModuleDescription("Identify the programming language of the code. Return only the language name.")]
class GuessLanguage extends Prediction
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