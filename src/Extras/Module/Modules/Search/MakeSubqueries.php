<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Search;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Core\Predictor;

class MakeSubqueries extends Module
{
    private Predictor $makeSubqueries;

    public function __construct() {
        $this->makeSubqueries = Predictor::fromSignature(
            signature: 'question, context -> list_of_subqueries:string[]',
            description: "Generate relevant subqueries to extract context needed to answer provided question",
        );
    }

    public function for(string $question, string $context) : array {
        return ($this)(question: $question, context: $context)
            ->get('subqueries')
            ->toArray();
    }

    protected function forward(mixed ...$callArgs) : array {
        $query = $callArgs['question'];
        $context = $callArgs['context'];
        $result = $this->makeSubqueries->predict(question: $query, context: $context);
        return [
            'subqueries' => $result
        ];
    }
}
