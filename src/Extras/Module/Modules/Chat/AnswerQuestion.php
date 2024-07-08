<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Chat;

use Cognesy\Instructor\Extras\Module\Core\Prediction;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('question, context -> answer')]
#[ModuleDescription("Answer a question based on the provided context")]
class AnswerQuestion extends Prediction
{
//    private Predictor $answerFromContext;
//
//    public function __construct() {
//        $this->answerFromContext = Predictor::fromSignature(
//            signature: 'question, context -> answer',
//            description: "Answer a question based on the provided context",);
//    }
//
//    public function for(string $question, string $context) : string {
//        return ($this)(question: $question, context: $context)->get('answer');
//    }
//
//    protected function forward(mixed ...$callArgs) : array {
//        $question = $callArgs['question'];
//        $context = $callArgs['context'];
//        $result = $this->answerFromContext->predict(question: $question, context: $context);
//        return [
//            'answer' => $result
//        ];
//    }
}
