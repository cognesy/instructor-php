<?php
namespace Cognesy\Experimental\Module\Modules\Chat;

//use Cognesy\Experimental\Module\Core\Module;
//use Cognesy\Experimental\Module\Core\Predictor;
use Cognesy\Experimental\Module\Modules\Prediction;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleSignature;

//use InvalidArgumentException;

#[ModuleSignature('chat -> query_with_context')]
#[ModuleDescription("Infer full user query with full context from the provided chat")]
class InferQueryFromChat extends Prediction
{
//    private Predictor $inferQueryFromChat;
//
//    public function __construct() {
//        $this->inferQueryFromChat = Predictor::fromSignature(
//            signature: 'chat -> query_with_context',
//            description: "Infer full user query with full context from the provided chat",
//        );
//    }
//
//    public function for(string $chat) : string {
//        return ($this)(chat: $chat)->get('query_with_context');
//    }
//
//    protected function forward(mixed ...$callArgs) : array {
//        $chat = $callArgs['chat'] ?? throw new InvalidArgumentException('Missing `chat` parameter');
//        $query = $this->inferQueryFromChat->predict(chat: $chat);
//        return [
//            'query_with_context' => $query
//        ];
//    }
}