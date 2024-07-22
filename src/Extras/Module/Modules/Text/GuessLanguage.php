<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Text;

//use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Prediction;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

//use Cognesy\Instructor\Extras\Module\Core\Predictor;

#[ModuleSignature('text:string -> language:string')]
#[ModuleDescription('Return full name of the language of the provided text')]
class GuessLanguage extends Prediction
{
//    private Predictor $guessLanguage;
//
//    public function __construct() {
//        $this->guessLanguage = Predictor::fromSignature(
//            signature: 'text -> language',
//            description: "Guess the language of the provided text",
//        );
//    }
//
//    public function for(string $text) : string {
//        return ($this)(text: $text)->get('language');
//    }
//
//    protected function forward(mixed ...$callArgs) : array {
//        $text = $callArgs['text'];
//        $result = $this->guessLanguage->predict(text: $text);
//        return [
//            'language' => $result
//        ];
//    }
}
