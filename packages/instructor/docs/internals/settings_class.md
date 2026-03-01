
## `Settings` Class

`Settings` is a read-only config loader used by Instructor packages.

Most relevant methods:
- `setPath(string $dir)` - override config search path
- `flush()` - clear cached config and reset custom paths
- `has(string $group, ?string $key = null)` - check group/key existence
- `get(string $group, string $key, mixed $default = null)` - read value
- `getGroup(string $group)` - read full config group as array
- `hasGroup(string $group)` - check if group file exists

Notes:
- There is no mutable `set(group, key, value)` API.
- Path resolution supports both `INSTRUCTOR_CONFIG_PATHS` and `INSTRUCTOR_CONFIG_PATH`.
