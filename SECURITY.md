# Security Policy

[![Maatify Eligibility](https://img.shields.io/badge/Maatify-Eligibility-blue?style=for-the-badge)](https://github.com/Maatify/php-eligibility)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)

## Supported Versions

`maatify/php-eligibility` is the **Pre-Stable `v1.0.0-rc.1` Release Candidate**
distributed through Packagist. It is a pre-release and does not establish a
supported Stable line on its own.

## Reporting a Vulnerability

Please report a suspected vulnerability privately instead of opening a public
issue, so it can be assessed before it is disclosed.

Use the repository's private security channel
[GitHub Security Advisories](https://github.com/Maatify/php-eligibility/security/advisories/new)
and include:

- The affected package and the exact branch/commit you tested.
- A minimal reproduction (entry point, environment, and observations).
- Your suggested impact/severity assessment if you have one.

Because this is a pre-release, do not assume a fix exists for a numbered Stable
version; the maintainers will coordinate fixes on the development branch and
identify the affected release line in the security advisory.

## Scope

Security-relevant issues include runtime behavior that can lead to incorrect
eligibility decisions, data integrity or persistence failures, privacy/access
problems for stored rules or the Host database, and vulnerable build or
distribution tooling. This package trusts the Host database connection it is
given and the Subjects/Contexts it evaluates; problems caused by misconfigured
Host integration are not package vulnerabilities.

Coordinated public disclosure of a confirmed vulnerability happens only after a
fix has been prepared and communicated to reporters, consistent with the
Maatify disclosure practice.
