
## `Settings` Class

`Settings` class is the main entry point for telling Instructor where to look for its configuration. It allows you to set up path of Instructor's configuration directory and access Instructor settings.

`Settings` class provides the following methods:
- `setPath(string $path)`: Set the path to Instructor's configuration directory
- `getPath(): string`: Get current path to Instructor's configuration directory
- `has($group, $key): bool`: Check if a specific setting exists in Instructor's configuration
- `get($group, $key, $default): mixed`: Get a specific setting from Instructor's configuration
- `set($group, $key, $value)`: Set a specific setting in Instructor's configuration

You won't usually need to use these methods directly, but they are used internally by Instructor to access configuration settings.
