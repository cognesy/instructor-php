<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Pgvector;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Contracts\CanFetchDocuments;
use Cognesy\Retrieval\Contracts\CanManageStore;
use Cognesy\Retrieval\Contracts\CanScanDocuments;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\DocumentPage;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Data\SearchScore;
use Cognesy\Retrieval\Data\StoreCapabilities;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Data\StoredDocument;
use Cognesy\Retrieval\Data\StoredDocuments;
use Cognesy\Retrieval\Data\StorePage;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Data\WriteResult;
use Cognesy\Retrieval\Filter\MatchAll;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Filter\MetadataFilter;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use InvalidArgumentException;
use JsonException;
use Override;
use PDO;
use PDOStatement;
use RuntimeException;

final readonly class PgvectorStore implements CanFetchDocuments, CanManageStore, CanScanDocuments, CanStoreDocuments
{
    public function __construct(
        private PgvectorConfig $config,
        private PDO $pdo,
    ) {}

    #[Override]
    public function upsert(VectorDocuments $documents): WriteResult
    {
        $table = $this->config->quotedTable();
        $sql = "INSERT INTO {$table} (id, id_type, embedding, metadata, content, embedding_space) "
            .'VALUES (:id, :id_type, CAST(:embedding AS vector), CAST(:metadata AS jsonb), :content, :space) '
            .'ON CONFLICT (id) DO UPDATE SET id_type = EXCLUDED.id_type, embedding = EXCLUDED.embedding, '
            .'metadata = EXCLUDED.metadata, content = EXCLUDED.content, embedding_space = EXCLUDED.embedding_space';
        $statement = $this->prepare($sql);
        foreach ($documents as $document) {
            $statement->execute([
                'id' => (string) $document->id,
                'id_type' => is_int($document->id) ? 'int' : 'string',
                'embedding' => self::vectorLiteral($document->vector),
                'metadata' => self::encode($document->metadata),
                'content' => $document->content,
                'space' => $document->embeddingSpace,
            ]);
        }
        $statement->closeCursor();

        return new WriteResult(acknowledged: $documents->count());
    }

    #[Override]
    public function remove(DocumentIds $ids): WriteResult
    {
        if ($ids->count() === 0) {
            return new WriteResult;
        }
        [$placeholders, $parameters] = self::idParameters($ids);
        $statement = $this->prepare("DELETE FROM {$this->config->quotedTable()} WHERE id IN ({$placeholders})");
        $statement->execute($parameters);
        $count = $statement->rowCount();
        $statement->closeCursor();

        return new WriteResult(acknowledged: $count, missing: $ids->count() - $count);
    }

    #[Override]
    public function clear(): WriteResult
    {
        $count = $this->pdo->exec("DELETE FROM {$this->config->quotedTable()}");

        return new WriteResult(acknowledged: $count === false ? 0 : $count, unknown: $count === false ? 1 : 0);
    }

    #[Override]
    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery || $query->continuation() !== null) {
            throw new InvalidArgumentException('Pgvector supports bounded vector queries without portable ranked continuation');
        }
        $operator = $this->operator();
        $score = match ($this->config->metric) {
            DistanceMetric::Cosine => "1 - (embedding {$operator} CAST(:score_vector AS vector))",
            DistanceMetric::DotProduct => "-(embedding {$operator} CAST(:score_vector AS vector))",
            DistanceMetric::Euclidean => "embedding {$operator} CAST(:score_vector AS vector)",
        };
        [$where, $parameters] = $this->where($query->filter());
        $columns = $this->columns($query->projection());
        $sql = "SELECT {$columns}, {$score} AS native_score FROM {$this->config->quotedTable()} {$where} "
            ."ORDER BY embedding {$operator} CAST(:order_vector AS vector) ASC LIMIT :result_limit";
        $statement = $this->prepare($sql);
        $vector = self::vectorLiteral($query->vector);
        foreach ([...$parameters, 'score_vector' => $vector, 'order_vector' => $vector] as $key => $value) {
            $statement->bindValue(':'.$key, $value);
        }
        $statement->bindValue(':result_limit', $query->limit(), PDO::PARAM_INT);
        $statement->execute();
        $hits = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (! is_array($row)) {
                continue;
            }
            $stored = $this->row($row, $query->projection());
            $hits[] = new SearchHit(
                $stored->id,
                count($hits) + 1,
                new SearchScore((float) ($row['native_score'] ?? 0.0), $query->metric, 'pgvector'),
                $stored->metadata,
                $stored->content,
                $stored->vector,
                $stored->embeddingSpace,
            );
        }
        $statement->closeCursor();

        return new StorePage(new SearchHits($hits));
    }

    #[Override]
    public function scan(ScanRequest $request): DocumentPage
    {
        if ($request->continuation !== null && $request->continuation->driver !== 'pgvector') {
            throw new InvalidArgumentException('Scan continuation belongs to another driver');
        }
        [$where, $parameters] = $this->where($request->filter());
        $lastId = $request->continuation?->position['last_id'] ?? null;
        if ($lastId !== null) {
            $where .= $where === '' ? 'WHERE id > :last_id' : ' AND id > :last_id';
            $parameters['last_id'] = (string) $lastId;
        }
        $sql = "SELECT {$this->columns($request->projection())} FROM {$this->config->quotedTable()} {$where} ORDER BY id ASC LIMIT :page_limit";
        $statement = $this->prepare($sql);
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':'.$key, $value);
        }
        $statement->bindValue(':page_limit', $request->pageSize, PDO::PARAM_INT);
        $statement->execute();
        $documents = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (is_array($row)) {
                $documents[] = $this->row($row, $request->projection());
            }
        }
        $statement->closeCursor();
        $last = $documents === [] ? null : $documents[count($documents) - 1];
        $continuation = count($documents) === $request->pageSize && $last !== null
            ? new StoreContinuation('pgvector', ['last_id' => (string) $last->id])
            : null;

        return new DocumentPage(new StoredDocuments($documents), $continuation);
    }

    #[Override]
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments
    {
        if ($ids->count() === 0) {
            return new StoredDocuments;
        }
        [$placeholders, $parameters] = self::idParameters($ids);
        $statement = $this->prepare("SELECT {$this->columns($projection)} FROM {$this->config->quotedTable()} WHERE id IN ({$placeholders})");
        $statement->execute($parameters);
        $documents = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (is_array($row)) {
                $documents[] = $this->row($row, $projection);
            }
        }
        $statement->closeCursor();

        return new StoredDocuments($documents);
    }

    #[Override]
    public function capabilities(): StoreCapabilities
    {
        return new StoreCapabilities(
            [VectorQuery::class],
            scan: true,
            fetch: true,
            exact: ! $this->config->approximate,
            approximate: $this->config->approximate,
            ordering: 'strict',
        );
    }

    #[Override]
    public function setup(): void
    {
        $this->pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->config->quotedTable()} ("
            .'id text PRIMARY KEY, id_type text NOT NULL, '
            ."embedding vector({$this->config->dimensions}) NOT NULL, "
            ."metadata jsonb NOT NULL DEFAULT '{}'::jsonb, content text NULL, embedding_space text NOT NULL DEFAULT '')");
        if ($this->config->approximate) {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$this->config->quotedIndex()} "
                ."ON {$this->config->quotedTable()} USING hnsw (embedding {$this->operatorClass()})");
        }
    }

    #[Override]
    public function drop(): void
    {
        $this->pdo->exec("DROP TABLE {$this->config->quotedTable()}");
    }

    private function operator(): string
    {
        return match ($this->config->metric) {
            DistanceMetric::Cosine => '<=>',
            DistanceMetric::Euclidean => '<->',
            DistanceMetric::DotProduct => '<#>',
        };
    }

    private function operatorClass(): string
    {
        return match ($this->config->metric) {
            DistanceMetric::Cosine => 'vector_cosine_ops',
            DistanceMetric::Euclidean => 'vector_l2_ops',
            DistanceMetric::DotProduct => 'vector_ip_ops',
        };
    }

    private function columns(Projection $projection): string
    {
        return implode(', ', array_filter([
            'id',
            'id_type',
            $projection->metadata ? 'metadata' : null,
            $projection->content ? 'content' : null,
            $projection->vector ? 'embedding::text AS embedding' : null,
            'embedding_space',
        ]));
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function where(MetadataFilter $filter): array
    {
        return match (true) {
            $filter instanceof MatchAll => ['', []],
            $filter instanceof MetadataEquals => [
                'WHERE metadata @> CAST(:metadata_filter AS jsonb)',
                ['metadata_filter' => self::encode([$filter->field => $filter->value])],
            ],
            default => throw new InvalidArgumentException('Filter is not supported by Pgvector'),
        };
    }

    /** @param array<string, mixed> $row */
    private function row(array $row, Projection $projection): StoredDocument
    {
        $id = (string) ($row['id'] ?? '');
        $typedId = ($row['id_type'] ?? 'string') === 'int' ? (int) $id : $id;
        $metadata = $projection->metadata ? self::decode((string) ($row['metadata'] ?? '{}')) : [];
        $content = $projection->content && is_string($row['content'] ?? null) ? $row['content'] : null;
        $vector = $projection->vector && is_string($row['embedding'] ?? null)
            ? new Vector(self::parseVector($row['embedding']))
            : null;

        return new StoredDocument(
            $typedId,
            $metadata,
            $content,
            $vector,
            (string) ($row['embedding_space'] ?? ''),
        );
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if (! $statement instanceof PDOStatement) {
            throw new RuntimeException('Unable to prepare Pgvector statement');
        }

        return $statement;
    }

    private static function vectorLiteral(Vector $vector): string
    {
        return '['.implode(',', $vector->values()).']';
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function idParameters(DocumentIds $ids): array
    {
        $parameters = [];
        $placeholders = [];
        foreach ($ids->all() as $index => $id) {
            $key = 'id_'.$index;
            $placeholders[] = ':'.$key;
            $parameters[$key] = (string) $id;
        }

        return [implode(', ', $placeholders), $parameters];
    }

    /** @param array<string, mixed> $value */
    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Metadata must be JSON serializable', 0, $error);
        }
    }

    /** @return array<string, mixed> */
    private static function decode(string $value): array
    {
        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Invalid JSON metadata returned by Pgvector', 0, $error);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<float> */
    private static function parseVector(string $value): array
    {
        $trimmed = trim($value, '[]');
        if ($trimmed === '') {
            return [];
        }

        return array_map(static fn (string $part): float => (float) $part, explode(',', $trimmed));
    }
}
