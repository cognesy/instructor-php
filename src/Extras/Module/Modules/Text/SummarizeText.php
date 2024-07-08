<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Text;

//use Cognesy\Instructor\Extras\Module\Core\Module;
//use Cognesy\Instructor\Extras\Module\Core\Predictor;
use Cognesy\Instructor\Extras\Module\Core\Prediction;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('text:string -> summary:string')]
#[ModuleDescription('Summarize the text')]
class SummarizeText extends Prediction
{
//    private Predictor $summarize;
//
//    public function __construct() {
//        $this->summarize = new Predictor(
//            signature: 'text -> summary',
//            description: "Summarize the text."
//        );
//    }
//
//    public function for(string $text) : string {
//        return ($this)(text: $text)->get('summary');
//    }
//
//    protected function forward(...$callArgs): array {
//        $text = $callArgs['text'];
//        $summary = $this->summarize->predict(text: $text);
//        return [
//            'summary' => $summary
//        ];
//    }
}