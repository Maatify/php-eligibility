# Eligibility schema

This directory contains the RC1 executable schema for package-owned Rule
persistence and Subject-specific coordination metadata.

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
- Package-owned coordination table: `maa_eligibility_subject_locks`.
- Table prefix: `maa_eligibility_`.
- Internal `id`: `BIGINT UNSIGNED AUTO_INCREMENT`, used only as an infrastructure
  surrogate key and never exposed through `Rule` or public package contracts.
- Canonical effect values: `allow` and `deny`.
- Canonical lifecycle values: `active` and `inactive`.
- No Host foreign key, Host join, audit actor column, timestamp, log, or event log
  is part of this slice.
- `maa_eligibility_subject_locks` contains one package-owned row per Subject
  that has participated in replacement or cleanup. A replacement/cleanup
  transaction creates the row if needed and locks it before reading or
  mutating Rules. This explicit coordination row protects initially-empty
  dimensions without relying on gap-lock behavior. It has no Host foreign key
  and is deleted by the management cleanup operation for that Subject.
- When a Host transaction is already active, the management service uses the transactional
  `SAVEPOINT`, `ROLLBACK TO SAVEPOINT`, and `RELEASE SAVEPOINT` capabilities
  through `maatify/persistence`'s `PdoSavepointTransactionRunner` to make each
  replacement/cleanup operation atomic without taking ownership of the Host
  transaction. The runner and Eligibility Rule adapters MUST use the same PDO
  connection. This is a capability requirement of the transactional
  MySQL-compatible semantics; the package declares no minimum MySQL or MariaDB
  product version.

Canonical strings are bounded in **bytes**, not characters, by the production
source of truth `Maatify\Eligibility\Common\CanonicalString` and
validated there before SQL:

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

Repository reads are hydrated into package `Rule` values and passed through the
existing package collections, which provide canonical bytewise ordering. The
schema's binary ordering is only an efficient bounded read order; it is not the
public ordering contract.

## Applying and reapplying

The SQL asset is safe to reapply because it uses `CREATE TABLE IF NOT EXISTS`.
It does not drop, replace, or truncate an existing valid table. It is an asset,
not a migration framework and is not run automatically during Composer install.

For local Integration tests, use the repository-owned lifecycle:

```bash
composer test:integration
```

The orchestrator binds a fresh dynamic host port to loopback only and discovers
it at runtime. It uses database `maatify_eligibility_test`, user `eligibility_test`, and password
`eligibility_test`; these credentials are test-only and must not be reused for
production. `ELIGIBILITY_TEST_DB_*` are verification-scoped environment values
exported by the canonical repository orchestrator. Raw PHPUnit execution is an
internal path and requires explicit verification environment values; the public
`composer test:integration` lifecycle does not use an externally overridden
fixed service.

The Integration suite applies the schema, clears package-owned Rule and
coordination rows, and verifies fresh application, safe reapplication with
valid data, lifecycle visibility, exact identity, paginated Management reads,
bulk active reads, cleanup of both package-owned tables, and repeatable setup.
It fails when the required real MySQL service or `ext-pdo_mysql` is unavailable;
it has no SQLite or mock fallback. Run the suite again after the first clean run
to prove repeatability; every invocation tears down its own disposable Compose
state.
