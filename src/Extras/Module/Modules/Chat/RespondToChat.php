<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Chat;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Search\FindSources;
use Cognesy\Instructor\Extras\Module\Modules\Search\MakeSubqueries;
use Cognesy\Instructor\Extras\Module\Modules\Text\GuessLanguage;
use Cognesy\Instructor\Extras\Module\Modules\Text\Translate;

/**
 * Can answer questions based on a fragment of a chat
 * in the same language as the chat, based on the provided
 * data sources.
 */
class RespondToChat extends Module
{
    private AnswerQuestion $answerQuestion;
    private Translate $translate;
    private MakeSubqueries $makeSubqueries;
    private FindSources $findSources;
    private InferQueryFromChat $inferQueryFromChat;
    private GuessLanguage $guessLanguage;

    public function __construct() {
        $this->guessLanguage = new GuessLanguage();
        $this->inferQueryFromChat = new InferQueryFromChat();
        $this->makeSubqueries = new MakeSubqueries();
        $this->findSources = new FindSources();
        $this->answerQuestion = new AnswerQuestion();
        $this->translate = new Translate();
    }

    public function for(string|array $chat) : string {
        if (is_array($chat)) {
            $chat = array_map(fn($message) => $message['content'], $chat);
            $chat = implode("\n", $chat);
        }
        return ($this)(chat: $chat)->get('answer');
    }

    protected function forward(mixed ...$callArgs) : array {
        $chat = $callArgs['chat'];
        $language = $this->guessLanguage->for($chat);
        $query = $this->inferQueryFromChat->for($chat);
        $subqueries = $this->makeSubqueries->for($query, $chat);
        $sources = [];
        foreach ($subqueries as $subquery) {
            $sources[] = $this->findSources->for(query: $subquery);
        }
        $context = implode("\n", array_merge(["SOURCES:\n"], $sources));
        $answer = $this->answerQuestion->for($query, $context);
        $answerLanguage = $this->guessLanguage->for($answer);
        $finalAnswer = match(true) {
            $answerLanguage === $language => $answer,
            default => $this->translate->from($answer, $language),
        };
        return [
            'answer' => $finalAnswer
        ];
    }
}
