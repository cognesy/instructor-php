<?php

namespace Cognesy\Instructor\Core\Messages\Utils;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

class ChatFormat
{
    static public function mapToTargetAPI(ClientType $clientType, array $messages) : array {
        if (empty($messages)) {
            return [];
        }

        $roleMap = [
            ClientType::Anthropic->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'assistant', 'tool' => 'user'],
            ClientType::Cohere->value => ['user' => 'USER', 'assistant' => 'CHATBOT', 'system' => 'CHATBOT', 'tool' => 'USER'],
            ClientType::Mistral->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            ClientType::OpenAI->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            ClientType::OpenAICompatible->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
        ];

        $keyMap = [
            ClientType::Anthropic->value => 'content',
            ClientType::Cohere->value => 'message',
            ClientType::Mistral->value => 'content',
            ClientType::OpenAICompatible->value => 'content',
            ClientType::OpenAI->value => 'content',
        ];

        $roles = $roleMap[$clientType->value];
        $key = $keyMap[$clientType->value];

        return array_map(function($message) use ($roles, $key) {
            return ['role' => $roles[$message['role']], $key => $message['content']];
        }, $messages);
    }
}