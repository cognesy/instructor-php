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
    protected AnswerQuestion $answerQuestion;
    protected Translate $translate;
    protected MakeSubqueries $makeSubqueries;
    protected FindSources $findSources;
    protected InferQueryFromChat $inferQueryFromChat;
    protected GuessLanguage $guessLanguage;
    protected array $urls;

    public function __construct(array $urls = []) {
        $this->guessLanguage = new GuessLanguage();
        $this->inferQueryFromChat = new InferQueryFromChat();
        $this->makeSubqueries = new MakeSubqueries();
        $this->findSources = new FindSources();
        $this->answerQuestion = new AnswerQuestion();
        $this->translate = new Translate();
        $this->urls = $urls;
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
        $language = $this->guessLanguage->for(text: $chat);
        $query = $this->inferQueryFromChat->for(chat: $chat);
        $subqueries = $this->makeSubqueries->for(question: $query, context: $chat);
        $sources = [];
        foreach ($subqueries as $subquery) {
            $sources[] = $this->findSources->for(sourceUrls: $this->urls, query: $subquery, topK: 3);
        }
dd($sources);
        $context = implode("\n", array_merge(["SOURCES:\n"], $sources));
        $answer = $this->answerQuestion->for(question: $query, context: $context);
        $answerLanguage = $this->guessLanguage->for(text: $answer);
        $finalAnswer = match(true) {
            $answerLanguage === $language => $answer,
            default => $this->translate->for(text: $answer, language: $language),
        };
        return [
            'answer' => $finalAnswer
        ];
    }
}
