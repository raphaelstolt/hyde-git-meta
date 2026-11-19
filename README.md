# HydePHP Git Meta

Display the Git commit that last modified each blog post, documentation page, or Markdown page in your HydePHP site.

## Why?

HydePHP does not track which Git commit last changed a content file. This extension resolves the most recent commit for
each Markdown source file at build time and exposes it to Blade templates, so you can show a clickable abbreviated commit
hash in a post's metadata row.

The displayed hash identifies the latest Git commit touching the source file, not necessarily the site's deployment commit
or the original publication commit.

## Requirements

- PHP `^8.2`.
- Hyde Framework `^2.0.3`.
- Symfony Process `^6.4|^7.0`.

The integration is verified against Hyde Framework `v2.0.3`. The Composer constraint is authoritative; newer Hyde 2.x
releases will be supported only after testing confirms compatibility.

Before `1.0.0`, breaking public API changes may be made in a minor release and will be called out in the changelog.

## Installation

```bash
composer require stolt/hyde-git-meta
```

Composer package discovery registers the service provider automatically; no manual provider registration is needed.

## Usage

Add the opt-in `@gitCommitLink` directive to a layout or Blade view where the commit hash should appear:

```blade
<div class="post-meta">
    Nov 19, 2026 &middot; 5 min read &middot; @gitCommitLink
</div>
```

The directive renders a linked short SHA when a repository URL is configured, or plain text otherwise. It renders nothing
for unsupported pages or when Git data is unavailable.

### Directives

Three Blade directives are available:

| Directive | Output |
|---|---|
| `@gitCommitHash` | The abbreviated commit hash (e.g. `a3f91c2d`) |
| `@gitCommitUrl` | The full commit URL (e.g. `https://github.com/user/repo/commit/...`) |
| `@gitCommitLink` | A styled link or plain hash, depending on configuration |

### Access the result

For programmatic access, `GitMetaPageService::forPage()` returns a `GitCommitInfo` object with properties `fullSha`,
`shortSha`, `timestamp`, `authorName`, `authorEmail`, and `subject`, or `null` for excluded pages:

```blade
@php($gitMeta = app(\HydeGitMeta\GitMetaPageService::class)->forPage($page))

@if ($gitMeta !== null)
    <code>{{ $gitMeta->shortSha }}</code>
@endif
```

Hyde shares the current page as `$page` in its Blade views.

## Configuration

Defaults are merged automatically, so publishing a configuration file is optional. To publish it in a Hyde site, run:

```sh
php hyde vendor:publish --tag=git-meta-config
```

The configuration is `config/git-meta.php`:

```php
<?php

use Hyde\Pages\MarkdownPost;

return [
    'enabled' => true,
    'short_hash_length' => 8,
    'strict' => false,
    'content_types' => [
        MarkdownPost::class,
    ],
    'repository_url' => null,
    'show_author' => false,
    'show_date' => false,
];
```

`short_hash_length` controls the displayed hash length (default 8). `strict` throws on Git failures instead of returning
null — useful for CI. `content_types` accepts Hyde Markdown page classes; only listed types receive metadata.
`repository_url` is the HTTPS or SSH URL of the repository; if set, commit links are generated. Supported hosts include
GitHub, GitLab, and Bitbucket.

### Repository URL

Set `repository_url` to an HTTPS or SSH URL:

```php
'repository_url' => 'https://github.com/user/repo',
```

SSH URLs are normalized to HTTPS automatically:

```php
'repository_url' => 'git@github.com:user/repo.git',
```

If no URL is configured, the hash is displayed as plain text without a link.

## Git metadata and limitations

- Metadata is computed at build time from the Git CLI; no browser-side calls or PHP runtime are required on the hosting
  server.
- Each file's last-modifying commit is resolved independently. Two posts last changed in different commits will show
  different hashes.
- Results are cached within a single build to avoid repeated Git subprocesses.
- Untracked files, empty repositories, and non-Git environments return `null` and render nothing.
- The extension walks up from each source file to find the `.git` directory, so it works when the HydePHP project lives
  in a subdirectory of a larger repository.
- Shallow clones may lack per-file history. Use `actions/checkout` with `fetch-depth: 0` in CI for complete history.
- Squashed history, rebased branches, and uncommitted edits will not reflect the original commit timeline.
- Commit author details are resolved but not displayed by default. Commit subjects may contain private information.

## Security and privacy

- Git commands are executed via argument-vector invocation; untrusted file paths are never interpolated into shell
  commands.
- Local paths and Git errors are not leaked into public HTML.
- Author details are not displayed by default. Enable `show_author` explicitly if needed.
- Commit subjects may contain sensitive information. Review your commit messages before exposing them.

## AI disclaimer

This extension is developed with the assistance of Artificial Intelligence (AI). AI tools are used throughout the
development process, including code generation, documentation, testing, and maintenance.

While reasonable care is taken to review and validate AI-generated contributions, AI-generated code is not inherently
correct, secure, or free of defects. All contributions remain subject to human review and project quality standards.

As with any software, use this package at your own risk. No guarantee is made regarding its fitness for a particular
purpose, and the maintainers assume no liability for damages resulting from its use.

## Contributing

If you're considering contributing to this project, have a look at this repository's [CONTRIBUTING.md](CONTRIBUTING.md)
for more advice.

## License

This extension is licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in the [CHANGELOG.md](CHANGELOG.md).
