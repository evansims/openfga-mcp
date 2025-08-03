# Changelog

## [Unreleased]

### Added

- feat: new AuthoringGuidancePrompts class providing comprehensive OpenFGA model authoring guidance
- feat: offline mode support - server now works without OpenFGA instance for planning and code generation
- feat: comprehensive error message guidance to configure OpenFGA when administrative features are invoked
- feat: standalone documentation sync tool for fetching OpenFGA documentation from GitHub repositories
- feat: composer commands for syncing documentation (`docs:sync`, `docs:sync:quiet`, `docs:sync:source`)

### Changed

- **BREAKING**: write operations now require explicit opt-in via `OPENFGA_MCP_API_WRITEABLE=true` environment variable (previously opt-out via OPENFGA_MCP_API_READONLY)
- feat: enhanced authorization model design prompts for improved context
- refactor: aligned ModelDesignPrompts and SecurityGuidancePrompts with OpenFGA documentation patterns
- refactor: Docker image now defaults to offline mode, with clear documentation for connecting to OpenFGA instances
- refactor: convert changelog update workflow to status check instead of auto-modification
- refactor: improved completion providers with better fallback patterns for offline mode

### Fixed

- fix: RelationCompletionProvider now correctly iterates TypeDefinitionRelations collections
- fix: ObjectCompletionProvider provides contextual completions based on object type prefixes
- fix: integration tests now properly enable write operations in Docker environment
- fix: fuzzing test for tuple key validation to handle expected errors correctly
- fix: removed unused DelegationType enum causing test failures
- fix: documentation sync tool now properly handles `.mdx` files from OpenFGA documentation repository

## [1.0.0] - 2025-07-13

### Added

- feat: initial stable release! 🥳
- feat: MCP resources for reading OpenFGA data (stores, models, relationships)
- feat: MCP resource templates for dynamic resource generation
- feat: MCP prompts for authorization model design and query building
- feat: completion providers for enhanced developer experience
