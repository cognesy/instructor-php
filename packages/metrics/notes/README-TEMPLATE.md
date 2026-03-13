# Package Template

This is a template for creating new subpackages in the instructor-php monorepo.

## Creating a New Package

1. Create a JSON configuration file with the following structure:

```json
{
  "package_name": "your-package-name",
  "namespace": "YourPackageName", 
  "package_title": "Your Package Title",
  "package_description": "Description of your package functionality",
  "target_directory": "packages/your-package-name"
}
```

2. Run the package creation script:

```bash
php scripts/create-package.php your-config.json
```

## Template Placeholders

The following placeholders are replaced in template files:

- `instructor-metrics` - The package name (e.g., "dynamic", "messages")
- `Cognesy\Metrics` - The PSR-4 namespace (e.g., "Dynamic", "Messages")
- `Metrics` - The human-readable title for documentation
- `Event-driven metrics collection system for Instructor PHP library` - Description of the package functionality

## Example Configuration

See `package-config.example.json` in the project root for a complete example.