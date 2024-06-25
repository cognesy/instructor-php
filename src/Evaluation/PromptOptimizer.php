<?php

namespace Cognesy\Instructor\Evaluation;

use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Evaluation\Data\EvaluationResult;
use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;
use Cognesy\Instructor\Evaluation\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Evaluation\ResponseModels\BooleanCorrectnessAnalysis;
use Cognesy\Instructor\Evaluation\ResponseModels\PromptImprovement;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

class PromptOptimizer {
    const LOSS_THRESHOLD = 0.1;

    private EvaluationRenderer $evaluationRenderer;
    private FeedbackRenderer $feedbackRenderer;

    public function __construct(
        EvaluationRenderer $evaluationRenderer,
        FeedbackRenderer $feedbackRenderer
    ) {
        $this->evaluationRenderer = $evaluationRenderer;
        $this->feedbackRenderer = $feedbackRenderer;
    }

    public function step(Instructor $transformation) : string {
        $result = $transformation->get();

        $prompt = $transformation->getRequest()->prompt();
        $examples = $transformation->getRequest()->examples();
        $outputModel = $transformation->getRequest()->responseModel()?->toJsonSchema();

        // EVALUATION PROCESS //

        $evaluation = new PromptEvaluation(
            prompt: $prompt,
            examples: $examples,
            input: (array) $result,
            outputModel: $outputModel,
        );

        /** @var EvaluationResult $evaluationResult */
        $evaluationResult = $this->evaluate($evaluation);

        if ($evaluationResult->metric()->toLoss() < self::LOSS_THRESHOLD) {
            // Prompt executed correctly, skipping optimization step
            return '';
        }

        $evaluation->withResult($evaluationResult);
        $improvedPrompt = $this->improvePrompt($evaluation);

        return $improvedPrompt->improvedPrompt;
    }

    public function evaluate(PromptEvaluation $evaluation) : EvaluationResult {
        $input = $this->evaluationRenderer->render($evaluation);

        /** @var BooleanCorrectnessAnalysis $analysis */
        $analysis = (new Instructor)->withClient(new AnthropicClient(apiKey: Env::get('ANTHROPIC_API_KEY')))
            ->onEvent(RequestSentToLLM::class, fn(RequestSentToLLM $e)=>$e->printDebug())
            ->respond(
                input: $input,
                responseModel: BooleanCorrectnessAnalysis::class,
                prompt: 'Analyze task instructions, then assess results and determine if the actual result is correct. Respond with JSON, follow the schema: <|json_schema|>',
                toolTitle: 'correctness_evaluation',
                //toolDescription: 'Respond with true or false to indicate if the actual result is correct.',
                mode: Mode::MdJson,
            );
        return new EvaluationResult(
            metric: new BooleanCorrectness($analysis->isCorrect),
            feedback: $analysis->feedback
        );
    }

    public function improvePrompt(PromptEvaluation $evaluation) : PromptImprovement {
        $input = $this->feedbackRenderer->render($evaluation);
        return (new Instructor)->withClient(new AnthropicClient(apiKey: Env::get('ANTHROPIC_API_KEY')))
            ->onEvent(RequestSentToLLM::class, fn(RequestSentToLLM $e)=>$e->printDebug())
            ->respond(
                input: $input,
                responseModel: PromptImprovement::class,
                prompt: 'Improve the prompt based on the feedback. Respond with JSON data, follow the schema: <|json_schema|>',
                //examples: $examples,
                mode: Mode::MdJson,
            );
    }
}
