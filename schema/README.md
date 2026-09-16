# Eligibility schema

This directory contains the B3 executable schema for the package-owned Rule
persistence boundary.

## Compatibility contract (D2)

RC1 uses **MySQL-compatible database-server semantics through direct PDO**. The
database compatibility contract is capability-based, not product-version-based:
no minimum MySQL version and no minimum MariaDB version is declared. A compatible
database server must provide transactional InnoDB-style package-owned table
behavior, binary-safe exact-value storage/comparison, the indexed-key capacity
needed by this schema, and the uniqueness/index semantics used by it. Being
labelled MySQL-compatible is not enough to establish support. MariaDB verification
is not claimed unless it has actually been executed.

Separately, the PHP runtime must provide `ext-pdo` and `ext-pdo_mysql`. These are
PHP runtime requirements and are not supplied by the database server.

The local reproducibility fixture is `mysql:8.4.11`. It is a fixture version,
not a package minimum version.

## Schema contract

- Asset: [`eligibility_rules.sql`](eligibility_rules.sql).
- Package-owned table: `maa_eligibility_rules`.
- Table prefix: `maa_eligibility_`.
- Internal `id`: `BIGINT UNSIGNED AUTO_INCREMENT`, used only as an infrastructure
  surrogate key and never exposed through `Rule` or B2 contracts.
- Canonical effect values: `allow` and `deny`.
- Canonical lifecycle values: `active` and `inactive`.
- No Host foreign key, Host join, audit actor column, timestamp, log, or event log
  is part of this slice.

Canonical strings are bounded in **bytes**, not characters, by the production
source of truth `Maatify\Eligibility\Validation\CanonicalString` and validated
there before SQL:

| Field | Maximum | SQL type |
|---|---:|---|
| `subject_type` | 64 bytes | `VARBINARY(64)` |
| `subject_id` | 191 bytes | `VARBINARY(191)` |
| `dimension_key` | 64 bytes | `VARBINARY(64)` |
| `dimension_value` | 255 bytes | `VARBINARY(255)` |

The natural identity is the complete unique key:

```text
subject_type + subject_id + dimension_key + dimension_value
```

`effect` and `lifecycle` are deliberately excluded from that identity. The
574-byte aggregate unique key remains within the conservative indexed-key
capability selected for this schema. `VARBINARY` avoids database collation and
preserves validated UTF-8 bytes exactly, including case and composed/decomposed
Unicode forms. No trim, case conversion, normalization, transliteration,
coercion, or truncation is performed.

Repository reads are hydrated into B1 `Rule` values and passed through the
existing package collections, which provide canonical bytewise ordering. The
schema's binary ordering is only an efficient bounded read order; it is not the
public ordering contract.

## Applying and reapplying

The SQL asset is safe to reapply because it uses `CREATE TABLE IF NOT EXISTS`.
It does not drop, replace, or truncate an existing valid table. It is an asset,
not a migration framework and is not run automatically during Composer install.

For local Integration tests, start the dedicated test-only MySQL service:

```bash
docker compose -f docker-compose.integration.yml up -d --wait
composer test:integration
```

The fixture binds host port `13306` to loopback only (`127.0.0.1:13306`), and
uses database `maatify_eligibility_test`, user `eligibility_test`, and password
`eligibility_test`; these credentials are test-only and must not be reused for
production. The test environment can be overridden with `ELIGIBILITY_TEST_DB_HOST`,
`ELIGIBILITY_TEST_DB_PORT`, `ELIGIBILITY_TEST_DB_NAME`,
`ELIGIBILITY_TEST_DB_USER`, and `ELIGIBILITY_TEST_DB_PASSWORD`.

The Integration suite applies the schema, clears only package-owned Rule rows,
and verifies fresh application, safe reapplication with valid data, lifecycle
visibility, exact identity, bounded management reads, bulk active reads,
cleanup, and repeatable setup. It fails when the required real MySQL service or
`ext-pdo_mysql` is unavailable; it has no SQLite or mock fallback. Run the suite
again after the first clean run to prove repeatability. Stopping the fixture
afterward is explicit local cleanup:

```bash
docker compose -f docker-compose.integration.yml down
```
