<?php

declare(strict_types=1);

namespace HydeGitMeta\Tests\Integration;

use HydeGitMeta\GitCommitInfo;
use HydeGitMeta\GitMetaService;
use PHPUnit\Framework\TestCase;

final class GitMetaServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hyde-git-meta-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_filter(scandir($dir), static fn ($file) => $file !== '.' && $file !== '..');
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createGitRepo(string $path): void
    {
        $this->execGit($path, ['init', '-b', 'main']);
        $this->execGit($path, ['config', 'user.email', 'test@example.com']);
        $this->execGit($path, ['config', 'user.name', 'Test Author']);
    }

    private function commitFile(string $repoPath, string $relativePath, string $content, string $message): void
    {
        $fullPath = $repoPath . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($fullPath, $content);
        $this->execGit($repoPath, ['add', $relativePath]);
        $this->execGit($repoPath, ['commit', '-m', $message, '--date', '2024-01-15T10:00:00']);
    }

    private function execGit(string $repoPath, array $args): void
    {
        $process = new \Symfony\Component\Process\Process(array_merge(['git', '-C', $repoPath], $args));
        $process->mustRun();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Git command failed: " . $process->getErrorOutput());
        }
    }

    public function testResolvesCommitForTrackedFile(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post.md', '# Hello World', 'Initial post');

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'post.md');

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Initial post', $result->subject);
        $this->assertSame('Test Author', $result->authorName);
        $this->assertSame('test@example.com', $result->authorEmail);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result->fullSha);
        $this->assertLessThanOrEqual(8, strlen($result->shortSha));
    }

    public function testReturnsNullForUntrackedFile(): void
    {
        $this->createGitRepo($this->tempDir);
        file_put_contents($this->tempDir . '/untracked.md', '# Untracked');

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'untracked.md');

        $this->assertNull($result);
    }

    public function testReturnsNullForNonExistentFile(): void
    {
        $this->createGitRepo($this->tempDir);

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'nonexistent.md');

        $this->assertNull($result);
    }

    public function testReturnsNullForNonRepositoryDirectory(): void
    {
        $notGitDir = $this->tempDir . '/not-git';
        mkdir($notGitDir);
        file_put_contents($notGitDir . '/file.md', '# File');

        $service = new GitMetaService();
        $result = $service->resolve($notGitDir, 'file.md');

        $this->assertNull($result);
    }

    public function testReturnsCorrectCommitForModifiedFile(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post.md', '# Version 1', 'First version');
        $this->commitFile($this->tempDir, 'post.md', '# Version 2', 'Second version');

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'post.md');

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Second version', $result->subject);
    }

    public function testDifferentFilesHaveDifferentCommits(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post1.md', '# Post 1', 'Add post 1');
        $this->commitFile($this->tempDir, 'post2.md', '# Post 2', 'Add post 2');

        $service = new GitMetaService();
        $result1 = $service->resolve($this->tempDir, 'post1.md');
        $result2 = $service->resolve($this->tempDir, 'post2.md');

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotSame($result1->fullSha, $result2->fullSha);
        $this->assertSame('Add post 1', $result1->subject);
        $this->assertSame('Add post 2', $result2->subject);
    }

    public function testModifyingOneFileDoesNotAffectAnother(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post1.md', '# Post 1', 'Add post 1');
        $this->commitFile($this->tempDir, 'post2.md', '# Post 2', 'Add post 2');
        $this->commitFile($this->tempDir, 'post1.md', '# Post 1 Updated', 'Update post 1');

        $service = new GitMetaService();
        $result1 = $service->resolve($this->tempDir, 'post1.md');
        $result2 = $service->resolve($this->tempDir, 'post2.md');

        $this->assertSame('Update post 1', $result1->subject);
        $this->assertSame('Add post 2', $result2->subject);
    }

    public function testCachesResults(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post.md', '# Hello', 'Add post');

        $service = new GitMetaService();
        $result1 = $service->resolve($this->tempDir, 'post.md');
        $result2 = $service->resolve($this->tempDir, 'post.md');

        $this->assertSame($result1, $result2);
    }

    public function testClearCacheResetsCache(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post.md', '# Hello', 'Add post');

        $service = new GitMetaService();
        $service->resolve($this->tempDir, 'post.md');
        $service->clearCache();

        // After clearing cache, resolving again should still work
        $result = $service->resolve($this->tempDir, 'post.md');
        $this->assertInstanceOf(GitCommitInfo::class, $result);
    }

    public function testConfigurableShortHashLength(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'post.md', '# Hello', 'Add post');

        $service = new GitMetaService(shortHashLength: 7);
        $result = $service->resolve($this->tempDir, 'post.md');

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame(7, strlen($result->shortSha));
    }

    public function testStrictModeThrowsOnFailure(): void
    {
        $this->createGitRepo($this->tempDir);
        file_put_contents($this->tempDir . '/untracked.md', '# Untracked');

        $service = new GitMetaService(strict: true);

        $this->expectException(\RuntimeException::class);
        $service->resolve($this->tempDir, 'untracked.md');
    }

    public function testHandlesNestedDirectories(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'content/posts/post.md', '# Nested', 'Add nested post');

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'content/posts/post.md');

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Add nested post', $result->subject);
    }

    public function testHandlesFilenamesWithSpaces(): void
    {
        $this->createGitRepo($this->tempDir);
        $this->commitFile($this->tempDir, 'my post.md', '# Spaces', 'Add post with spaces');

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'my post.md');

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Add post with spaces', $result->subject);
    }

    public function testRejectsInvalidShortHashLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GitMetaService(shortHashLength: 0);
    }

    public function testReturnsNullForNonExistentRepositoryRoot(): void
    {
        $service = new GitMetaService();
        $result = $service->resolve('/nonexistent/path', 'file.md');

        $this->assertNull($result);
    }

    public function testEmptyRepositoryReturnsNull(): void
    {
        $this->createGitRepo($this->tempDir);

        $service = new GitMetaService();
        $result = $service->resolve($this->tempDir, 'missing.md');

        $this->assertNull($result);
    }
}
