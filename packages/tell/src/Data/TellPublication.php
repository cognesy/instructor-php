<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Immutable evidence describing whether and where a Tell turn was published. */
final readonly class TellPublication
{
    /** @param 'current'|'invocation'|null $branchSource */
    public function __construct(
        public TellPublicationStatus $status,
        public bool $requested,
        public ?string $workspace = null,
        public ?string $branch = null,
        public ?string $session = null,
        public ?string $branchSource = null,
        public ?string $baseHead = null,
        public ?string $publishedHead = null,
        public ?string $failureCode = null,
    ) {}

    /** @param 'current'|'invocation'|null $branchSource */
    public static function notApplicable(
        ?string $workspace = null,
        ?string $branch = null,
        ?string $session = null,
        ?string $branchSource = null,
    ): self {
        return new self(
            status: TellPublicationStatus::NotApplicable,
            requested: false,
            workspace: $workspace,
            branch: $branch,
            session: $session,
            branchSource: $branchSource,
        );
    }

    /** @param 'current'|'invocation'|null $branchSource */
    public static function notAttempted(
        ?string $workspace = null,
        ?string $branch = null,
        ?string $session = null,
        ?string $branchSource = null,
    ): self {
        return new self(
            status: TellPublicationStatus::NotAttempted,
            requested: true,
            workspace: $workspace,
            branch: $branch,
            session: $session,
            branchSource: $branchSource,
        );
    }

    public function published(?string $baseHead, string $publishedHead): self {
        return new self(
            status: TellPublicationStatus::Published,
            requested: true,
            workspace: $this->workspace,
            branch: $this->branch,
            session: $this->session,
            branchSource: $this->branchSource,
            baseHead: $baseHead,
            publishedHead: $publishedHead,
        );
    }

    public function failed(string $failureCode, ?string $baseHead = null): self {
        return new self(
            status: TellPublicationStatus::Failed,
            requested: true,
            workspace: $this->workspace,
            branch: $this->branch,
            session: $this->session,
            branchSource: $this->branchSource,
            baseHead: $baseHead,
            failureCode: $failureCode,
        );
    }

    public function isPublished(): bool {
        return $this->status === TellPublicationStatus::Published;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'status' => $this->status->value,
            'requested' => $this->requested,
            'workspace' => $this->workspace,
            'branch' => $this->branch,
            'branchSource' => $this->branchSource,
            'session' => $this->session,
            'baseHead' => $this->baseHead,
            'publishedHead' => $this->publishedHead,
            'failureCode' => $this->failureCode,
        ];
    }
}
