<?php

declare(strict_types=1);

namespace HydeGitMeta;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class GitMetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/git-meta.php', 'git-meta');

        $this->app->singleton(
            GitMetaService::class,
            static function (Application $app): GitMetaService {
                /** @var Repository $config */
                $config = $app->make('config');

                return new GitMetaService(
                    shortHashLength: $config->get('git-meta.short_hash_length', 8),
                    strict: $config->get('git-meta.strict', false),
                );
            },
        );

        $this->app->singleton(
            RepositoryUrl::class,
            static function (Application $app): RepositoryUrl {
                /** @var Repository $config */
                $config = $app->make('config');

                return new RepositoryUrl(
                    $config->get('git-meta.repository_url'),
                );
            },
        );

        $this->app->singleton(
            GitMetaPageService::class,
            static function (Application $app): GitMetaPageService {
                /** @var Repository $config */
                $config = $app->make('config');

                return new GitMetaPageService(
                    gitService: $app->make(GitMetaService::class),
                    repositoryUrl: $app->make(RepositoryUrl::class),
                    pageTypes: $config->get('git-meta.content_types', []),
                );
            },
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/git-meta.php' => $this->app->configPath('git-meta.php'),
        ], 'git-meta-config');

        // Blade directive: @gitCommitHash - outputs the short SHA
        // @mago-ignore(prefer-arrow-function) Returns complex string literal with embedded PHP
        Blade::directive('gitCommitHash', static function (): string {
            return '<?php echo e(app(\HydeGitMeta\GitMetaPageService::class)->shortSha(\Hyde\Foundation\HydeKernel::getInstance()->currentPage()) ?? \'\'); ?>';
        });

        // Blade directive: @gitCommitUrl - outputs the full commit URL
        // @mago-ignore(prefer-arrow-function) Returns complex string literal with embedded PHP
        Blade::directive('gitCommitUrl', static function (): string {
            return '<?php echo e(app(\HydeGitMeta\GitMetaPageService::class)->commitUrl(\Hyde\Foundation\HydeKernel::getInstance()->currentPage()) ?? \'\'); ?>';
        });

        // Blade directive: @gitCommitLink - outputs a linked short SHA, or plain text if no URL
        // @mago-ignore(prefer-arrow-function) Returns complex multi-line string literal
        Blade::directive('gitCommitLink', static function (): string {
            return '<?php
                $gitService = app(\HydeGitMeta\GitMetaPageService::class);
                $page = \Hyde\Foundation\HydeKernel::getInstance()->currentPage();
                $shortSha = $gitService->shortSha($page);
                $commitUrl = $gitService->commitUrl($page);
                if ($shortSha !== null) {
                    if ($commitUrl !== null) {
                        echo \'<a href="\' . e($commitUrl) . \'" target="_blank" rel="noopener noreferrer" class="font-mono text-xs text-gray-500 hover:text-gray-700">\' . e($shortSha) . \'</a>\';
                    } else {
                        echo \'<code class="text-xs text-gray-500">\' . e($shortSha) . \'</code>\';
                    }
                }
            ?>';
        });
    }
}
