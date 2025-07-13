<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Prompts;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\{AuditFrequency, ComplianceFramework, DelegationType, RiskLevel, SecurityLevel, SystemCriticality, SystemType};
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

Please provide a least privilege implementation:

1. **Minimal Permission Analysis**:
   - Analyze each role's actual job requirements
   - Identify the minimum permissions needed for each function
   - Eliminate unnecessary or excessive permissions

2. **Granular Access Control**:
   - Design fine-grained permissions rather than broad access
   - Create specific relations for distinct operations
   - Avoid bundling unrelated permissions together

3. **Role-Based Restrictions**:
   - Limit each role to only essential permissions
   - Implement temporal restrictions where appropriate
   - Design for regular permission reviews and audits

4. **Data Access Minimization**:
   - Restrict access to {sensitiveData} based on need-to-know
   - Implement data classification and protection levels
   - Design context-aware access controls

5. **OpenFGA Model Design**:
   - Create relations that enforce minimal access
   - Design inheritance patterns that don't grant excessive permissions
   - Implement proper separation between read and write operations

6. **Administrative Controls**:
   - Limit administrative privileges to essential personnel
   - Implement approval workflows for sensitive operations
   - Design secure delegation and temporary access patterns

7. **Security Boundaries**:
   - Create clear security zones and access boundaries
   - Implement network and resource segmentation
   - Design defense in depth through layered access controls

8. **Monitoring and Compliance**:
   - Design for continuous access monitoring
   - Implement automated privilege reviews
   - Create audit trails for all access decisions

9. **Implementation Strategy**:
   - Provide step-by-step implementation plan
   - Recommend gradual rollout to minimize disruption
   - Include user training and change management considerations

Focus on creating a practical, implementable least privilege model that enhances security without hindering productivity in a {$systemType} environment.";

        return [
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * Generate a prompt to design secure delegation patterns in OpenFGA.
     *
     * @param  string                            $delegationType  Type of delegation needed (temporary, permanent, conditional)
     * @param  string                            $businessContext Business context requiring delegation
     * @param  string                            $riskLevel       Risk level of delegated permissions (low, medium, high)
     * @return array<int, array<string, string>>
     */
    #[McpPrompt(name: 'secure_delegation_patterns')]
    public function secureDelegationPatterns(
        #[CompletionProvider(enum: DelegationType::class)]
        string $delegationType,
        string $businessContext,
        #[CompletionProvider(enum: RiskLevel::class)]
        string $riskLevel = 'medium',
    ): array {
        $error = $this->checkRestrictedMode();

        if ($this->hasError($error)) {
            return $this->createErrorResponse($error);
        }

        $prompt = "Design secure delegation patterns in OpenFGA for the following requirements:

**Delegation Requirements:**
- Delegation Type: {$delegationType}
- Business Context: {$businessContext}
- Risk Level: {$riskLevel}

Please provide a secure delegation design:

1. **Delegation Architecture**:
   - Design the overall delegation structure in OpenFGA
   - Define delegator and delegatee relationships
   - Create clear delegation boundaries and limitations

2. **Permission Scoping**:
   - Limit delegated permissions to specific scopes
   - Implement time-based restrictions for {$delegationType} delegation
   - Design granular control over what can be delegated

3. **Security Controls**:
   - Implement approval workflows for {$riskLevel} risk delegations
   - Create monitoring and auditing for delegated access
   - Design automatic revocation mechanisms

4. **OpenFGA Model Implementation**:
   - Create relations that support secure delegation
   - Design inheritance patterns for delegated permissions
   - Implement conditional relations based on delegation context

5. **Business Context Integration**:
   - Align delegation patterns with {$businessContext} requirements
   - Ensure delegation supports business processes
   - Balance security with operational efficiency

6. **Delegation Lifecycle**:
   - Design creation, modification, and revocation processes
   - Implement periodic review and renewal requirements
   - Create emergency revocation capabilities

7. **Audit and Compliance**:
   - Design comprehensive audit trails for all delegated actions
   - Implement reporting for delegation usage and compliance
   - Create alerts for unusual delegation patterns

8. **Risk Mitigation**:
   - Address specific risks associated with {$riskLevel} delegation
   - Implement compensating controls for high-risk scenarios
   - Design fail-safe mechanisms for delegation failures

9. **Edge Cases and Exceptions**:
   - Handle delegation conflicts and overlapping permissions
   - Design for delegation chains and sub-delegation scenarios
   - Implement safeguards against delegation abuse

10. **Implementation Guidance**:
    - Provide step-by-step implementation instructions
    - Include testing strategies for delegation patterns
    - Recommend deployment and rollback procedures

Focus on creating a robust, secure delegation system that meets {$businessContext} needs while maintaining strong security controls for {$riskLevel} risk scenarios.";

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
