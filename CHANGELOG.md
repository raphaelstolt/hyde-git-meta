# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Framework-independent Git metadata service with safe argument-vector Git CLI execution.
- Immutable `GitCommitInfo` value object with full SHA, short SHA, timestamp, author, and subject.
- Repository URL normalizer supporting GitHub, GitLab, Bitbucket, and SSH URLs.
- HydePHP integration via service provider with three Blade directives: `@gitCommitHash`, `@gitCommitUrl`, `@gitCommitLink`.
- Configurable page types, short hash length, and strict mode.
- Unit tests for value objects and URL normalization.
- Integration tests with real temporary Git repositories covering multiple commits, nested paths, and filenames with spaces.
- GitHub Actions CI with PHP 8.2, 8.3, and 8.4 matrix.
