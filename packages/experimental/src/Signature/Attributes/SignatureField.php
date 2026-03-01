<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Attributes;

use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\Type;

abstract class SignatureField
{
    public string $name = '';
    public string $description = '';
    public ?Type $type = null;

    public function __construct(
        string $description = '',
    ) {
        $this->description = $description;
    }

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }

    public function type() : Type {
        return $this->type ?? Type::mixed();
    }

    /** @return static */
    public static function string(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::string();
        return $field;
    }

    /** @return static */
    public static function int(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::int();
        return $field;
    }

    /** @return static */
    public static function float(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::float();
        return $field;
    }

    /** @return static */
    public static function bool(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::bool();
        return $field;
    }

    /** @return static */
    public static function collection(string $name, string $itemType, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::list(TypeInfo::fromTypeName($itemType));
        return $field;
    }

    /** @return static */
    public static function array(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::array();
        return $field;
    }

    /**
     * @param class-string $class
     * @return static
     */
    public static function object(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::object($class);
        return $field;
    }

    /**
     * @param class-string $class
     * @return static
     */
    public static function enum(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = Type::enum($class);
        return $field;
    }
}
