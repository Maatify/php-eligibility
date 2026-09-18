# Security Policy

[![Maatify Eligibility](https://img.shields.io/badge/Maatify-Eligibility-blue?style=for-the-badge)](https://github.com/Maatify/php-eligibility)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)

## Supported Versions

`maatify/php-eligibility` is in **unpublished RC1 preparation** for
`1.0.0-rc.1`. The `1.0.0-rc.1` tag does not exist yet and the package is not
externally resolvable through any Composer distribution source, so there is
currently **no supported release line**.

When `v1.0.0-rc.1` is actually published and resolvable by external consumers
through its approved distribution source, this file will be updated to describe
that pre-release explicitly as a pre-release and to record the then-supported
release lines per the repository support policy. A published pre-release does
not establish a supported Stable line on its own.

## Reporting a Vulnerability

Please report a suspected vulnerability privately instead of opening a public
issue, so it can be assessed before it is disclosed.

Use the repository's private security channel
[GitHub Security Advisories](https://github.com/Maatify/php-eligibility/security/advisories/new)
and include:

- The affected package and the exact branch/commit you tested.
- A minimal reproduction (entry point, environment, and observations).
- Your suggested impact/severity assessment if you have one.

Because no version is published yet, do not assume a fix exists for a
numbered version; the maintainers will coordinate a fix on the development
branch and confirm when a published release line becomes available.

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
