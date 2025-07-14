# Security Policy

## Supply Chain Security

This project implements SLSA (Supply-chain Levels for Software Artifacts) Level 3 provenance generation for all releases.

### Verifying Release Artifacts

All releases include cryptographically signed provenance that can be verified to ensure:

- The artifacts were built from the expected source repository
- The build process was not tampered with
- The artifacts match what was produced by our CI/CD pipeline

#### Prerequisites

Install the SLSA verifier:

```bash
# macOS
brew install slsa-verifier

# Linux
curl -sSLO https://github.com/slsa-framework/slsa-verifier/releases/latest/download/slsa-verifier-linux-amd64
chmod +x slsa-verifier-linux-amd64
sudo mv slsa-verifier-linux-amd64 /usr/local/bin/slsa-verifier

# Windows
curl -sSLO https://github.com/slsa-framework/slsa-verifier/releases/latest/download/slsa-verifier-windows-amd64.exe
```

#### Verifying a Release

1. Download the release artifact and its provenance:

```bash
# Download the release artifact
curl -LO https://github.com/evansims/openfga-mcp/releases/download/v1.0.0/openfga-mcp-v1.0.0.tar.gz

# Download the provenance
curl -LO https://github.com/evansims/openfga-mcp/releases/download/v1.0.0/openfga-mcp-v1.0.0.tar.gz.intoto.jsonl
```

2. Verify the provenance:

```bash
slsa-verifier verify-artifact \
  openfga-mcp-v1.0.0.tar.gz \
  --provenance-path openfga-mcp-v1.0.0.tar.gz.intoto.jsonl \
  --source-uri github.com/evansims/openfga-mcp \
  --source-tag v1.0.0
```

#### Verifying Docker Images

Our Docker images include embedded provenance and SBOM (Software Bill of Materials) attestations:

```bash
# Verify Docker image provenance
docker trust inspect --pretty <dockerhub-username>/openfga-mcp:1.0.0

# View image attestations
docker buildx imagetools inspect <dockerhub-username>/openfga-mcp:1.0.0 --format "{{json .Provenance}}"

# Using cosign (alternative method)
cosign verify-attestation <dockerhub-username>/openfga-mcp:1.0.0 \
  --type slsaprovenance \
  --certificate-identity-regexp "^https://github.com/evansims/openfga-mcp/.github/workflows/" \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com
```

### Build Reproducibility

Our build process aims for reproducibility by:

- Using locked dependency versions via `composer.lock`
- Setting consistent timestamps in archives using `SOURCE_DATE_EPOCH`
- Normalizing file permissions and ownership in release archives
- Building in consistent environments via GitHub Actions

## Reporting Security Vulnerabilities

Please report security vulnerabilities via [GitHub Security Advisories](https://github.com/evansims/openfga-mcp/security/advisories/new).
