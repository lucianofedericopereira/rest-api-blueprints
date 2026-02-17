# Security Policy

## Supported Versions

This is a reference implementation for educational and portfolio purposes.
Only the latest version on `main` is maintained.

| Version | Supported |
|---------|-----------|
| latest (`main`) | yes |
| older tags | no |

## Reporting a Vulnerability

If you find a security issue in this reference implementation (e.g. a committed secret, an insecure pattern presented as correct, or a dependency with a known CVE):

1. **Do not open a public issue.**
2. Email **lucianopereira@posteo.es** with the subject `[SECURITY] rest-api-blueprints`.
3. Include: what you found, which file/line, and the potential impact.

You will receive a response within **72 hours**. If the issue is confirmed, a fix will be committed and a new release tagged within **7 days**.

## Scope

- Committed secrets or key material → in scope
- Insecure code patterns presented as ISO 27001-compliant → in scope
- Vulnerabilities in third-party dependencies (`vendor/`, `node_modules/`) → report upstream; out of scope here
- The Terraform IaC layer → in scope if a misconfiguration is presented as secure
