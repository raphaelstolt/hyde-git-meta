# PROMPTS.md — hyde-git-meta

## Mission

Build `hyde-git-meta`, a small, reusable HydePHP extension that exposes Git provenance for individual content pages to Blade templates at static-site build time. The primary use case is displaying a clickable abbreviated commit hash in a blog post's `div.post-meta`, linking to the commit that last modified that post.

**Repository description:** A HydePHP extension for exposing Git commit metadata for pages and posts at build time.

## Agent instructions

- Work through the phases below in order. Mark completed checkboxes and document important decisions.
- Inspect the current HydePHP release and its documented extension points before selecting integration APIs. Do not invent HydePHP classes, hooks, configuration keys, or page methods.
- Prefer small, testable PHP services, clear interfaces, and minimal runtime dependencies.
- Keep the generated website static: no browser-side Git calls, external API requirements, or PHP runtime on the hosting server.
- Use Git CLI through a safely constructed process invocation (for example Symfony Process), **never** interpolate untrusted file paths into shell commands.
- Verify behavior against actual Git repositories in tests; use mocks only where they add value.
- Follow the project's supported PHP versions and HydePHP compatibility matrix; do not assume a PHP baseline without checking.

## Phase 1 — Research and scaffolding

- [x] Inspect HydePHP's current plugin/package conventions, service providers, page models, Markdown source paths, routing, Blade integration, and build lifecycle.
- [x] Identify the earliest reliable point at which page source paths are known and the metadata can be computed, and how templates can access it.
- [x] Check whether HydePHP already offers Git metadata or a competing extension; document differentiation.
- [x] Establish supported HydePHP and PHP versions; record compatibility in README and Composer constraints.
- [x] Create a Composer library package (`hyde-git-meta`) with PSR-4 autoloading and appropriate licensing.
- [x] Add coding standards, static analysis where practical, automated tests, and GitHub Actions CI.
- [x] Ensure package installation does not run Git commands automatically during Composer install.

**Acceptance criteria:** The extension installs in a minimal HydePHP test project and has a documented, verified registration mechanism.

## Phase 2 — Git metadata service

Implement a framework-independent service that accepts an absolute repository root and a validated page source file path.

- [x] Discover the repository root safely, including projects deployed from subdirectories.
- [x] Resolve and validate source paths against the repository working tree; handle spaces, Unicode, and filenames beginning with `-`.
- [x] Retrieve the most recent commit affecting a source file, e.g. via `git log -1 --format=... -- <path>` using argument-vector execution.
- [x] Return a structured, immutable result containing at least full SHA, abbreviated SHA, commit timestamp, and optional author/subject.
- [x] Make the abbreviation length configurable (default: 8); retain the full SHA for commit URLs and machine-readable attributes.
- [x] Ensure full SHA values are validated before rendering URLs.
- [x] Distinguish untracked files, tracked files without usable history, Git-unavailable environments, and command failures.
- [x] Provide a documented non-fatal fallback by default; allow strict mode for CI.
- [x] Avoid leaking local paths, Git errors, or other sensitive information into public HTML.
- [x] Cache results within one build to avoid repeated Git subprocesses for the same source file.

**Acceptance criteria:** Given a test repository with multiple posts and commits, each post receives the commit that last changed its own source, not merely the current `HEAD`.

## Phase 3 — HydePHP integration

- [x] Register the extension using the supported HydePHP integration API.
- [x] Map eligible HydePHP posts/pages to their original tracked Markdown source files.
- [x] Make metadata available to Blade in an ergonomic, documented way; choose a stable API after verifying HydePHP's lifecycle.
- [x] Support blog posts first; optionally support documentation and other Markdown pages where source mapping is reliable.
- [x] Compute metadata at build time, not per visitor request.
- [x] Ensure missing metadata never breaks page rendering in the default mode.
- [x] Avoid overwriting user-defined front matter or existing page metadata fields.
- [x] Confirm behavior when the HydePHP project lives in a subdirectory of a Git repository.

**Acceptance criteria:** Two posts last changed in different commits render different, correct hashes in their generated HTML.

## Phase 4 — Configuration and display

Propose a configuration shape, then adapt it to HydePHP conventions after research. Suggested settings:

```php
return [
    'enabled' => true,
    'short_hash_length' => 8,
    'strict' => false,
    'content_types' => ['posts'],
    'repository_url' => null, // explicit URL preferred
    'show_author' => false,
    'show_date' => false,
];
```

