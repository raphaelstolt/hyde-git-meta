<?php

declare(strict_types=1);

namespace HydeGitMeta\Tests\Unit;

use HydeGitMeta\RepositoryUrl;
use PHPUnit\Framework\TestCase;

final class RepositoryUrlTest extends TestCase
{
    public function testReturnsNullForNullUrl(): void
    {
        $repo = new RepositoryUrl(null);
        $this->assertNull($repo->get());
    }

    public function testReturnsNullForEmptyUrl(): void
    {
        $repo = new RepositoryUrl('');
        $this->assertNull($repo->get());
    }

    public function testNormalizesHttpsUrl(): void
    {
        $repo = new RepositoryUrl('https://github.com/user/repo');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }

    public function testRemovesTrailingSlash(): void
    {
        $repo = new RepositoryUrl('https://github.com/user/repo/');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }

    public function testRemovesGitSuffix(): void
    {
        $repo = new RepositoryUrl('https://github.com/user/repo.git');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }

    public function testConvertsHttpToHttps(): void
    {
        $repo = new RepositoryUrl('http://github.com/user/repo');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }

    public function testNormalizesSshUrl(): void
    {
        $repo = new RepositoryUrl('git@github.com:user/repo.git');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }

    public function testNormalizesSshUrlWithoutGitSuffix(): void
    {
        $repo = new RepositoryUrl('git@github.com:user/repo');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }

    public function testReturnsNullForUnsupportedFormat(): void
    {
        $repo = new RepositoryUrl('ftp://example.com/repo');
        $this->assertNull($repo->get());
    }

    public function testBuildsGithubCommitUrl(): void
    {
        $repo = new RepositoryUrl('https://github.com/user/repo');
        $sha = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->assertSame(
            'https://github.com/user/repo/commit/' . $sha,
            $repo->commitUrl($sha),
        );
    }

    public function testBuildsGitlabCommitUrl(): void
    {
        $repo = new RepositoryUrl('https://gitlab.com/user/repo');
        $sha = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->assertSame(
            'https://gitlab.com/user/repo/-/commit/' . $sha,
            $repo->commitUrl($sha),
        );
    }

    public function testBuildsBitbucketCommitUrl(): void
    {
        $repo = new RepositoryUrl('https://bitbucket.org/user/repo');
        $sha = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->assertSame(
            'https://bitbucket.org/user/repo/commits/' . $sha,
            $repo->commitUrl($sha),
        );
    }

    public function testBuildsGenericCommitUrl(): void
    {
        $repo = new RepositoryUrl('https://gitea.example.com/user/repo');
        $sha = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->assertSame(
            'https://gitea.example.com/user/repo/commit/' . $sha,
            $repo->commitUrl($sha),
        );
    }

    public function testReturnsNullCommitUrlForNullRepository(): void
    {
        $repo = new RepositoryUrl(null);
        $this->assertNull($repo->commitUrl('abcdef1234567890abcdef1234567890abcdef12'));
    }

    public function testReturnsNullCommitUrlForInvalidSha(): void
    {
        $repo = new RepositoryUrl('https://github.com/user/repo');
        $this->assertNull($repo->commitUrl('invalid'));
    }

    public function testSshUrlProducesCommitUrl(): void
    {
        $repo = new RepositoryUrl('git@github.com:user/repo.git');
        $sha = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->assertSame(
            'https://github.com/user/repo/commit/' . $sha,
            $repo->commitUrl($sha),
        );
    }

    public function testTrimsWhitespace(): void
    {
        $repo = new RepositoryUrl('  https://github.com/user/repo  ');
        $this->assertSame('https://github.com/user/repo', $repo->get());
    }
}
