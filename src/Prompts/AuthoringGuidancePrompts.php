<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Prompts;

use OpenFGA\ClientInterface;
use PhpMcp\Server\Attributes\{CompletionProvider, McpPrompt};

final readonly class AuthoringGuidancePrompts extends AbstractPrompts
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private ClientInterface $client,
    ) {
        // Client is injected for dependency injection but may be used in future implementations
    }

    /**
     * Generate a prompt for step-by-step model creation guidance.
     *
     * @param  string                            $requirements Description of the authorization requirements
     * @param  string                            $complexity   The expected complexity level
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'create_model_step_by_step')]
    public function createModelStepByStep(
        string $requirements,
        #[CompletionProvider(values: ['simple', 'moderate', 'complex', 'enterprise'])]
        string $complexity = 'moderate',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $guidance = $this->getStepByStepGuidance();

        $prompt = "Guide through creating an OpenFGA authorization model step-by-step:

**Requirements:**
{$requirements}

**Complexity Level:** {$complexity}

{$guidance}

Please provide:
1. **Step 1: Identify Types** - List all entity types needed
2. **Step 2: Define Relations** - Map out relationships for each type
3. **Step 3: Model Relationships** - Create the DSL with direct, concentric, and indirect relationships
4. **Step 4: Add Permissions** - Define can_* permissions appropriately
5. **Step 5: Test Coverage** - Create comprehensive .fga.yaml test cases
6. **Step 6: Optimization** - Review and optimize the model

For each step, provide:
- Specific guidance for the given requirements
- Example DSL snippets
- Rationale for design decisions
- Common mistakes to avoid";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt for relationship pattern guidance.
     *
     * @param  string                            $scenario    The specific scenario requiring relationship patterns
     * @param  string                            $patternType The type of relationship pattern needed
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'design_relationship_patterns')]
    public function designRelationshipPatterns(
        string $scenario,
        #[CompletionProvider(values: ['direct', 'concentric', 'indirect', 'conditional', 'usersets', 'mixed', 'advanced'])]
        string $patternType = 'mixed',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $guidance = $this->getRelationshipPatternsGuidance();

        $prompt = "Design relationship patterns for OpenFGA:

**Scenario:**
{$scenario}

**Pattern Type Focus:** {$patternType}

{$guidance}

Please provide:
1. **Pattern Selection** - Which relationship patterns best fit this scenario
2. **DSL Implementation** - Complete model using the selected patterns
3. **Pattern Explanation** - Why each pattern was chosen
4. **Relationship Examples** - Sample tuples demonstrating the patterns
5. **Query Patterns** - How authorization checks will work
6. **Trade-offs** - Performance vs flexibility considerations

Focus on:
- Clear, maintainable relationship definitions
- Efficient authorization checking
- Scalability of the chosen patterns
- Security implications
- Future extensibility";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate comprehensive guidance for authoring OpenFGA authorization models.
     *
     * @param  string                            $useCase   The specific use case for the model (e.g., 'document management', 'multi-tenant SaaS', 'e-commerce')
     * @param  string                            $focusArea The area to focus on (e.g., 'getting started', 'relationships', 'testing', 'custom roles')
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'guide_model_authoring')]
    public function guideModelAuthoring(
        string $useCase = 'general',
        #[CompletionProvider(values: ['getting_started', 'relationships', 'testing', 'custom_roles', 'hierarchies', 'conditions', 'migration', 'optimization', 'comprehensive'])]
        string $focusArea = 'comprehensive',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $guidance = $this->getAuthoringGuidance();

        $prompt = "Using the comprehensive OpenFGA model authoring guidance, provide detailed assistance for:

**Use Case:** {$useCase}
**Focus Area:** {$focusArea}

# OpenFGA Model Authoring Guidance

{$guidance}

Based on this guidance, please provide:
1. **Specific recommendations** for the {$useCase} use case
2. **Best practices** relevant to {$focusArea}
3. **Example DSL model** tailored to the requirements
4. **Common pitfalls** to avoid
5. **Testing strategies** appropriate for this scenario

Ensure the response is practical, actionable, and follows OpenFGA best practices.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt for implementing custom roles in OpenFGA.
     *
     * @param  string                            $roleRequirements Description of custom role requirements
     * @param  string                            $roleScope        The scope of custom roles (global, resource-specific, hybrid)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'implement_custom_roles')]
    public function implementCustomRoles(
        string $roleRequirements,
        #[CompletionProvider(values: ['global', 'resource_specific', 'hybrid', 'hierarchical'])]
        string $roleScope = 'global',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $guidance = $this->getCustomRolesGuidance();

        $prompt = "Design custom roles implementation for OpenFGA:

**Role Requirements:**
{$roleRequirements}

**Role Scope:** {$roleScope}

{$guidance}

Please provide:
1. **Role Model Design** - Complete DSL for custom roles
2. **Permission Assignment** - How to grant permissions to roles
3. **User Assignment** - How to assign users to roles
4. **Role Management** - Patterns for creating, updating, and deleting roles
5. **Migration Strategy** - Transitioning from static to custom roles
6. **Example Tuples** - Sample relationship tuples for the implementation

Consider:
- Whether to use simple user-defined roles or role assignments pattern
- Performance implications of the chosen approach
- Flexibility for future role modifications
- Integration with existing static roles
- Audit and compliance requirements";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt for testing OpenFGA models.
     *
     * @param  string                            $model     The OpenFGA DSL model to test
     * @param  string                            $testFocus What aspect to focus testing on
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'test_model_comprehensive')]
    public function testModelComprehensive(
        string $model,
        #[CompletionProvider(values: ['permissions', 'inheritance', 'edge_cases', 'security', 'performance', 'comprehensive'])]
        string $testFocus = 'comprehensive',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $guidance = $this->getTestingGuidance();

        $prompt = "Create comprehensive test cases for the OpenFGA model:

**Model to Test:**
```dsl
{$model}
```

**Test Focus:** {$testFocus}

{$guidance}

Please provide:
1. **Complete .fga.yaml file** with:
   - Model definition
   - Relationship tuples setup
   - Check tests for permission verification
   - List objects tests for resource discovery
   - List users tests for access review

2. **Test Scenarios** covering:
   - Positive and negative test cases
   - Permission inheritance chains
   - Edge cases and boundary conditions
   - Security validation tests

3. **Test Organization**:
   - Group tests by feature/permission
   - Clear test naming conventions
   - Documentation of test purpose

4. **Validation Strategy**:
   - How to verify model correctness
   - Performance testing approach
   - Regression test planning

Focus on {$testFocus} with particular attention to potential security issues.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Get the core authoring guidance content.
     */
    private function getAuthoringGuidance(): string
    {
        return <<<'GUIDANCE'
            ## Introduction to OpenFGA and Authorization Modeling

            OpenFGA is an open-source authorization solution that empowers developers to implement fine-grained access control within their applications through an intuitive modeling language. It functions as a flexible authorization engine, simplifying the process of defining application permissions.

            Inspired by Google's Zanzibar paper, OpenFGA primarily champions Relationship-Based Access Control (ReBAC), while also effectively addressing use cases for Role-Based Access Control (RBAC) and Attribute-Based Access Control (ABAC).

            ## Core Concepts

            - **Authorization Model:** A static blueprint defining the permission structure of a system
            - **Type:** A class of objects sharing similar characteristics (e.g., user, document, folder)
            - **Object:** A specific instance of a Type (e.g., `document:roadmap`, `user:anne`)
            - **User:** An entity that can be related to an object (specific user, wildcard, or userset)
            - **Relation:** A string defining possible relationships between objects and users
            - **Relationship Tuple:** Dynamic data representing actual relationships

            ## The OpenFGA Modeling Language (DSL)

            ```dsl
            model
              schema 1.1
            type user
            type document
              relations
                define viewer: [user] or editor
                define editor: [user]
            ```

            ## Defining Relationships

            ### Direct Relationships
            Explicit access grants: `define owner: [user]`

            ### Concentric Relationships
            Inherited permissions: `define viewer: [user] or editor`

            ### Indirect Relationships ('X from Y')
            Hierarchical access: `define admin: [user] or repo_admin from organization`

            ### Conditional Relationships
            Dynamic permissions with CEL: `define admin: [user with non_expired_grant]`

            ### Usersets
            Group-based access: `define editor: [user, team#member]`
            GUIDANCE;
    }

    /**
     * Get the custom roles guidance content.
     */
    private function getCustomRolesGuidance(): string
    {
        return <<<'GUIDANCE'
            ## Modeling Custom Roles

            ### Simple User-Defined Roles (Global)

            For organization-wide custom roles:

            ```dsl
            type role
              relations
                define assignee: [user]

            type organization
              relations
                define admin: [user]  # static role
                define can_create_project: [role#assignee] or admin
                define can_edit_project: [role#assignee] or admin
            ```

            ### Custom Roles with Role Assignments (Resource-Specific)

            For resource-specific roles with different members per instance:

            ```dsl
            type role
              relations
                define can_view_project: [user:*]
                define can_edit_project: [user:*]

            type role_assignment
              relations
                define assignee: [user]
                define role: [role]
                define can_view_project: assignee and can_view_project from role
                define can_edit_project: assignee and can_edit_project from role

            type project
              relations
                define role_assignment: [role_assignment]
                define can_edit_project: can_edit_project from role_assignment
            ```

            ### When to Use Each Pattern:
            - **Global Custom Roles**: Organization-wide roles with consistent permissions
            - **Role Assignments**: Resource-specific roles with different members per resource

            ### Migration Strategy:
            1. Additive approach - Introduce custom roles alongside existing static roles
            2. Gradual migration - Move permissions one at a time
            3. Backwards compatibility - Maintain existing behavior during transition
            GUIDANCE;
    }

    /**
     * Get the relationship patterns guidance content.
     */
    private function getRelationshipPatternsGuidance(): string
    {
        return <<<'GUIDANCE'
            ## Relationship Pattern Reference

            ### Pattern Summary Table

            | Pattern | Description | DSL Example | Use Case |
            |---------|-------------|-------------|----------|
            | Direct | Explicit grants | `define owner: [user]` | Individual permissions |
            | Concentric | Implied relations | `define viewer: [user] or editor` | Permission hierarchy |
            | Indirect (X from Y) | Through intermediary | `define admin: [user] or repo_admin from owner` | Hierarchical/group access |
            | Conditional | Dynamic permissions | `define admin: [user with non_expired_grant]` | Time-based, attribute-based |
            | Usersets | Group collections | `define editor: [user, team#member]` | Team/role assignments |

            ### Composition Strategies

            OpenFGA's strength lies in composing complex authorization from foundational patterns:

            1. **Layer patterns** - Combine direct + concentric for flexibility
            2. **Chain relationships** - Use X from Y for deep hierarchies
            3. **Mix static and dynamic** - Combine usersets with conditions
            4. **Balance complexity** - Start simple, add patterns as needed

            ### Performance Considerations

            - Direct relationships: Fastest lookups
            - Concentric: Single additional check
            - Indirect (X from Y): Requires traversal
            - Conditions: Runtime evaluation cost
            - Usersets: Scales with group size

            ### Security Implications

            - Validate inheritance doesn't escalate privileges
            - Audit indirect relationship chains
            - Test boundary conditions thoroughly
            - Document security assumptions
            - Review for privilege escalation paths
            GUIDANCE;
    }

    /**
     * Get the step-by-step guidance content.
     */
    private function getStepByStepGuidance(): string
    {
        return <<<'GUIDANCE'
            ## Step-by-Step Model Authoring Process

            The process of authoring an OpenFGA model is iterative, starting with critical features and systematically translating authorization requirements into a structured model.

            ### Recommended Steps:

            1. **Pick the most important feature** - Focus on a high-priority use case
            2. **List the object types** - Identify all relevant entities
            3. **List relations for those types** - Determine relationships users can have
            4. **Define relations** - Translate into OpenFGA DSL
            5. **Test the model** - Validate against expected behaviors
            6. **Iterate** - Refine based on testing and requirements

            ### Key Questions:
            - "Why could user U perform action A on object O?"
            - What inheritance patterns make sense?
            - How will permissions scale?

            ### Design Principles:
            - Start simple, add complexity incrementally
            - Use meaningful, consistent naming
            - Avoid circular dependencies
            - Design for query performance
            - Document your decisions
            GUIDANCE;
    }

    /**
     * Get the testing guidance content.
     */
    private function getTestingGuidance(): string
    {
        return <<<'GUIDANCE'
            ## Testing OpenFGA Models

            ### Test File Structure (.fga.yaml)

            ```yaml
            name: Model Tests
            model: |
              model
              schema 1.1
              type user
              type document
                relations
                  define viewer: [user] or editor
                  define editor: [user]

            tuples:
              - user: user:anne
                relation: editor
                object: document:roadmap

            tests:
              - name: Editor can view
                check:
                  - user: user:anne
                    object: document:roadmap
                    assertions:
                      viewer: true
                      editor: true

              - name: List user documents
                list_objects:
                  - user: user:anne
                    type: document
                    assertions:
                      editor:
                        - document:roadmap

              - name: List document users
                list_users:
                  - object: document:roadmap
                    user_filter:
                      - type: user
                    assertions:
                      editor:
                        users:
                          - user:anne
            ```

            ### Testing Best Practices:
            1. Test positive and negative cases
            2. Verify inheritance chains
            3. Check edge cases and boundaries
            4. Validate security assumptions
            5. Test with realistic data volumes
            6. Document test purpose and expected outcomes

            ### Test Coverage Areas:
            - Direct permission grants
            - Inherited permissions
            - Hierarchical relationships
            - Conditional access
            - Group-based permissions
            - Permission boundaries
            GUIDANCE;
    }
}
