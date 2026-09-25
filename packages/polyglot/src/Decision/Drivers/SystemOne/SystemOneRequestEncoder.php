<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\SystemOne;

use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use InvalidArgumentException;
use JsonException;
use stdClass;

/** @internal */
final readonly class SystemOneRequestEncoder
{
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(private string $provider = 'System One') {}

    public function encode(DecisionRequest $request, string $model): string
    {
        $questions = new stdClass;
        foreach ($request->questions()->all() as $question) {
            $questions->{$question->id()} = $this->questionBody($question);
        }
        $body = new stdClass;
        $body->state = $request->input()->value();
        $body->model = $model;
        $body->questions = $questions;

        try {
            return json_encode($body, self::JSON_FLAGS);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "{$this->provider} request body cannot be encoded.",
                previous: $exception,
            );
        }
    }

    private function questionBody(Noul|Choice|Score $question): stdClass
    {
        return match (true) {
            $question instanceof Noul => $this->noulBody($question),
            $question instanceof Choice => $this->choiceBody($question),
            $question instanceof Score => $this->scoreBody($question),
        };
    }

    private function noulBody(Noul $question): stdClass
    {
        $body = $this->baseQuestionBody('noul', $question->instructions()?->value());
        $criteria = $question->criteria();
        if ($criteria === null) {
            return $body;
        }

        $wireCriteria = new stdClass;
        $trueDescription = $criteria->trueDescription();
        if ($trueDescription !== null) {
            $wireCriteria->true = $trueDescription->value();
        }
        $falseDescription = $criteria->falseDescription();
        if ($falseDescription !== null) {
            $wireCriteria->false = $falseDescription->value();
        }
        $body->criteria = $wireCriteria;

        return $body;
    }

    private function choiceBody(Choice $question): stdClass
    {
        $body = $this->baseQuestionBody('choice', $question->instructions()?->value());
        $criteria = new stdClass;
        foreach ($question->options()->all() as $option) {
            $criteria->{$option->id()} = $option->description()?->value();
        }
        $body->criteria = $criteria;

        return $body;
    }

    private function scoreBody(Score $question): stdClass
    {
        if ($question->levels()->count() > 10) {
            throw new InvalidArgumentException("{$this->provider} Score supports between 2 and 10 levels.");
        }
        $body = $this->baseQuestionBody('score', $question->instructions()?->value());
        $body->criteria = $question->levels()->toArray();

        return $body;
    }

    private function baseQuestionBody(string $type, mixed $instructions): stdClass
    {
        $body = new stdClass;
        $body->type = $type;
        if ($instructions !== null) {
            $body->instructions = $instructions;
        }

        return $body;
    }
}
