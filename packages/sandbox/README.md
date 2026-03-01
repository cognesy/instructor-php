# Instructor Sandbox

Sandboxed command execution for PHP (host, Docker, Podman, Firejail, Bubblewrap).

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$sandbox = Sandbox::host(ExecutionPolicy::in(__DIR__));
$result = $sandbox->execute(['php', '-v']);

echo $result->stdout();
```

More docs:

- [docs/1-overview.md](docs/1-overview.md)
- [CHEATSHEET.md](CHEATSHEET.md)
