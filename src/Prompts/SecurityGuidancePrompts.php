<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Prompts;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\{AuditFrequency, ComplianceFramework, RiskLevel, SecurityLevel, SystemCriticality, SystemType};
use PhpMcp\Server\Attributes\{CompletionProvider, McpPrompt};

final readonly class SecurityGuidancePrompts extends AbstractPrompts
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private ClientInterface $client,
    ) {
        // Client is injected for dependency injection but may be used in future implementations
    }

    /**
     * Generate a prompt to design audit-friendly authorization patterns for compliance.
     *
     * @param  string                            $auditRequirements Specific audit requirements (SOX, HIPAA, PCI-DSS, etc.)
     * @param  string                            $auditFrequency    How often audits are conducted (monthly, quarterly, annual)
     * @param  string                            $systemCriticality Criticality of the system being audited (low, medium, high, critical)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'audit_friendly_patterns')]
    public function auditFriendlyPatterns(
        #[CompletionProvider(enum: ComplianceFramework::class)]
        string $auditRequirements,
        #[CompletionProvider(enum: AuditFrequency::class)]
        string $auditFrequency = 'quarterly',
        #[CompletionProvider(enum: SystemCriticality::class)]
        string $systemCriticality = 'high',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Design audit-friendly authorization patterns in OpenFGA for compliance requirements:

**Audit Requirements:**
- Compliance Standard: {$auditRequirements}
- Audit Frequency: {$auditFrequency}
- System Criticality: {$systemCriticality}

Please provide an audit-friendly authorization design:

1. **Audit Trail Design**:
   - Create comprehensive logging for all authorization decisions
   - Design immutable audit records for regulatory compliance
   - Implement real-time audit event capture

2. **Compliance Mapping**:
   - Map OpenFGA relations to {$auditRequirements} requirements
   - Ensure authorization model supports compliance controls
   - Design for regulatory reporting needs

3. **Access Documentation**:
   - Create clear documentation of all authorization patterns
   - Design self-documenting relation names and structures
   - Implement automated access rights reporting

4. **Segregation of Duties**:
   - Implement proper separation of duties controls
   - Design approval workflows for sensitive operations
   - Create conflict of interest prevention mechanisms

5. **Audit Reporting Capabilities**:
   - Design queries for {$auditFrequency} audit reporting
   - Create access certification and review processes
   - Implement automated compliance monitoring

6. **OpenFGA Model for Auditability**:
   - Design relations that clearly show access paths
   - Create audit-friendly inheritance patterns
   - Implement timestamped relationship tracking

7. **Compliance Controls**:
   - Implement controls specific to {$auditRequirements}
   - Design for {$systemCriticality} system protection requirements
   - Create evidence collection mechanisms

8. **Risk Assessment Integration**:
   - Design authorization patterns that support risk assessment
   - Implement risk-based access controls
   - Create risk monitoring and alerting capabilities

9. **Audit Preparation**:
   - Create audit preparation checklists and procedures
   - Design evidence collection and presentation processes
   - Implement audit trail validation mechanisms

10. **Continuous Compliance**:
    - Design for ongoing compliance monitoring
    - Implement automated compliance checking
    - Create exception handling and remediation processes

11. **Documentation Standards**:
    - Create comprehensive authorization documentation
    - Implement change management with audit trails
    - Design approval processes for model modifications

Focus on creating authorization patterns that not only meet {$auditRequirements} compliance but also streamline the {$auditFrequency} audit process for a {$systemCriticality} criticality system.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to implement temporary and shared access patterns using OpenFGA.
     *
     * @param  string                            $accessType      Type of access pattern (temporary, shared, delegated, conditional)
     * @param  string                            $businessContext Business context requiring special access
     * @param  string                            $riskLevel       Risk level of the access pattern (low, medium, high)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'implement_access_patterns')]
    public function implementAccessPatterns(
        #[CompletionProvider(values: ['temporary', 'shared', 'delegated', 'conditional'])]
        string $accessType,
        string $businessContext,
        #[CompletionProvider(enum: RiskLevel::class)]
        string $riskLevel = 'medium',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Implement secure {$accessType} access patterns using OpenFGA's documented features:

**Access Requirements:**
- Access Type: {$accessType}
- Business Context: {$businessContext}
- Risk Level: {$riskLevel}

Please provide an implementation using OpenFGA's core patterns:

1. **Using OpenFGA's Relationship Patterns**:
   - Implement {$accessType} access using direct relationships, concentric (or), or indirect (X from Y) patterns
   - Design appropriate relationship tuples for granting and revoking access
   - Use usersets for group-based {$accessType} access where appropriate

2. **Conditional Relations for Temporal Access**:
   - For temporary access, implement using conditional relationships with CEL expressions
   - Design time-based conditions (e.g., grant_expiry, valid_from, valid_until)
   - Show how to create conditional relationship tuples with context parameters

3. **Model Design for {$accessType} Access**:
   - Create type definitions that support {$accessType} access patterns
   - Define relations that clearly express the access intent
   - Use meaningful relation names (e.g., temporary_editor, shared_viewer)

4. **Using Custom Roles for Flexible Access**:
   - Implement {$accessType} access through custom role patterns if applicable
   - Show how role assignments can provide temporary or shared permissions
   - Design role-based access that can be easily granted and revoked

5. **Hierarchical Access Patterns**:
   - Use 'X from Y' pattern for inherited {$accessType} permissions
   - Design parent-child relationships that support access propagation
   - Implement organizational or resource hierarchies as needed

6. **Security Controls Using OpenFGA**:
   - Implement principle of least privilege through minimal relation grants
   - Use concentric relationships to ensure proper permission inheritance
   - Design relations that prevent privilege escalation

7. **Managing Access Lifecycle**:
   - Show how to grant {$accessType} access by creating relationship tuples
   - Demonstrate revocation by deleting specific tuples
   - For conditional access, show context-based enable/disable

8. **Testing with .fga.yaml**:
   - Provide comprehensive test cases for {$accessType} access scenarios
   - Include positive and negative test cases
   - Test access expiration and revocation scenarios

9. **Practical Implementation**:
   - Provide complete DSL model for the {$businessContext} use case
   - Show example relationship tuples for common scenarios
   - Include OpenFGA API calls for managing the access

10. **Risk Mitigation**:
    - Address {$riskLevel} risk through appropriate model design
    - Implement audit-friendly relation names and structures
    - Design for easy access review and compliance reporting

Focus on using OpenFGA's documented patterns (direct, concentric, indirect 'X from Y', conditional, usersets) to implement secure {$accessType} access for {$businessContext}.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to implement principle of least privilege in authorization design.
     *
     * @param  string                            $systemType    Type of system (web app, API, enterprise, microservices)
     * @param  string                            $userRoles     Description of user roles and their responsibilities
     * @param  string                            $sensitiveData Types of sensitive data that need protection
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'implement_least_privilege')]
    public function implementLeastPrivilege(
        #[CompletionProvider(enum: SystemType::class)]
        string $systemType,
        string $userRoles,
        string $sensitiveData = 'confidential business data',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Design an OpenFGA authorization model that implements the principle of least privilege for a {$systemType}:

**System Context:**
- System Type: {$systemType}
- User Roles: {$userRoles}
- Sensitive Data: {$sensitiveData}

Please provide a least privilege implementation using OpenFGA's core features:

1. **Minimal Permission Analysis**:
   - Analyze each role's actual job requirements
   - Identify the minimum permissions needed for each function
   - Map requirements to specific OpenFGA relations

2. **Granular Relations Design**:
   - Create fine-grained relations using direct relationships [user]
   - Use separate relations for distinct operations (e.g., can_read, can_write, can_delete)
   - Avoid bundling unrelated permissions in single relations

3. **Controlled Inheritance with Concentric Relationships**:
   - Use 'or' operator carefully to avoid excessive permission grants
   - Design concentric relationships that follow principle of least privilege
   - Example: define can_read: [user] or can_write (writers can read, but not vice versa)

4. **Restricted Access Using Indirect Relationships**:
   - Implement 'X from Y' patterns for hierarchical access control
   - Ensure parent objects don't automatically grant all child permissions
   - Design selective inheritance patterns

5. **OpenFGA Model Implementation**:
   - Create type definitions with minimal default permissions
   - Use specific relation names that clearly indicate scope (e.g., can_view_summary vs can_view_details)
   - Implement separate relations for read and write operations

6. **Conditional Relations for Context-Aware Access**:
   - Use conditional relationships with CEL for time-based or context-dependent access
   - Implement conditions that restrict access based on business rules
   - Example: define admin: [user with active_hours_check]

7. **Usersets for Controlled Group Access**:
   - Use usersets (e.g., team#member) only when group access is truly needed
   - Avoid wildcard permissions (user:*) except for truly public resources
   - Design usersets that grant minimal necessary permissions

8. **Testing Least Privilege with .fga.yaml**:
   - Create test cases that verify users cannot exceed their intended permissions
   - Test that permission inheritance doesn't grant unintended access
   - Validate that each role has exactly the permissions needed

9. **Implementation Example**:
   - Provide complete DSL model demonstrating least privilege for {$userRoles}
   - Show relationship tuples that grant minimal necessary access
   - Include examples of what permissions are explicitly NOT granted

Focus on using OpenFGA's patterns (direct, concentric 'or', indirect 'X from Y', conditional, usersets) to enforce least privilege for {$systemType} while protecting {$sensitiveData}.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to conduct a security review of an OpenFGA authorization model.
     *
     * @param  string                            $model           The OpenFGA DSL model to review
     * @param  string                            $securityLevel   Security requirements level (standard, high, critical)
     * @param  string                            $complianceNeeds Compliance requirements (SOC2, HIPAA, PCI, GDPR, etc.)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'security_review_model')]
    public function securityReviewModel(
        string $model,
        #[CompletionProvider(enum: SecurityLevel::class)]
        string $securityLevel = 'standard',
        #[CompletionProvider(enum: ComplianceFramework::class)]
        string $complianceNeeds = 'SOC2',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Conduct a comprehensive security review of the following OpenFGA authorization model:

**Model to Review:**
```
{$model}
```

**Security Requirements:**
- Security Level: {$securityLevel}
- Compliance: {$complianceNeeds}

Please provide a thorough security analysis:

1. **Access Control Vulnerabilities**:
   - Identify overly permissive relation definitions
   - Check for unintended privilege escalation paths
   - Analyze potential for unauthorized access

2. **Principle of Least Privilege**:
   - Verify that relations grant minimal necessary permissions
   - Check for excessive inheritance or broad access patterns
   - Recommend restrictions to tighten access control

3. **Separation of Duties**:
   - Ensure proper segregation of sensitive operations
   - Verify that critical actions require multiple approvals
   - Check for conflicts of interest in permission assignments

4. **Inheritance Security**:
   - Analyze inheritance chains for security vulnerabilities
   - Check for unintended permission propagation
   - Verify that inheritance doesn't bypass security controls

5. **Administrative Access**:
   - Review administrative and superuser permissions
   - Ensure proper controls on high-privilege accounts
   - Check for secure delegation patterns

6. **Compliance Alignment ({$complianceNeeds})**:
   - Verify model meets {$complianceNeeds} requirements
   - Check for necessary audit trails and logging capabilities
   - Ensure data access controls align with compliance standards

7. **Security Best Practices**:
   - Apply OpenFGA security best practices
   - Recommend security hardening measures
   - Identify potential security anti-patterns

8. **Threat Modeling**:
   - Consider potential attack vectors against the authorization model
   - Analyze risks from insider threats
   - Evaluate external threat scenarios

9. **Remediation Recommendations**:
   - Provide specific model changes to address security issues
   - Prioritize security fixes by risk level
   - Suggest implementation timeline for security improvements

Focus on practical security improvements that enhance protection while maintaining usability for {$securityLevel} security requirements.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }
}
