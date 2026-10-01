# Support replies with sources

This fictional SaaS scenario composes Retrieval, Polyglot embeddings, and
Instructor structured output. The application owns the approved knowledge set,
account facts, citation acceptance, and human review. No reply is sent and no
refund or cancellation is executed.

Run from the monorepo root after `composer install`:

```sh
php examples/B08_Retrieval/SupportReplyWithSources/run.php
just examples-replay support_reply_with_sources
```

The default is keyless and uses fixture vectors and fixture model responses.
They deliberately force the failure cases; they do not measure semantic search
quality or a model's factual accuracy. Checks throw on failure even when PHP's
native `assert()` is disabled.

## Reading the walkthrough

The runtime output follows the support request through 6 numbered stages: account
inputs, policy indexing, similarity ranking and eligibility, evidence assembly,
typed generation, and support review. Search results show actual cosine scores
and why a policy is eligible. Evidence usage is shown against each configured
limit, followed by the excerpts actually supplied to generation. The generated
reply is printed as wrapped paragraphs with source references for the reviewer.

With `--live`, the provider workflow appears first, followed by a separate section
of deterministic boundary checks. The default shows the same workflow with fixture
vectors and a scripted reply. Replay mode explicitly identifies recorded responses.
The account fixture supplies the renewal age; this example does not compute refund
entitlement from similarity scores.

## Live and recorded provider checks

```sh
# Uses configured OpenAI credentials and makes paid provider calls.
php examples/B08_Retrieval/SupportReplyWithSources/run.php --live

# Replays the captured provider interactions without provider access.
INSTRUCTOR_EXAMPLES_HTTP=replay php examples/B08_Retrieval/SupportReplyWithSources/run.php --live
```

The captured run uses `text-embedding-3-small` and `gpt-4o-mini`. The final cassette
contains 9 interactions: 6 document embeddings, 2 query embeddings, and one
generation request. Record/replay verifies request fingerprints and sequence.
To record another run without appending to this cassette, use
`INSTRUCTOR_EXAMPLES_RECORDINGS_DIR=/tmp/support-reply-new-recording` together
with `INSTRUCTOR_EXAMPLES_HTTP=record` and `--live`.

## Verified behavior

| Case | Observed result |
| --- | --- |
| Unfiltered fixture ranking | Archived policy and Enterprise exception rank first. |
| Approved knowledge set | Only current Standard documents are eligible. |
| Normal context | 558 bytes, 121 local tokens, 2 sources, one hit omitted. |
| Repeated long documents | 220 bytes, 60 local tokens, 2 sources, one hit omitted; every excerpt is at most 120 bytes. |
| Independent limits | Byte-only case uses 80 of 80 bytes; token-only case uses 10 of 10 local tokens. |
| Missing approved corpus | `insufficient_evidence`, zero additional generation calls. |
| Typed draft with `S99` | Rejected by application citation membership check. |
| Inline labels differ from the citation list | Rejected by application consistency check. |
| Unsupported statement with a valid label | Passes citation checks; factual support still requires review. |

The proof uses the bundled `Gpt3TokenizerDriver` (`r50k_base`) explicitly so it
works offline and its counts are reproducible. Its token count is not the exact
token count for `gpt-4o-mini`. Production callers should choose the target model's
tokenizer and separately budget instructions, account facts, message, schema,
and output. Byte, token, source-count, and excerpt caps here cover evidence only.

`knowledge_set` is one application-maintained metadata equality value. The
application determines that value from trusted account configuration. Retrieval
does not decide authorization, policy freshness, or refund entitlement. The
in-memory store and short, unsplit documents keep this example self-contained.

## Evidence from 1 October 2026

- [Deterministic output](evidence/deterministic.txt)
- [Final live output](evidence/live.txt)
- [First live draft](evidence/first-live-draft.json)
- [Narrated walkthrough rendered from the final provider recording](evidence/recorded-walkthrough.txt)

The first live query ranked `archived-refund` first without filtering. The
approved query ranked `cancel-renewal`, `standard-refund`, and `payment-errors`.
The first generated DTO listed `S1` and `S2` but omitted inline labels from its
body; the application rejected it. Schema descriptions were then added and the
live runtime was made explicitly JSON-based, matching the fixture runtime. The
second run produced a customer-facing draft with matching inline labels and
source references. This is an observed run, not a guarantee of future behavior.

The live check fails if either answer-bearing policy drops out of the assembled
context, if the draft asks for a handoff, or if source-label checks fail. A fresh
provider run may fail those checks. In an application, that result should route
to review rather than become a customer-visible reply.