- [x] Support explicit repository URL configuration; optionally detect an `origin` remote if it is a recognized HTTPS or SSH GitHub-style URL.
- [x] Normalize supported remote formats safely; do not assume every remote is GitHub.
- [x] Construct commit URLs using the **full** SHA, not the abbreviated display SHA.
- [x] If no valid repository URL is known, render plain text or omit the link rather than generating a broken URL.
- [x] Provide a Blade usage example for `div.post-meta`, with an accessible link label and `<code>` or monospace styling.
- [x] Keep rendering opt-in; do not silently alter every post template.
- [x] Escape all rendered metadata appropriately.
- [x] Document how to display commit date separately from the publication/update date.

**Acceptance criteria:** A configured site displays an eight-character hash linking to the correct commit; an unconfigured repository URL never yields an invalid link.

## Phase 5 — Testing and CI

- [x] Unit-test Git output parsing, SHA validation, abbreviation, repository URL normalization, and failure handling.
- [x] Integration-test against a temporary Git repository with deterministic commits, timestamps, and at least two Markdown files.
- [x] Test that modifying one post changes only that post's latest-change metadata.
- [x] Test untracked posts, missing Git binary, non-repository directories, invalid paths, filenames with spaces, and empty repositories.
- [x] Test the source path resolution for nested content and a project located within a parent Git repository.
- [x] Test fallback behavior and strict-mode failures.
- [x] Test escaping and link generation, including SSH remotes and unsupported hosts.
- [ ] Build a minimal HydePHP fixture and assert the generated HTML contains correct per-post hashes and URLs.
- [x] Document shallow-clone behavior: `actions/checkout` with `fetch-depth: 0` is recommended for complete per-file history; consider and test limited-history fallbacks.
- [x] Run tests and static checks in CI across the declared support matrix.

**Acceptance criteria:** CI passes; a real HydePHP fixture produces correct metadata from a multi-commit repository.

## Phase 6 — Documentation and release

- [x] Write a README explaining the problem, installation, configuration, and template integration.
- [x] The documentation documents should have the same style and tone of `./hyde-llms-txt` and `./hyde-reading-time`.
- [x] Show an example metadata row: `Nov 19, 2026 · 5 min read · a3f91c2d` (illustrative hash).
- [x] Explain that the displayed hash identifies the latest Git commit touching the source file, **not** necessarily the site's deployment commit or original publication commit.
- [x] Explain limitations of shallow clones, squashed history, uncommitted edits, and generated or non-Git-backed pages.
- [x] Provide GitHub Actions examples using `actions/checkout` and `fetch-depth: 0`.
- [x] Include security/privacy notes: avoid exposing commit author details by default; commit subjects may contain private information.
- [x] Add CHANGELOG, contributing instructions, release workflow, and semantic versioning guidance.
- [ ] Publish a first tagged release only after testing installation in a separate HydePHP site.

**Acceptance criteria:** A new user can install, configure, and display a commit link without inspecting the source code.

## MVP definition of done

- [x] Installable HydePHP extension with documented version compatibility.
- [x] Correct latest-source-commit lookup for individual Markdown blog posts.
- [x] Full SHA retained; configurable short display SHA (default 8 characters).
- [x] Metadata accessible to Blade at build time.
- [x] Optional GitHub commit link via configured repository URL.
- [x] Graceful default behavior when Git data is unavailable.
- [ ] Tests using real temporary Git repositories and an actual HydePHP build fixture.
- [x] README, configuration examples, and passing CI.

## Post-MVP backlog — do not block v0.1.0

- [ ] First-published commit metadata and original publication date.
- [ ] Last-modified date and `datetime` markup.
- [ ] Commit author and subject (explicitly opt-in).
- [ ] GitLab, Forgejo, and other repository hosts.
- [ ] Per-page history links and "Edit this page" links.
- [ ] Support for non-Markdown pages where source provenance can be established.
- [ ] Git blame-based provenance for specific sections (only if compelling).
- [ ] A `hyde:git-meta:check` diagnostic command if needed.

## Suggested implementation order for the coding agent

1. Research and document the actual HydePHP APIs and compatibility constraints.
2. Build and test the framework-independent Git metadata resolver.
3. Integrate the resolver with a minimal HydePHP fixture.
4. Expose a safe Blade API and optional commit links.
5. Add configuration, failure handling, and comprehensive tests.
6. Document, verify a real site build, and prepare the initial release.

**Non-goals:** GitHub API access, client-side tracking, analytics, automatic changes to blog themes, and assuming `HEAD` is the last change to every post.
