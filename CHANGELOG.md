# Changelog

## [Unreleased]

### Added

- SDK documentation system exposing all OpenFGA SDKs as MCP resources
- Advanced documentation search tools with SDK filtering and code example extraction
- Documentation resources accessible via `openfga://docs` URIs
- Smart chunking for large documentation files
- AuthoringGuidancePrompts for model authoring guidance
- Offline mode support for planning and code generation without OpenFGA instance
- Standalone documentation sync tool for fetching OpenFGA docs from GitHub
- Composer commands: `docs:sync`, `docs:sync:quiet`, `docs:sync:source`

### Changed

- **BREAKING**: Write operations now require `OPENFGA_MCP_API_WRITEABLE=true` (previously opt-out via OPENFGA_MCP_API_READONLY)
- Enhanced authorization model design prompts
- Aligned prompt patterns with OpenFGA documentation
- Docker image defaults to offline mode
- Improved completion providers with offline mode fallbacks
- Added comprehensive integration tests for documentation system
- Updated README with SDK documentation features

### Fixed

- RelationCompletionProvider iteration over TypeDefinitionRelations
- ObjectCompletionProvider contextual completions based on object type prefixes
- Integration tests write operations in Docker environment
- Fuzzing test tuple key validation error handling
- Documentation sync tool `.mdx` file handling
- Changelog update workflow converted to status check

## [1.0.0] - 2025-07-13

### Added

- Initial stable release
- MCP resources for reading OpenFGA data (stores, models, relationships)
- MCP resource templates for dynamic resource generation
- MCP prompts for authorization model design and query building
- Completion providers for enhanced developer experience
