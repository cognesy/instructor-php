<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Internal;

use Cognesy\Experimental\Signature\Signature;
use Cognesy\Schema\Data\Schema;

final class SignatureStringRenderer
{
    public static function full(Schema $input, Schema $output) : string {
        return self::render(
            $input,
            $output,
            static fn(Schema $schema) : string => self::fullProperty($schema),
        );
    }

    public static function short(Schema $input, Schema $output) : string {
        return self::render(
            $input,
            $output,
            static fn(Schema $schema) : string => $schema->name(),
        );
    }

    private static function render(Schema $input, Schema $output, callable $propertyRenderer) : string {
        $inputs = array_map($propertyRenderer, $input->getPropertySchemas());
        $outputs = array_map($propertyRenderer, $output->getPropertySchemas());
        return implode('', [
            implode(', ', $inputs),
            ' ' . Signature::ARROW . ' ',
            implode(', ', $outputs),
        ]);
    }

    private static function fullProperty(Schema $schema) : string {
        $description = '';
        if ($schema->description() !== '') {
            $description = ' (' . $schema->description() . ')';
        }

        return $schema->name() . ':' . (string) $schema->type() . $description;
    }
}
