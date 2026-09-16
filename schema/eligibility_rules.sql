-- Maatify Eligibility B3 schema.
--
-- Ownership: Eligibility owns this table and all Rules stored in it.
-- Compatibility: MySQL-compatible persistence semantics through PDO; this
-- schema does not declare a minimum MySQL or MariaDB product version.
-- Identity: subject_type + subject_id + dimension_key + dimension_value is
-- the one natural identity, independent of effect and lifecycle.
-- Lifecycle: create stores active Rules; inactive Rules remain unique and
-- management-visible, while evaluation reads select active Rules only.
-- Host boundary: no Host foreign key or Host-table JOIN is used.
-- Exact strings: canonical UTF-8 is validated in PHP and stored as VARBINARY
-- so database collation cannot fold case or normalize Unicode.
-- Cleanup: the package cleanup primitive physically deletes all rows for one
-- supplied Subject; it is idempotent and does not touch other Subjects.

CREATE TABLE IF NOT EXISTS `maa_eligibility_rules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Infrastructure-only surrogate key; never exposed as a Rule identity.',
    `subject_type` VARBINARY(64) NOT NULL COMMENT 'Host-provided canonical Subject type, validated in PHP; no Host FK.',
    `subject_id` VARBINARY(191) NOT NULL COMMENT 'Host-provided canonical Subject ID, validated in PHP; no Host FK.',
    `dimension_key` VARBINARY(64) NOT NULL COMMENT 'Host-defined canonical dimension key, validated in PHP.',
    `dimension_value` VARBINARY(255) NOT NULL COMMENT 'Canonical dimension value, validated in PHP and preserved byte-for-byte.',
    `effect` VARBINARY(5) NOT NULL COMMENT 'Canonical RuleEffectEnum value: allow or deny.',
    `lifecycle` VARBINARY(8) NOT NULL COMMENT 'Canonical RuleLifecycleEnum value: active or inactive.',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_maa_eligibility_rules_natural_identity` (
        `subject_type`,
        `subject_id`,
        `dimension_key`,
        `dimension_value`
    ),
    KEY `idx_maa_eligibility_rules_subject_lifecycle_dimension` (
        `subject_type`,
        `subject_id`,
        `lifecycle`,
        `dimension_key`,
        `dimension_value`
    )
) ENGINE=InnoDB COMMENT='Eligibility-owned Rule persistence; no Host coupling.';

-- The coordination row is package-owned metadata. Replacements and cleanup
-- acquire this row with a transaction-scoped row lock before reading or
-- mutating a Subject. It exists even when the Subject currently has no Rules,
-- so empty-dimension replacements do not rely on storage-engine gap locks.
CREATE TABLE IF NOT EXISTS `maa_eligibility_subject_locks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Infrastructure-only coordination key; never exposed through the public API.',
    `subject_type` VARBINARY(64) NOT NULL COMMENT 'Host-provided canonical Subject type; no Host FK.',
    `subject_id` VARBINARY(191) NOT NULL COMMENT 'Host-provided canonical Subject ID; no Host FK.',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_maa_eligibility_subject_locks_subject` (`subject_type`, `subject_id`)
) ENGINE=InnoDB COMMENT='Eligibility-owned replacement/cleanup coordination rows; no Host coupling.';
