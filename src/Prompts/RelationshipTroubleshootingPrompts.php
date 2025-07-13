<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Prompts;

use OpenFGA\ClientInterface;
use PhpMcp\Server\Attributes\McpPrompt;

final readonly class RelationshipTroubleshootingPrompts extends AbstractPrompts
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private ClientInterface $client,
    ) {
        // Client is injected for dependency injection but may be used in future implementations
    }

    /**
     * Generate a prompt to analyze and understand permission inheritance paths.
     *
     * @param  string                            $user           The user to analyze inheritance for
     * @param  string                            $object         The object to trace permissions to
     * @param  string                            $expectedAccess Whether user should or shouldn't have access
     * @param  string                            $storeId        OpenFGA store ID for context
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'analyze_permission_inheritance')]
    public function analyzePermissionInheritance(string $user, string $object, string $expectedAccess = 'should have access', string $storeId = ''): array
    {
        $error = $this->checkRestrictedMode(('' !== $storeId) ? $storeId : null);

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $storeContext = ('' !== $storeId) ? '
**Store ID:** ' . $storeId : '';

        $prompt = "Analyze the permission inheritance paths for the following scenario:

**Scenario:**
- User: {$user}
- Object: {$object}
- Expected: User {$expectedAccess}{$storeContext}

Please provide a comprehensive inheritance analysis:

1. **Direct Relationships**:
   - List any direct relationships between {$user} and {$object}
   - Check for immediate permission grants

2. **Inheritance Chain Analysis**:
   - Map out all possible inheritance paths from {$user} to {$object}
   - Identify intermediate objects and relations in the chain
   - Show step-by-step inheritance flow

3. **Group and Role Memberships**:
   - Check if {$user} belongs to any groups that have access to {$object}
   - Analyze role-based permissions that might apply
   - Verify group membership relationships

4. **Hierarchical Permissions**:
   - Examine parent-child relationships that could grant access
   - Check organizational hierarchy permissions
   - Verify resource hierarchy inheritance

5. **Conditional Access**:
   - Identify any conditional or computed relations
   - Check for time-based or context-dependent permissions
   - Analyze complex permission logic

6. **Inheritance Visualization**:
   - Create a tree or graph showing all inheritance paths
   - Mark active vs inactive inheritance routes
   - Highlight where inheritance might be broken

7. **Troubleshooting Recommendations**:
   - Identify missing links in the inheritance chain
   - Suggest relationships to create or modify
   - Recommend model improvements for clearer inheritance

Provide clear explanations of how OpenFGA evaluates these inheritance paths and why certain paths succeed or fail.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to debug why a user was denied access to a resource.
     *
     * @param  string                            $user     The user who was denied access (e.g., 'user:alice')
     * @param  string                            $relation The relation being checked (e.g., 'viewer', 'editor')
     * @param  string                            $object   The object being accessed (e.g., 'document:budget')
     * @param  string                            $storeId  OpenFGA store ID for context
     * @param  string                            $modelId  Authorization model ID for context
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'debug_permission_denial')]
    public function debugPermissionDenial(string $user, string $relation, string $object, string $storeId = '', string $modelId = ''): array
    {
        $error = $this->checkRestrictedMode(('' !== $storeId) ? $storeId : null, ('' !== $modelId) ? $modelId : null);

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $storeContext = ('' !== $storeId) ? '
**Store ID:** ' . $storeId : '';
        $modelContext = ('' !== $modelId) ? '
**Model ID:** ' . $modelId : '';

        $prompt = "Help debug why the following permission check failed in OpenFGA:

**Permission Check:**
- User: {$user}
- Relation: {$relation}
- Object: {$object}{$storeContext}{$modelContext}

**Result:** ❌ Permission DENIED

Please provide a systematic debugging approach:

1. **Relationship Analysis**:
   - Check if direct relationships exist between {$user} and {$object}
   - Verify relationship tuple format and syntax
   - Confirm relation name matches model definition

2. **Model Verification**:
   - Examine the authorization model for the {$relation} relation definition
   - Check if {$relation} is properly defined for the object type
   - Verify inheritance patterns and relation chains

3. **Inheritance Path Investigation**:
   - Trace potential inheritance paths that could grant access
   - Check for intermediate relationships that might be missing
   - Verify parent-child relationships in hierarchical structures

4. **Common Issues to Check**:
   - Typos in user, relation, or object identifiers
   - Missing relationship tuples that should exist
   - Incorrect object type prefixes
   - Malformed relation definitions in the model
   - Circular dependencies or broken inheritance chains

5. **Debugging Commands**:
   - Suggest specific OpenFGA API calls to investigate
   - Recommend relationship queries to run
   - Provide model validation steps

6. **Resolution Steps**:
   - Identify what relationships need to be created
   - Suggest model changes if the relation definition is incorrect
   - Provide exact tuples to create for fixing access

Focus on practical, actionable debugging steps that will quickly identify the root cause.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to optimize relationship queries for better performance.
     *
     * @param  string                            $queryType        Type of query to optimize (check, list_objects, list_users, expand)
     * @param  string                            $performanceIssue Specific performance problem being experienced
     * @param  string                            $modelComplexity  Complexity of the authorization model (simple, moderate, complex)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'optimize_relationship_queries')]
    public function optimizeRelationshipQueries(string $queryType, string $performanceIssue = 'slow response times', string $modelComplexity = 'moderate'): array
    {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Optimize OpenFGA relationship queries for better performance:

**Query Optimization Target:**
- Query Type: {$queryType}
- Performance Issue: {$performanceIssue}
- Model Complexity: {$modelComplexity}

Please provide optimization strategies:

1. **Query Pattern Analysis**:
   - Analyze the current {$queryType} query patterns
   - Identify performance bottlenecks in the query execution
   - Examine the authorization model structure for optimization opportunities

2. **Model Optimization**:
   - Suggest authorization model changes to improve {$queryType} performance
   - Recommend relation definition optimizations
   - Identify unnecessary complexity in inheritance chains

3. **Query Structure Improvements**:
   - Optimize query parameters and filters
   - Suggest batching strategies for multiple queries
   - Recommend caching patterns for frequently accessed data

4. **Relationship Design**:
   - Design more efficient relationship patterns
   - Minimize deep inheritance chains that slow down queries
   - Optimize for the most common query patterns

5. **Indexing and Storage**:
   - Recommend relationship storage patterns for better performance
   - Suggest indexing strategies for frequently queried relationships
   - Optimize relationship tuple organization

6. **Performance Monitoring**:
   - Identify key metrics to monitor for {$queryType} queries
   - Set up performance benchmarks and alerts
   - Establish query performance baselines

7. **Scaling Strategies**:
   - Design for horizontal scaling of authorization queries
   - Implement query result caching where appropriate
   - Optimize for high-volume authorization checking

8. **Best Practices**:
   - Follow OpenFGA performance best practices
   - Avoid common anti-patterns that hurt performance
   - Balance security requirements with performance needs

Focus on practical optimizations that will significantly improve {$performanceIssue} while maintaining security and correctness.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to troubleshoot unexpected permission grants.
     *
     * @param  string                            $user     The user who unexpectedly has access
     * @param  string                            $relation The relation that was unexpectedly granted
     * @param  string                            $object   The object that was unexpectedly accessible
     * @param  string                            $storeId  OpenFGA store ID for context
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'troubleshoot_unexpected_access')]
    public function troubleshootUnexpectedAccess(string $user, string $relation, string $object, string $storeId = ''): array
    {
        $error = $this->checkRestrictedMode(('' !== $storeId) ? $storeId : null);

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $storeContext = ('' !== $storeId) ? '
**Store ID:** ' . $storeId : '';

        $prompt = "Investigate why the following user has unexpected access in OpenFGA:

**Unexpected Access:**
- User: {$user}
- Relation: {$relation}
- Object: {$object}
- Issue: User should NOT have this access{$storeContext}

This is a security investigation to identify unintended permission grants:

1. **Direct Relationship Audit**:
   - Check for direct relationships that shouldn't exist
   - Verify if {$user} was explicitly granted {$relation} on {$object}
   - Look for recently created relationships

2. **Inheritance Investigation**:
   - Trace all inheritance paths that could grant this permission
   - Identify unexpected inheritance routes
   - Check for overly permissive relation definitions

3. **Group Membership Analysis**:
   - Verify if {$user} belongs to groups with excessive permissions
   - Check for unintended group memberships
   - Analyze role escalation possibilities

4. **Model Security Review**:
   - Examine the authorization model for overly broad relation definitions
   - Look for unintended permission cascades
   - Check for relations that grant too much access

5. **Administrative Oversight**:
   - Check for admin or superuser relationships
   - Verify if {$user} has elevated privileges
   - Look for wildcard or universal permissions

6. **Recent Changes**:
   - Identify recent relationship changes that might have caused this
   - Check for model updates that expanded permissions
   - Review administrative actions

7. **Security Hardening**:
   - Recommend immediate steps to revoke unintended access
   - Suggest model changes to prevent future issues
   - Provide access control tightening strategies

8. **Compliance Check**:
   - Verify if this access violates security policies
   - Check against principle of least privilege
   - Assess regulatory compliance implications

Focus on quickly identifying and mitigating the security risk while preventing similar issues in the future.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }
}
