<?php
namespace Cognesy\Instructor\Data\Messages;

use Exception;

class Section {
    use Traits\Section\HandlesAccess;
    use Traits\Section\HandlesMutation;
    use Traits\Section\HandlesTransformation;

    public const MARKER = '@';
    private Messages $messages;
    private bool $isTemplate = false;

    public function __construct(
        public string $name,
        public string $description = '',
    ) {
        if (str_starts_with($name, self::MARKER)) {
            $this->isTemplate = true;
        }
        $this->messages = new Messages();
    }

    public function isTemplate() : bool {
        return $this->isTemplate;
    }

    public function messages() : Messages {
        return $this->messages;
    }
}

//enum StepType : string {
//    case GoalStatement = 'goal';
//    case GoalAcknowledgement = 'goal_ack';
//    case ContentProvision = 'content';
//    case ContentAcknowledgement = 'content_ack';
//    case StopAndThink = 'think';
//    case ContinueCommand = 'continue';
//    case ToolsRequest = 'tools';
//    case ToolsResponse = 'tools';
//    case InferenceRequest = 'inference';
//    case AssistantResponse = 'response';
//    case RetryRequest = 'retry';
//    case CustomUserStep = 'custom_user';
//    case CustomAssistantStep = 'custom_assistant';
//}
