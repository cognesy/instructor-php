# Design = Small, Composable, Explicit

*Subtitle: Repeatable rules + concrete examples for application domain classes*

---

## 1) Model one concern per class

*Subtitle: High cohesion, no God objects*

* Prefer **value objects** for data + invariants (e.g., `Metadata`, `Usage`, `StateInfo`).
* Keep behavior close to data; avoid “util” bags.
* If a class needs >1 reason to change → split.

**Before**

```php
final class Order {
    public function __construct(
        public array $items,
        public float $taxRate,
        public string $email,
        public string $status,
        public string $currency,
    ) {}
    public function addItem(array $item): void { $this->items[] = $item; }
    public function total(): float { /* sums, taxes, currency */ }
    public function sendConfirmation(): void { /* emails user */ }
    public function markPaid(): void { $this->status = 'paid'; }
}
```

**After**

```php
final class Money { public function __construct(public float $amount, public string $currency) {} }

final class OrderLines {
    public function __construct(private array $lines = []) {}
    public function withItem(array $line): self { $copy = clone $this; $copy->lines[] = $line; return $copy; }
    public function net(): float { /* sum lines */ }
}

final class OrderStatus { public function __construct(public string $value = 'new') {} }

final class Order { // façade
    public function __construct(
        public readonly OrderLines $lines,
        public readonly float $taxRate,
        public readonly OrderStatus $status,
    ) {}
    public function total(): Money { /* uses lines + taxRate */ }
}
```

---

## 2) Immutability by default

*Subtitle: Predictable flow, safe sharing*

* Treat domain classes as **immutable**.
* Mutations create a **new instance** via `copy()` or `withXxx()`.
* If a dependency is mutable, **wrap** and replace on write.

**Before**

```php
final class Inventory {
    public function __construct(public int $onHand) {}
    public function deduct(int $qty): void { $this->onHand -= $qty; }
}
```

**After**

```php
final class Inventory {
    public function __construct(private int $onHand) {}
    public function onHand(): int { return $this->onHand; }
    public function deduct(int $qty): self {
        return new self($this->onHand - $qty);
    }
}
```

---

## 3) Thin façade, composed internals

*Subtitle: Keep DX simple, internals modular*

* Public API mirrors domain language.
* Compose small objects behind a façade; **delegate** instead of inherit.
* Add helpers for common ops; keep heavy logic in composed parts.

**Before**

```php
final class Report {
    public function __construct(public array $data) {}
    public function renderCsv(): string { /* all logic here */ }
    public function renderPdf(): string { /* all logic here */ }
}
```

**After**

```php
interface RendersReport { public function render(array $data): string; }
final class CsvRenderer implements RendersReport { /* render(...) */ }
final class PdfRenderer implements RendersReport { /* render(...) */ }

final class Report {
    public function __construct(
        private array $data,
        private RendersReport $renderer
    ) {}
    public function output(): string { return $this->renderer->render($this->data); }
}
```

---

## 4) Ports over concrete types

*Subtitle: Stable contracts, swappable impls*

* Define **tiny interfaces** for capabilities (e.g., `HasMetadata`, `HasUsage`).
* Return domain types in ports; **accept** interfaces in constructors.
* Favor **constructor injection** with sensible defaults.

**Before**

```php
final class Notifier {
    public function __construct(private SmtpMailer $mailer) {}
    public function send(string $to, string $msg): void { $this->mailer->send($to, $msg); }
}
```

**After**

```php
interface SendsMail { public function send(string $to, string $msg): void; }
final class SmtpMailer implements SendsMail { /* ... */ }
final class SesMailer implements SendsMail { /* ... */ }

final class Notifier {
    public function __construct(private SendsMail $mailer) {}
    public function send(string $to, string $msg): void { $this->mailer->send($to, $msg); }
}
```

---

## 5) `copy()` as the single write-path

*Subtitle: Consistent updates, easy auditing*

* All “writes” go through `copy(...)`.
* `copy()` updates lifecycle (e.g., `updatedAt`) once, centrally.
* Provide a handful of `withXxx()` that call `copy()` under the hood.

**Before**

```php
final class Profile {
    public function __construct(
        public string $name,
        public array $tags,
        public DateTimeImmutable $updatedAt,
    ) {}
    public function addTag(string $t): void {
        $this->tags[] = $t;
        $this->updatedAt = new DateTimeImmutable(); // sprinkled
    }
    public function rename(string $n): void {
        $this->name = $n;
        $this->updatedAt = new DateTimeImmutable(); // sprinkled
    }
}
```

**After**

```php
final class Profile {
    public function __construct(
        private string $name,
        private array $tags,
        private DateTimeImmutable $updatedAt,
    ) {}
    private function copy(?string $name=null, ?array $tags=null): self {
        return new self($name ?? $this->name, $tags ?? $this->tags, new DateTimeImmutable());
    }
    public function withTag(string $t): self { return $this->copy(tags: [...$this->tags, $t]); }
    public function withName(string $n): self { return $this->copy(name: $n); }
}
```

