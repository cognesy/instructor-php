<?php

use Cognesy\Polyglot\Inference\Data\DriverCapabilities;

describe('DriverCapabilities', function () {

    it('reports inference features as supported by default', function () {
        $caps = new DriverCapabilities();

        expect($caps->supportsStreaming())->toBeTrue();
        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeTrue();
        expect($caps->maxContextTokens)->toBeNull();
        expect($caps->maxOutputTokens)->toBeNull();
    });

    it('reports disabled features when configured', function () {
        $caps = new DriverCapabilities(
            streaming: false,
            toolCalling: false,
            toolChoice: false,
            responseFormatJsonObject: false,
            responseFormatJsonSchema: false,
            responseFormatWithTools: false,
        );

        expect($caps->supportsStreaming())->toBeFalse();
        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsToolChoice())->toBeFalse();
        expect($caps->supportsResponseFormatJsonObject())->toBeFalse();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('stores optional limits when provided', function () {
        $caps = new DriverCapabilities(
            maxContextTokens: 200_000,
            maxOutputTokens: 8_192,
        );

        expect($caps->maxContextTokens)->toBe(200000);
        expect($caps->maxOutputTokens)->toBe(8192);
    });

    it('is readonly and exposes explicit feature fields', function () {
        $caps = new DriverCapabilities(
            streaming: true,
            toolCalling: true,
            toolChoice: true,
            responseFormatJsonObject: true,
            responseFormatJsonSchema: true,
            responseFormatWithTools: false,
            maxContextTokens: 131072,
            maxOutputTokens: 4096,
        );

        expect($caps->streaming)->toBeTrue();
        expect($caps->toolCalling)->toBeTrue();
        expect($caps->toolChoice)->toBeTrue();
        expect($caps->responseFormatJsonObject)->toBeTrue();
        expect($caps->responseFormatJsonSchema)->toBeTrue();
        expect($caps->responseFormatWithTools)->toBeFalse();
        expect($caps->maxContextTokens)->toBe(131072);
        expect($caps->maxOutputTokens)->toBe(4096);
    });
});
