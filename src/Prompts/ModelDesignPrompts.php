<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Prompts;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\{AccessPattern, ComplexityLevel};
use PhpMcp\Server\Attributes\{CompletionProvider, McpPrompt};

final readonly class ModelDesignPrompts extends AbstractPrompts
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private ClientInterface $client,
    ) {
        // Client is injected for dependency injection but may be used in future implementations
    }

    /**
     * Generate a prompt to convert traditional RBAC (Role-Based Access Control) to OpenFGA's ReBAC model.
     *
     * @param  string                            $roleDescription Description of existing roles and permissions
     * @param  string                            $migrationScope  Scope of migration (additive, gradual, full)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'convert_rbac_to_rebac')]
    public function convertRbacToRebac(
        string $roleDescription,
        #[CompletionProvider(values: ['additive', 'gradual', 'full', 'backwards-compatible'])]
        string $migrationScope = 'additive',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Convert the following RBAC (Role-Based Access Control) system to OpenFGA's ReBAC (Relationship-Based Access Control) model. OpenFGA primarily champions ReBAC while also supporting RBAC and ABAC use cases.

**Existing RBAC System:**
{$roleDescription}

**Migration Scope:** {$migrationScope}

Please provide:
1. **RBAC Analysis**: Break down the existing roles, permissions, and hierarchies
2. **ReBAC Mapping**: Map roles to types and relationships in OpenFGA
3. **OpenFGA Model**: Complete DSL model (schema 1.1) that replaces or augments the RBAC system
4. **Migration Strategy**: Following the {$migrationScope} approach:
   - Additive: Introduce custom roles alongside existing static roles
   - Gradual: Move permissions one at a time to custom roles
   - Backwards-compatible: Maintain existing static role behavior during transition
5. **Relationship Tuples**: Show how existing role assignments become relationship tuples
6. **Custom Roles**: If applicable, show how to implement user-defined roles

Consider:
- Preserving existing access patterns during migration
- Maintaining security boundaries
- Using OpenFGA patterns: direct relationships, concentric (or), indirect (X from Y)
- The separation between static authorization model and dynamic relationship tuples
- Performance implications of the new model";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to design an OpenFGA authorization model for a specific domain.
     *
     * @param  string                            $domain        The application domain (e.g., 'document management', 'e-commerce', 'healthcare')
     * @param  string                            $accessPattern The access control pattern to focus on (hierarchical, flat, hybrid)
     * @param  string                            $complexity    The complexity level (simple, moderate, complex)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'design_model_for_domain')]
    public function designModelForDomain(
        string $domain,
        #[CompletionProvider(enum: AccessPattern::class)]
        string $accessPattern = 'hierarchical',
        #[CompletionProvider(enum: ComplexityLevel::class)]
        string $complexity = 'moderate',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Design an OpenFGA authorization model for a {$domain} application using a {$accessPattern} access control pattern at {$complexity} complexity level.

Please provide:
1. **Type Definitions**: Define the main entity types (users, resources, groups, etc.)
2. **Relations**: Specify the relationships between types with proper inheritance
3. **OpenFGA DSL**: Write the complete model in OpenFGA's Domain Specific Language (schema 1.1)
4. **Example Relationship Tuples**: Show 3-5 example tuples that would be created
5. **Permissions**: Define can_* permissions following OpenFGA best practices
6. **Security Considerations**: Highlight important security aspects of this design

Focus on:
- Using OpenFGA's core relationship patterns (direct, concentric, indirect 'X from Y')
- Scalability and maintainability
- Clear separation between static model and dynamic tuples
- Efficient query patterns
- Real-world use cases for {$domain}

Consider these best practices:
- Use meaningful type and relation names
- Implement proper inheritance using 'or' and 'X from Y' patterns
- Avoid circular dependencies
- Design for query performance
- Always define permissions in the authorization model using can_* relations";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to model hierarchical relationships and inheritance patterns.
     *
     * @param  string                            $hierarchyType      Type of hierarchy (organizational, resource, folder, team)
     * @param  string                            $inheritancePattern How inheritance should work (parent-to-child, selective, conditional)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'model_hierarchical_relationships')]
    public function modelHierarchicalRelationships(
        #[CompletionProvider(values: ['organizational', 'resource', 'folder', 'team', 'group', 'department'])]
        string $hierarchyType,
        #[CompletionProvider(values: ['parent-to-child', 'selective', 'conditional', 'with-usersets'])]
        string $inheritancePattern = 'parent-to-child',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Design hierarchical relationships and inheritance patterns for a {$hierarchyType} hierarchy using {$inheritancePattern} inheritance in OpenFGA.

**Requirements:**
- Hierarchy Type: {$hierarchyType}
- Inheritance Pattern: {$inheritancePattern}

Please provide:
1. **Hierarchy Design**: Structure of the {$hierarchyType} hierarchy
2. **Inheritance Rules**: How permissions flow through the hierarchy using 'X from Y' syntax
3. **OpenFGA Model**: DSL implementation with proper relation definitions
4. **Permission Propagation**: Examples of how permissions inherit through the hierarchy
5. **Edge Cases**: Handle scenarios like deeply nested hierarchies and permission boundaries
6. **Performance Considerations**: Query optimization for hierarchical lookups

Focus on:
- Using the 'X from Y' pattern for scalable hierarchical access
- Clear inheritance patterns that are easy to understand
- Avoiding permission escalation vulnerabilities
- Efficient query paths for authorization checks
- Proper use of concentric relationships (or) for permission inheritance

Use OpenFGA's standard patterns:
- **Indirect Relationships ('X from Y')**: For hierarchical inheritance
- **Concentric Relationships**: Using 'or' for nested permissions (e.g., editors are viewers)
- **Usersets**: For group-based access control
- **Conditions**: For dynamic, contextual permissions with CEL";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to optimize and improve an existing OpenFGA authorization model.
     *
     * @param  string                            $currentModel     The existing OpenFGA DSL model to optimize
     * @param  string                            $optimizationGoal Primary optimization goal (performance, maintainability, security, flexibility)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'optimize_model_structure')]
    public function optimizeModelStructure(
        string $currentModel,
        #[CompletionProvider(values: ['performance', 'maintainability', 'security', 'flexibility', 'scalability', 'readability'])]
        string $optimizationGoal = 'performance',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Analyze and optimize the following OpenFGA authorization model with a focus on {$optimizationGoal}:

**Current Model:**
```
{$currentModel}
```

**Optimization Goal:** {$optimizationGoal}

Please provide:
1. **Current Analysis**: Identify strengths and weaknesses of the existing model
2. **Optimization Opportunities**: Specific areas for improvement targeting {$optimizationGoal}
3. **Improved Model**: Optimized OpenFGA DSL with explanations of changes
4. **Performance Impact**: Expected improvements in query performance and scalability
5. **Migration Plan**: Safe steps to transition from current to optimized model
6. **Validation Strategy**: How to verify the optimization maintains existing functionality

Focus on:
- Reducing query complexity and authorization check latency
- Simplifying relation definitions while maintaining functionality
- Eliminating redundant or inefficient patterns
- Improving readability and maintainability
- Ensuring security is not compromised by optimizations

Consider these optimization techniques:
- Relation consolidation and simplification
- Query path optimization using efficient 'X from Y' patterns
- Type hierarchy restructuring
- Removal of unnecessary indirection
- Proper use of concentric relationships to reduce tuple count
- Leveraging usersets for group-based access";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }
}
