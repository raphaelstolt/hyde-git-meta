<?php

declare(strict_types=1);

namespace HydeGitMeta;

/**
 * Immutable value object representing Git commit metadata for a source file.
 */
final class GitCommitInfo
{
    /**
     * @param non-empty-string $fullSha The full 40-character commit SHA.
     * @param non-empty-string $shortSha The abbreviated commit SHA.
     * @param int<0, max> $timestamp Unix timestamp of the commit date.
     * @param string $authorName Author name from the commit.
     * @param string $authorEmail Author email from the commit.
     * @param string $subject Commit subject line.
     */
    public function __construct(
        public readonly string $fullSha,
        public readonly string $shortSha,
        public readonly int $timestamp,
        public readonly string $authorName = '',
        public readonly string $authorEmail = '',
        public readonly string $subject = '',
    ) {
        if ($fullSha === '' || ! preg_match('/^[0-9a-f]{40}$/', $fullSha)) {
            throw new \InvalidArgumentException('Full SHA must be a 40-character hexadecimal string.');
        }

        if ($shortSha === '') {
            throw new \InvalidArgumentException('Short SHA must not be empty.');
        }

        if ($timestamp < 0) {
            throw new \InvalidArgumentException('Timestamp must be a non-negative integer.');
        }
    }
}