---

## 6) Only wrap when it adds value

*Subtitle: No ceremony for ceremony’s sake*

* Store **raw domain objects** unless you need an immutability guard, invariant hub, anti-corruption, or a testing seam.

**Before (unnecessary wrapper)**

```php
final class MetadataWrapper {
    public function __construct(private Metadata $m) {}
    public function metadata(): Metadata { return $this->m; }
}
final class State {
    public function __construct(private MetadataWrapper $meta) {}
    public function metadata(): Metadata { return $this->meta->metadata(); }
}
```

**After (use raw)**

```php
final class State {
    public function __construct(private Metadata $metadata) {}
    public function metadata(): Metadata { return $this->metadata; }
}
```

**When a wrapper is worth it (mutable dependency)**

```php
final class SafeStore {
    public function __construct(private MessageStore $store) {}
    public function withStore(MessageStore $new): self { return new self($new); } // replace, not mutate
    public function messages(): Messages { /* read-only façade */ }
}
```

---

## 7) Construction is explicit, ergonomic

*Subtitle: Factories for flexible inputs*

* Provide a `from(...)` factory for flexible inputs (arrays → value objects).
* Validate invariants in constructor/factory; fail fast.

**Before**

```php
final class Customer {
    public function __construct(public string $name, public array $metadata) {}
}
$customer = new Customer('Ann', ['tier' => 'gold']); // OK, but no guardrails
```

**After**

```php
final class Metadata {
    public function __construct(private array $data = []) {}
    public static function fromArray(array $data): self { return new self($data); }
}

final class Customer {
    private function __construct(public string $name, public Metadata $metadata) {}
    public static function from(string $name, Metadata|array|null $meta = null): self {
        $m = $meta instanceof Metadata ? $meta : Metadata::fromArray($meta ?? []);
        return new self($name, $m);
    }
}
$customer = Customer::from('Ann', ['tier' => 'gold']);
```

---

## 8) Serialization stays at the edge

*Subtitle: Keep domain clean*

* Domain exposes `toArray()` (or DTO) for IO boundaries.
* No JSON/transport details inside core logic.

**Before**

```php
final class AuditEvent {
    public function __construct(public string $type, public array $payload) {}
    public function toJson(): string { return json_encode(['type'=>$this->type,'payload'=>$this->payload]); }
    public function signAndSend(HttpClient $http): void { /* transport logic here */ }
}
```

**After**

```php
final class AuditEvent {
    public function __construct(private string $type, private array $payload) {}
    public function type(): string { return $this->type; }
    public function payload(): array { return $this->payload; }
    public function toArray(): array { return ['type'=>$this->type, 'payload'=>$this->payload]; }
}
// Edge adapter
final class AuditTransport {
    public function __construct(private HttpClient $http) {}
    public function publish(AuditEvent $e): void {
        $this->http->post('/audit', json: $e->toArray());
    }
}
```

---

## 9) Naming and API shape

*Subtitle: Read like prose, avoid flags & getters*

* Nouns for objects, verbs for actions: `withMetadata()`, `accumulateUsage()`.
* Avoid abbreviations; be precise.
* Keep method lists short; favor composition over option flags.

**Before**

```php
final class Cfg {
    public function __construct(public array $vars) {}
    public function set(string $k, mixed $v, bool $overwrite=true): void { /* ... */ }
    public function get(string $k, $d=null) { /* ... */ }
}
```

**After**

```php
final class Configuration {
    public function __construct(private array $values = []) {}
    public function with(string $key, mixed $value): self {
        return new self([...$this->values, $key => $value]);
    }
    public function value(string $key): mixed {
        if (!array_key_exists($key, $this->values)) throw new RuntimeException("Missing config: $key");
        return $this->values[$key];
    }
}
```

---

## Testing Strategy (quick)

* Unit-test value objects (construct, invariants, `with*`, `copy`, `touch`).
* Integration-test façades for delegation and update rules.
* Use fakes/stubs at **port** boundaries; avoid heavy mocks.

---

## Do / Don’t (guardrails)

| Do                              | Don’t                     |
| ------------------------------- | ------------------------- |
| Make domain objects immutable   | Leak mutable internals    |
| Centralize updates via `copy()` | Update timestamps ad hoc  |
| Delegate from façade to parts   | Inherit for code reuse    |
| Keep ports tiny & stable        | Expose concrete internals |
| Wrap only for invariants/seams  | Add layers “just in case” |
| Return rich domain types        | Return arrays everywhere  |

---

## TL;DR

* Compose tiny domain objects behind a thin façade.
* One write-path (`copy()`), one clock (`touch()`), tiny ports.
* Wrap only when it **buys** invariants, seams, or DX—otherwise keep it raw and simple.
