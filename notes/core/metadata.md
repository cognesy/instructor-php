# Metadata

Need a better way to handle model metadata. Currently, we rely on 2 building blocks:

- PHPDocs
- Type information
- Attributes (limited - validation)

Redesigned engine does not offer an easy way to handle custom Attributes.

Not sure if Attributes are the ultimate answer, as they are static and cannot be easily manipulated at runtime.

Pydantic approach is to take over the whole model definition via Field() calls, but PHP does not allow us to do something similar, at least in a clean way.

```php
class User {
    public string $name;
    public string $email = new Field(description: 'Email address'); // This is not possible in PHP
}
```
