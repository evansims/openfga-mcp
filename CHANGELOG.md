# Changelog

## [Unreleased]

## [2.0.0] - 2025-08-09

### Added

- The server now supports two varied configurations: online and offline mode.
  - Online mode requires configuration, so that the server can connect to an OpenFGA instance.
  - Offline mode still enables all authorization model designing and troubleshooting tools.
- The server now includes all documentation for OpenFGA, it's official SDKs, and the community-contributed PHP and Laravel SDKs.
- The server has new `tool` endpoints that allow agents to interactively query this documentation. This feature is supported in offline mode.

### Changed

- As a safety measure, live mode now defaults to disabling write operations. You must specifically enable the `OPENFGA_MCP_API_WRITEABLE` environment variable to enable operations such as creating models, or creating or deleting stores or tuples.
- The `prompt` endpoints have been greatly enhanced with new authorization model design best practices and patterns.

## [1.0.0] - 2025-07-13

### Added

- Initial stable release
- MCP resources for reading OpenFGA data (stores, models, relationships)
- MCP resource templates for dynamic resource generation
- MCP prompts for authorization model design and query building
- Completion providers for enhanced developer experience
