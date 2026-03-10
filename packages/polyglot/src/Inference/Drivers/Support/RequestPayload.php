<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Support;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Utils\Arrays;

final class RequestPayload
{
    public static function filterEmptyValues(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    public static function responseFormatType(InferenceRequest $request): ?string
    {
        if (! $request->hasResponseFormat()) {
            return null;
        }

        return match ($request->responseFormat()->type()) {
            'text' => 'text',
            'json',
            'json_object' => 'json_object',
            'json_schema' => 'json_schema',
            default => null,
        };
    }

    public static function removeSchemaKeys(array $jsonSchema, array $keys): array
    {
        return Arrays::removeRecursively(array: $jsonSchema, keys: $keys);
    }
}
