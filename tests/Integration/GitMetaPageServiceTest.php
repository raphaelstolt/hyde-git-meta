<?php

declare(strict_types=1);

namespace HydeGitMeta\Tests\Integration;

use HydeGitMeta\GitCommitInfo;
use HydeGitMeta\GitMetaPageService;
use HydeGitMeta\GitMetaService;
use HydeGitMeta\RepositoryUrl;
use Hyde\Pages\Concerns\BaseMarkdownPage;
use PHPUnit\Framework\TestCase;

final class GitMetaPageServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hyde-git-meta-page-test-' . uniqid();
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

    /**
     * Create a mock page that only implements the methods GitMetaPageService needs.
     */
    private function createMockPage(string $sourcePath): BaseMarkdownPage
    {
        $page = $this->createMock(BaseMarkdownPage::class);
        $page->method('getSourcePath')->willReturn($sourcePath);
        return $page;
    }

    /**
     * Test that Git metadata is resolved when the Hyde project lives in a subdirectory
     * of the Git repository. This is the bug reported by the user.
     *
     * Setup:
     *   /repo/.git (Git root)
     *   /repo/_blog/ (Hyde project root, working directory)
     *   /repo/_blog/_posts/post.md (source file)
     *
     * getSourcePath() returns "_posts/post.md" (relative to Hyde project root).
     * findRepositoryRoot() must walk up from _blog/ to repo/ to find .git.
     */
    public function testResolvesMetadataWhenHydeProjectIsInSubdirectoryOfGitRepo(): void
    {
        $this->createGitRepo($this->tempDir);

        $hydeProjectDir = $this->tempDir . '/_blog';
        $postsDir = $hydeProjectDir . '/_posts';
        mkdir($postsDir, 0777, true);

        $this->commitFile($this->tempDir, '_blog/_posts/post.md', '# Hello World', 'Initial post');

        $originalCwd = getcwd();
        chdir($hydeProjectDir);

        $page = $this->createMockPage('_posts/post.md');

        $gitService = new GitMetaService();
        $repositoryUrl = new RepositoryUrl('https://github.com/user/repo.git');
        $pageService = new GitMetaPageService($gitService, $repositoryUrl, [BaseMarkdownPage::class]);

        $result = $pageService->forPage($page);

        chdir($originalCwd);

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Initial post', $result->subject);
        $this->assertSame('Test Author', $result->authorName);
    }

    /**
     * Test that metadata is resolved when the Hyde project root IS the Git repository root.
     */
    public function testResolvesMetadataWhenHydeProjectIsGitRepoRoot(): void
    {
        $this->createGitRepo($this->tempDir);
        $postsDir = $this->tempDir . '/_posts';
        mkdir($postsDir, 0777, true);

        $this->commitFile($this->tempDir, '_posts/post.md', '# Hello World', 'Initial post');

        $originalCwd = getcwd();
        chdir($this->tempDir);

        $page = $this->createMockPage('_posts/post.md');

        $gitService = new GitMetaService();
        $repositoryUrl = new RepositoryUrl('https://github.com/user/repo.git');
        $pageService = new GitMetaPageService($gitService, $repositoryUrl, [BaseMarkdownPage::class]);

        $result = $pageService->forPage($page);

        chdir($originalCwd);

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Initial post', $result->subject);
    }

    /**
     * Test nested subdirectory scenario: Hyde project is deeply nested in the Git repo.
     */
    public function testResolvesMetadataWhenHydeProjectIsDeeplyNested(): void
    {
        $this->createGitRepo($this->tempDir);

        $hydeProjectDir = $this->tempDir . '/src/site/content';
        $postsDir = $hydeProjectDir . '/_posts';
        mkdir($postsDir, 0777, true);

        $this->commitFile($this->tempDir, 'src/site/content/_posts/post.md', '# Nested', 'Add nested post');

        $originalCwd = getcwd();
        chdir($hydeProjectDir);

        $page = $this->createMockPage('_posts/post.md');

        $gitService = new GitMetaService();
        $repositoryUrl = new RepositoryUrl('https://github.com/user/repo.git');
        $pageService = new GitMetaPageService($gitService, $repositoryUrl, [BaseMarkdownPage::class]);

        $result = $pageService->forPage($page);

        chdir($originalCwd);

        $this->assertInstanceOf(GitCommitInfo::class, $result);
        $this->assertSame('Add nested post', $result->subject);
    }

    /**
     * Test that null is returned when no Git repository is found.
     */
    public function testReturnsNullWhenNoGitRepoFound(): void
    {
        $notGitDir = $this->tempDir . '/not-git';
        $postsDir = $notGitDir . '/_posts';
        mkdir($postsDir, 0777, true);
        file_put_contents($postsDir . '/post.md', '# No Git');

        $originalCwd = getcwd();
        chdir($notGitDir);

        $page = $this->createMockPage('_posts/post.md');

        $gitService = new GitMetaService();
        $repositoryUrl = new RepositoryUrl('https://github.com/user/repo.git');
        $pageService = new GitMetaPageService($gitService, $repositoryUrl, [BaseMarkdownPage::class]);

        $result = $pageService->forPage($page);

        chdir($originalCwd);

        $this->assertNull($result);
    }
}
