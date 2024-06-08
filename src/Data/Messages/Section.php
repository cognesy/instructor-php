<?php
namespace Cognesy\Instructor\Data\Messages;

class Section {
    use Traits\Section\HandlesAccess;
    use Traits\Section\HandlesMutation;
    use Traits\Section\HandlesTransformation;

    private Messages $messages;

    public function __construct(
        public string $name,
        public string $description = '',
    ) {
        $this->messages = new Messages();
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
