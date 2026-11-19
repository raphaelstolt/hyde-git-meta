<?php

declare(strict_types=1);

namespace HydeGitMeta\Tests\Unit;

use HydeGitMeta\GitCommitInfo;
use PHPUnit\Framework\TestCase;

final class GitCommitInfoTest extends TestCase
{
    public function testCreatesValidCommitInfo(): void
    {
        $info = new GitCommitInfo(
            fullSha: 'abcdef1234567890abcdef1234567890abcdef12',
            shortSha: 'abcdef12',
            timestamp: 1700000000,
            authorName: 'Test Author',
            authorEmail: 'test@example.com',
            subject: 'Test commit',
        );

        $this->assertSame('abcdef1234567890abcdef1234567890abcdef12', $info->fullSha);
        $this->assertSame('abcdef12', $info->shortSha);
        $this->assertSame(1700000000, $info->timestamp);
        $this->assertSame('Test Author', $info->authorName);
        $this->assertSame('test@example.com', $info->authorEmail);
        $this->assertSame('Test commit', $info->subject);
    }

    public function testCreatesCommitInfoWithDefaults(): void
    {
        $info = new GitCommitInfo(
            fullSha: 'abcdef1234567890abcdef1234567890abcdef12',
            shortSha: 'abcdef12',
            timestamp: 1700000000,
        );

        $this->assertSame('', $info->authorName);
        $this->assertSame('', $info->authorEmail);
        $this->assertSame('', $info->subject);
    }

    public function testRejectsInvalidFullSha(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Full SHA must be a 40-character hexadecimal string.');

        new GitCommitInfo(
            fullSha: 'invalid',
            shortSha: 'abcdef12',
            timestamp: 1700000000,
        );
    }

    public function testRejectsShortFullSha(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GitCommitInfo(
            fullSha: 'abcdef1234567890',
            shortSha: 'abcdef12',
            timestamp: 1700000000,
        );
    }

    public function testRejectsEmptyShortSha(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Short SHA must not be empty.');

        new GitCommitInfo(
            fullSha: 'abcdef1234567890abcdef1234567890abcdef12',
            shortSha: '',
            timestamp: 1700000000,
        );
    }

    public function testRejectsNegativeTimestamp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp must be a non-negative integer.');

        new GitCommitInfo(
            fullSha: 'abcdef1234567890abcdef1234567890abcdef12',
            shortSha: 'abcdef12',
            timestamp: -1,
        );
    }

    public function testAcceptsZeroTimestamp(): void
    {
        $info = new GitCommitInfo(
            fullSha: 'abcdef1234567890abcdef1234567890abcdef12',
            shortSha: 'abcdef12',
            timestamp: 0,
        );

        $this->assertSame(0, $info->timestamp);
    }

    public function testRejectsNonHexFullSha(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GitCommitInfo(
            fullSha: 'ghijkl1234567890abcdef1234567890abcdef12',
            shortSha: 'abcdef12',
            timestamp: 1700000000,
        );
    }
}
