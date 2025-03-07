# Security Policy

## Supported Versions

We currently support the following versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| latest  | :white_check_mark: |

## Reporting a Vulnerability

The OpenFGA MCP team takes security vulnerabilities seriously. We appreciate your efforts to responsibly disclose your findings.

To report a security vulnerability, please **DO NOT** open a public GitHub issue. Instead, please email security concerns to [hello@evansims.com](mailto:hello@evansims.com) with "SECURITY" in the subject line.

Please include the following information in your report:

- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact of the vulnerability
- Any potential solutions you've identified

## Security Measures

This project implements several security measures:

- Automated dependency scanning with GitHub's Dependency Review
- Docker image vulnerability scanning with Trivy
- Static code analysis with CodeQL
- Software Bill of Materials (SBOM) generation
- Regular dependency updates

## Response Process

When a vulnerability is reported, we will:

1. Acknowledge receipt of your vulnerability report within 3 business days
2. Provide a more detailed response within 10 business days, indicating next steps
3. Keep you informed about our progress in addressing the vulnerability
4. Notify you when the vulnerability has been fixed

## Security Best Practices for Contributors

When contributing to this project, please follow these security best practices:

1. Keep dependencies up to date
2. Avoid hardcoding sensitive information
3. Follow the principle of least privilege
4. Use secure coding practices
5. Run security checks locally before submitting pull requests
