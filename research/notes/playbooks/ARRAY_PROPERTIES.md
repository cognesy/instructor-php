# Collections = Typed, Immutable, Intentional

*Subtitle: Converting "array properties" into domain collection objects that read like prose*

---

## 1) Replace arrays with dedicated collection types

*Subtitle: Domain language first, storage detail second*

* Arrays accept anything and silently lose invariants; collection classes communicate intent and filter inputs.
* Encapsulate domain vocabulary in the type name: `StudentList`, `CourseMap`, `PermissionSet`.
* Build on existing utilities (`Cognesy\Utils\Collection\ArrayList`, `ArrayMap`, `ArraySet`) instead of re-implementing plumbing.

**Before**

```php
final class Classroom
{
    /** @var Student[] */
    public array $students = [];

    public function addStudent(Student $student): void
    {
        $this->students[] = $student;
    }
}
```

**After**

```php
declare(strict_types=1);

use Cognesy\Utils\Collection\ArrayList;

final class StudentList implements Countable, IteratorAggregate
{
    /** @var ArrayList<Student> */
    private ArrayList $items;

    public function __construct(Student ...$students)
    {
        $this->items = ArrayList::of(...$students);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withStudent(Student $student): self
    {
        $next = $this->items->withAppended($student);
        return self::fromArray($next->all());
    }

    /** @return list<Student> */
    public function all(): array
    {
        return $this->items->all();
    }

    public static function fromArray(array $students): self
    {
        foreach ($students as $student) {
            if (!$student instanceof Student) {
                throw new InvalidArgumentException('StudentList expects Student instances only.');
            }
        }
        return new self(...$students);
    }

    public function getIterator(): Traversable
    {
        return $this->items->getIterator();
    }

    public function count(): int
    {
        return $this->items->count();
    }
}

final class Classroom
{
    public function __construct(private StudentList $students)
    {
    }

    public function withStudent(Student $student): self
    {
        return new self($this->students->withStudent($student));
    }
}
```

---

## 2) Name for semantics: list, map, set

*Subtitle: Communicate ordering, keys, and uniqueness*

* **List** → ordered, position-based access, duplicates allowed. Example: `LectureSegmentList`.
* **Map** → keyed lookup. Example: `QuestionOutcomeMap` keyed by question id.
* **Set** → uniqueness by a domain hash. Example: `InstructorPermissionSet` deduplicated by permission code.
* Reflect the behaviour in the class name and docblocks so usage is self-documenting.

---

## 3) Immutability all the way down

*Subtitle: Copy on write, predictable flow*

* Constructor stores an immutable snapshot; modification helpers return **new instances**.
* Provide ergonomic `with*`, `without*`, `filter*` methods that delegate to the underlying utility and re-wrap.
* Avoid `&$` references, `array_pop`, `array_shift`, or other in-place mutation.

**Before**

```php
final class EnrollmentTracker
{
    /** @var list<Enrollment> */
    private array $enrollments = [];

    public function add(Enrollment $enrollment): void
    {
        $this->enrollments[] = $enrollment; // mutation leaks state
    }
}
```

**After**

```php
final class EnrollmentList
{
    /** @var ArrayList<Enrollment> */
    private ArrayList $items;

    public function __construct(Enrollment ...$enrollments)
    {
        $this->items = ArrayList::of(...$enrollments);
    }

    public function withEnrollment(Enrollment $enrollment): self
    {
        $next = $this->items->withAdded($enrollment);
        return new self(...$next->all());
    }

    public function withoutEnrollment(Enrollment $enrollment): self
    {
        $filtered = $this->items->filter(
            fn (Enrollment $current) => !$current->isSameAs($enrollment)
        );
        return new self(...$filtered->all());
    }
}
```

---

## 4) Constructors + factories

*Subtitle: Simple, explicit entry points*

* Primary constructor is **variadic**: `__construct(DomainObject ...$items)`; it guarantees type safety at the boundary.
* Provide static factories for interop: `empty()`, `fromArray(array $items)`, `fromIterable(iterable $items)`, `of(DomainObject ...$items)`.
* Guard conversion factories with assertions/Result objects when data might be untrusted.

```php
final class CourseList
{
    /** @var ArrayList<Course> */
    private ArrayList $items;

    public function __construct(Course ...$courses)
    {
        $this->items = ArrayList::of(...$courses);
    }

    public static function fromArray(array $courses): self
    {
        foreach ($courses as $course) {
            if (!$course instanceof Course) {
                throw new InvalidArgumentException('CourseList expects Course instances only.');
            }
        }
        return new self(...$courses);
    }

    public static function fromIterable(iterable $courses): self
    {
        return self::fromArray(iterator_to_array($courses, preserve_keys: false));
    }
}
```

---

## 5) Minimal, pragmatic API surface

*Subtitle: Cover the operations teams actually use*

Start from a thin core and extend only when real use cases appear.

