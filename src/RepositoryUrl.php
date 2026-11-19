<?php

declare(strict_types=1);

namespace HydeGitMeta;

/**
 * Utility class for normalizing repository URLs and building commit links.
 *
 * Supports GitHub, GitLab, and Bitbucket URL formats.
 */
final class RepositoryUrl
{
    private ?string $normalizedUrl;

    /**
     * @param string|null $url The repository URL (HTTPS or SSH).
     */
    public function __construct(private ?string $url)
    {
        $this->normalizedUrl = $url !== null ? $this->normalize($url) : null;
    }

    /**
     * Get the normalized HTTPS URL, or null if not available.
     */
    public function get(): ?string
    {
        return $this->normalizedUrl;
    }

    /**
     * Build a commit URL for the given full SHA, or null if no valid URL is configured.
     *
     * @param non-empty-string $fullSha The full 40-character commit SHA.
     */
    public function commitUrl(string $fullSha): ?string
    {
        $url = $this->normalizedUrl;

        if ($url === null) {
            return null;
        }

        if (! preg_match('/^[0-9a-f]{40}$/', $fullSha)) {
            return null;
        }

        $host = $this->extractHost($url);

        if ($host === null) {
            return null;
        }

        return match (strtolower($host)) {
            'github.com' => $url . '/commit/' . $fullSha,
            'gitlab.com' => $url . '/-/commit/' . $fullSha,
            'bitbucket.org' => $url . '/commits/' . $fullSha,
            default => $url . '/commit/' . $fullSha,
        };
    }

    /**
     * Normalize a repository URL to its HTTPS form.
     *
     * Handles:
     * - HTTPS URLs (returned as-is, trailing slashes removed)
     * - SSH URLs (git@host:path format converted to HTTPS)
     */
    private function normalize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Handle SSH format: git@host:path/to/repo.git
        if (preg_match('/^git@([^:]+):(.+)$/', $url, $matches)) {
            $host = $matches[1];
            $path = $matches[2];

            // Remove .git suffix
            $path = rtrim($path, '.git');

            return 'https://' . $host . '/' . $path;
        }

        // Handle HTTPS URLs
        if (preg_match('/^https?:\/\//', $url)) {
            $url = rtrim($url, '/');

            // Remove .git suffix
            $url = preg_replace('/\.git$/', '', $url) ?? $url;

            // Ensure HTTPS
            return preg_replace('/^http:\/\//', 'https://', $url) ?? $url;
        }

        // Unsupported format
        return null;
    }

    /**
     * Extract the host from a URL.
     */
    private function extractHost(string $url): ?string
    {
        $parsed = parse_url($url, PHP_URL_HOST);

        if ($parsed === false || $parsed === '') {
            return null;
        }

        return $parsed;
    }
}
