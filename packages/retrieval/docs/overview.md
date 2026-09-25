# Retrieval

InstructorPHP Retrieval provides one replaceable store contract for direct
vector persistence, bounded similarity search, document indexing, and RAG.

The package bundles in-memory, Pgvector, Qdrant, Typesense, Meilisearch,
Weaviate, and Milvus drivers behind one registry and store contract. Indexing
composes Polyglot embeddings through `Vectorizer`; semantic queries use the same
declared embedding space and remain lazy through `PendingRetrieval`.
`ContextAssembler` creates citation-labelled textual evidence under hard byte,
token, evidence, and excerpt budgets. Generation remains an explicit
`Inference` or `StructuredOutput` call.

See the [package README](../README.md) for installation, complete examples,
driver options and capabilities, large-corpus traversal, live-test setup,
Agents integration, observability, and the current multimodal support boundary.
