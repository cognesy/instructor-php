<?php declare(strict_types=1);

namespace Cognesy\Schema\Attributes;

use Cognesy\Schema\Data\TypeDetails;

abstract class SignatureField
{
    public string $name = '';
    public string $description = '';
    public ?TypeDetails $type = null;

    public function __construct(
        string $description = '',
    ) {
        $this->description = $description;
    }

    public function name(): string {
        return $this->name;
    }

    public function description(): string {
        return $this->description;
    }

    public function type(): TypeDetails {
        return $this->type ?? TypeDetails::mixed();
    }

    /** @return static */
    public static function string(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::string();
        return $field;
    }

    /** @return static */
    public static function int(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::int();
        return $field;
    }

    /** @return static */
    public static function float(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::float();
        return $field;
    }

    /** @return static */
    public static function bool(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::bool();
        return $field;
    }

    /** @return static */
    public static function collection(string $name, string $itemType, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::collection($itemType);
        return $field;
    }

    /** @return static */
    public static function array(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::array();
        return $field;
    }

    /**
     * @param class-string $class
     * @return static
     */
    public static function object(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::object($class);
        return $field;
    }

    /**
     * @param class-string $class
     * @return static
     */
    public static function enum(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::enum($class);
        return $field;
    }
}