| Capability | Purpose | Implementation hint |
|------------|---------|---------------------|
| `all(): array` | Expose immutable snapshot | Return `$this->items->all()` |
| `each(): Traversable` | Lazy iteration with type safety | `return $this->items->getIterator();` |
| `with(DomainObject ...$items): static` | Bulk add semantics | Wrap `ArrayList::withAdded()` |
| `filter(callable $predicate): static` | Domain-specific subsets | `return new self(...$this->items->filter($predicate)->all());` |
| `map(callable $mapper): ListInterface` | Transform to other aggregates | Use `$this->items->map(...)` and document return type |
| `reduce(callable $reducer, mixed $initial): mixed` | Aggregate values | Delegate to `$this->items->reduce(...)` |
| `find(callable $predicate): ?DomainObject` | First match helper | Use `$this->items->filter(...)->first()` |
| `toArray(): array` | Leave collection context (IO boundaries) | Return `$this->items->toArray()` |
| `fromArray(array $items): static` | Inbound data hydration | Validate + call constructor |
| `contains(DomainObject $item): bool` | Equality semantics | For sets rely on `ArraySet` with hash callback |

**Tip:** expose domain-specific helpers (`active(): self`, `forTenant(TenantId $id): self`) instead of forcing consumers to write their own `filter()` lambdas.

---

## 6) Lean domain logic only

*Subtitle: Keep collections focused*

* Embed only behaviour that belongs to **the collection** (ordering, grouping, membership).
* Push formatting, serialization, and infrastructure concerns to adapters.
* Avoid mixing responsibilities like validation, persistence, or caching in the collection.

**Good example**

```php
final class SessionList
{
    /** @var ArrayList<Session> */
    private ArrayList $items;

    public function __construct(Session ...$sessions)
    {
        $this->items = ArrayList::of(...$sessions);
    }

    public function upcoming(Clock $clock): self
    {
        $filtered = $this->items->filter(
            fn (Session $session) => $session->startsAt()->isAfter($clock->now())
        );
        return new self(...$filtered->all());
    }
}
```

---

## 7) Choosing the right helper

*Subtitle: Reuse `Cognesy\Utils\Collection` primitives*

* `ArrayList` already provides immutability, `filter`, `map`, `reduce`, `concat`, and iterators.
* `ArrayMap` exposes `with()`, `withRemoved()`, `keys()`, `values()`, `merge()` for associative use-cases.
* `ArraySet` supplies uniqueness via injected hash/equality callables—ideal for deduplicating domain entities by id.
* Wrap or compose rather than re-implementing behaviour; delegate to the utility and re-wrap the result for a fluent domain API.

```php
final class InvitationMap
{
    /** @var ArrayMap<EmailAddress, Invitation> */
    private ArrayMap $map;

    public function __construct(Invitation ...$invitations)
    {
        $this->map = ArrayMap::fromArray(self::indexByEmail($invitations));
    }

    public function withInvitation(Invitation $invitation): self
    {
        $next = $this->map->with($invitation->email(), $invitation);
        return self::fromArray($next->toArray());
    }

    /** @param array<EmailAddress,Invitation> $entries */
    public static function fromArray(array $entries): self
    {
        return new self(...array_values($entries));
    }

    /** @param list<Invitation> $invitations @return array<EmailAddress,Invitation> */
    private static function indexByEmail(array $invitations): array
    {
        $grouped = [];
        foreach ($invitations as $invitation) {
            $grouped[$invitation->email()] = $invitation;
        }
        return $grouped;
    }
}
```

---

## 8) Migration playbook (array → collection)

*Subtitle: Safe incremental refactor*

1. **Introduce** a dedicated collection class alongside the existing array property.
2. **Wrap reads**: convert array consumers to use the collection API (`CourseList::fromArray($this->courses)` during transition).
3. **Flip constructor**: update aggregate root to accept the new collection type; keep `fromArray()` helper for legacy callers.
4. **Inline factories**: add `::empty()`, `::fromArray()` to ease adoption.
5. **Delete array property** once all callers use the collection.
6. **Add tests** at collection level to capture invariants previously implied (ordering, uniqueness, filtering).

---

## 9) Testing strategy

*Subtitle: Focus on behavior, not storage*

* Use Pest to cover domain-specific helpers (`active()`, `forTenant()`), equality semantics, and immutability guarantees.
* Assert that `with*` methods return **new** instances (`expect($new)->not->toBe($original)`).
* Validate boundary conversions: `fromArray()` rejects invalid payloads; `toArray()` returns plain PHP arrays ready for serialization.

---

## Do / Don’t

| Do | Don’t |
| --- | --- |
| Name collections with semantics (`UserList`, `FeatureFlagMap`) | Hide meaning behind `array $users` |
| Keep constructors variadic & type-hinted | Accept `iterable $items` without runtime checks |
| Return new instances on updates | Mutate internal arrays in place |
| Leverage `ArrayList`/`ArrayMap`/`ArraySet` | Rebuild custom collection logic each time |
| Add domain-specific helpers (`resolved()`, `pending()`) | Make consumers reimplement filtering manually |
| Use `toArray()` only at IO boundaries | Leak raw arrays through public properties |

---

## TL;DR

* Prefer typed, immutable collection classes over raw arrays.
* Pick the right semantic flavour (list/map/set) and say it in the name.
* Variadic constructors + thin helper API keep collections easy to use and hard to misuse.
* Compose with base collection utilities, add only domain logic, and keep arrays at the edges.
