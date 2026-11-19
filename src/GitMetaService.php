<?php

declare(strict_types=1);

namespace HydeGitMeta;

use Symfony\Component\Process\Process;

/**
 * Framework-independent service that resolves Git commit metadata for source files.
 *
 * Uses safe argument-vector execution to avoid shell injection.
 */
final class GitMetaService
{
    /** @var array<string, GitCommitInfo|null> */
    private array $cache = [];

    public function __construct(
        private readonly int $shortHashLength = 8,
        private readonly bool $strict = false,
    ) {
        if ($shortHashLength < 1 || $shortHashLength > 40) {
            throw new \InvalidArgumentException('Short hash length must be between 1 and 40.');
        }
    }

    /**
     * Resolve the most recent commit metadata for a source file.
     *
     * Returns null if the file has no Git history or Git is unavailable.
     * In strict mode, throws on failure instead of returning null.
     *
     * @param string $repositoryRoot Absolute path to the Git repository root.
     * @param string $sourcePath Relative path from repository root to the source file.
     */
    public function resolve(string $repositoryRoot, string $sourcePath): ?GitCommitInfo
    {
        $cacheKey = $repositoryRoot . '/' . $sourcePath;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $this->cache[$cacheKey] = $this->resolveOnce($repositoryRoot, $sourcePath);
        return $this->cache[$cacheKey];
    }

    /**
     * @param string $repositoryRoot Absolute path to the Git repository root.
     * @param string $sourcePath Relative path from repository root to the source file.
     */
    private function resolveOnce(string $repositoryRoot, string $sourcePath): ?GitCommitInfo
    {
        // Validate paths exist
        if (! is_dir($repositoryRoot)) {
            $this->handleError("Repository root does not exist: {$repositoryRoot}", $repositoryRoot, $sourcePath);
            return null;
        }

        $fullPath = rtrim($repositoryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sourcePath;

        if (! is_file($fullPath)) {
            $this->handleError("Source file does not exist: {$fullPath}", $repositoryRoot, $sourcePath);
            return null;
        }

        // Check if git is available
        if (!$this->isGitAvailable($repositoryRoot)) {
            $this->handleError('Git is not available or this is not a Git repository', $repositoryRoot, $sourcePath);
            return null;
        }

        // Get the commit info using safe argument-vector execution
        $format = '%H%n%h%n%at%n%an%n%ae%n%s';
        $command = ['git', '-C', $repositoryRoot, 'log', '-1', '--format=' . $format, '--', $sourcePath];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->handleError('Git log command failed', $repositoryRoot, $sourcePath);
            return null;
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            $this->handleError('No Git history found for file', $repositoryRoot, $sourcePath);
            return null;
        }

        $lines = explode("\n", $output);
        if (count($lines) < 6) {
            $this->handleError('Unexpected Git output format', $repositoryRoot, $sourcePath);
            return null;
        }

        $fullSha = trim($lines[0]);
        $shortSha = trim($lines[1]);
        $timestamp = (int) trim($lines[2]);
        $authorName = trim($lines[3]);
        $authorEmail = trim($lines[4]);
        $subject = trim($lines[5]);

        // Validate SHA
        if (! preg_match('/^[0-9a-f]{40}$/', $fullSha)) {
            $this->handleError('Invalid full SHA from Git', $repositoryRoot, $sourcePath);
            return null;
        }

        // Truncate short SHA to configured length
        $shortSha = substr($shortSha, 0, $this->shortHashLength);

        return new GitCommitInfo(
            fullSha: $fullSha,
            shortSha: $shortSha,
            timestamp: $timestamp,
            authorName: $authorName,
            authorEmail: $authorEmail,
            subject: $subject,
        );
    }

    /**
     * Check if Git is available and the directory is a repository.
     */
    private function isGitAvailable(string $repositoryRoot): bool
    {
        $process = new Process(['git', '-C', $repositoryRoot, 'rev-parse', '--git-dir']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Handle errors based on strict mode.
     */
    private function handleError(string $message, string $repositoryRoot, string $sourcePath): void
    {
        if ($this->strict) {
            throw new \RuntimeException("Git metadata resolution failed: {$message}");
        }
    }

    /**
     * Clear the internal cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
