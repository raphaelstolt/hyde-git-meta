<?php

declare(strict_types=1);

namespace HydeGitMeta;

use Hyde\Pages\Concerns\BaseMarkdownPage;
use Hyde\Pages\Concerns\HydePage;
use InvalidArgumentException;

/**
 * Service that resolves Git metadata for HydePHP pages.
 *
 * Maps eligible pages to their source files and resolves commit metadata.
 */
final class GitMetaPageService
{
    /** @var \WeakMap<BaseMarkdownPage, GitCommitInfo|null> */
    private \WeakMap $pageCache;

    /** @var list<class-string<BaseMarkdownPage>> */
    private readonly array $pageTypes;

    /**
     * @param  list<class-string<BaseMarkdownPage>>  $pageTypes
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly GitMetaService $gitService,
        private readonly RepositoryUrl $repositoryUrl,
        array $pageTypes,
    ) {
        $validatedPageTypes = [];

        foreach ($pageTypes as $pageType) {
            if (! is_string($pageType) || ! is_a($pageType, BaseMarkdownPage::class, true)) {
                throw new InvalidArgumentException(
                    'Configured page types must extend ' . BaseMarkdownPage::class . '.',
                );
            }

            $validatedPageTypes[] = $pageType;
        }

        $this->pageTypes = $validatedPageTypes;
        $this->pageCache = new \WeakMap();
    }

    /**
     * Resolve Git metadata for a page.
     *
     * Returns null if the page is not eligible or has no Git metadata.
     */
    public function forPage(?HydePage $page): ?GitCommitInfo
    {
        if (!$page instanceof BaseMarkdownPage || !$this->isEligible($page)) {
            return null;
        }

        if ($this->pageCache->offsetExists($page)) {
            return $this->pageCache[$page];
        }

        $sourcePath = $page->getSourcePath();
        $repositoryRoot = $this->findRepositoryRoot($sourcePath);

        if ($repositoryRoot === null) {
            $this->pageCache[$page] = null;
            return null;
        }

        // Calculate relative path from repository root to source file
        $relativePath = $this->relativePath($repositoryRoot, $sourcePath);

        if ($relativePath === null) {
            $this->pageCache[$page] = null;
            return null;
        }

        $result = $this->gitService->resolve($repositoryRoot, $relativePath);
        $this->pageCache[$page] = $result;

        return $result;
    }

    /**
     * Get the commit URL for a page's latest commit, or null if not available.
     */
    public function commitUrl(?HydePage $page): ?string
    {
        $info = $this->forPage($page);

        if ($info === null) {
            return null;
        }

        return $this->repositoryUrl->commitUrl($info->fullSha);
    }

    /**
     * Get the short SHA for a page, or null if not available.
     */
    public function shortSha(?HydePage $page): ?string
    {
        $info = $this->forPage($page);

        return $info?->shortSha;
    }

    /**
     * Get the full SHA for a page, or null if not available.
     */
    public function fullSha(?HydePage $page): ?string
    {
        $info = $this->forPage($page);

        return $info?->fullSha;
    }

    /**
     * Get the commit timestamp for a page, or null if not available.
     */
    public function timestamp(?HydePage $page): ?int
    {
        $info = $this->forPage($page);

        return $info?->timestamp;
    }

    /**
     * Try to find the Git repository root by walking up from the given path.
     */
    private function findRepositoryRoot(string $filePath): ?string
    {
        $directory = is_file($filePath) ? dirname($filePath) : $filePath;
        $current = $directory;

        while (true) {
            if (is_dir($current . '/.git')) {
                return $current;
            }

            $parent = dirname($current);

            if ($parent === $current) {
                // Reached filesystem root
                return null;
            }

            $current = $parent;
        }
    }

    /**
     * Calculate the relative path from the repository root to the source file.
     */
    private function relativePath(string $repositoryRoot, string $filePath): ?string
    {
        $repositoryRoot = rtrim($repositoryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with($filePath, $repositoryRoot)) {
            return substr($filePath, strlen($repositoryRoot));
        }

        return null;
    }

    /**
     * Check if a page type is eligible for Git metadata.
     */
    private function isEligible(BaseMarkdownPage $page): bool
    {
        foreach ($this->pageTypes as $pageType) {
            if ($page instanceof $pageType) {
                return true;
            }
        }

        return false;
    }
}
