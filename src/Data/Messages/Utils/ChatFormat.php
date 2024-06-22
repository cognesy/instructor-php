<?php

namespace Cognesy\Instructor\Data\Messages\Utils;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ChatFormat
{
    static public function mapToTargetAPI(ClientType $clientType, array $messages) : array {
        if (empty($messages)) {
            return [];
        }

        $contentKey = $clientType->contentKey();
        return array_map(function($message) use ($clientType, $contentKey) {
            return [
                'role' => $clientType->mapRole($message['role']),
                $contentKey => $message['content']
            ];
        }, $messages);
    }
}