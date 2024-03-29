# Sequenceable/Sequence::toSchema()

Currently:

```php
public function toSchema(
    SchemaFactory $schemaFactory,
    TypeDetailsFactory $typeDetailsFactory,
): Schema {}
```

Should be changed to:

```php
public function toSchema(): Schema {}
```

But I need to figure out how to provide access to the SchemaFactory and TypeDetailsFactory.

## Solution

Refactored the code, now does not require any arguments.
You can instantiate factories in the method body manually, as they've
been made simpler (no config / container needed).
