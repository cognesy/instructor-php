<?php declare(strict_types=1);

namespace Cognesy\Template\Enums;

enum TemplateEngineType : string
{
    case Twig = 'twig';
    case Blade = 'blade';
    case Arrowpipe = 'arrowpipe';

    public static function fromText(string $text): self {
        return match ($text) {
            'twig' => self::Twig,
            'blade' => self::Blade,
            'arrowpipe' => self::Arrowpipe,
            default => throw new \InvalidArgumentException("Unknown template engine type: $text"),
        };
    }
}
