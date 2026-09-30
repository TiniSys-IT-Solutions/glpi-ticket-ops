# Changelog

## 0.1.0 - 2026-09-30

### Added

- independent configuration switches for diagnostics, requester correction, ticket organization and quick assignment;
- requester-free organization plans using the same immutable preview and execution pipeline;
- previewed “Assign to me and start” operation using the current technician and GLPI assigned status;
- dedicated GLPI-styled entry points for requester correction, organization and quick assignment.
- factual assignment summary covering source, entity, category, location, actors and last update.

### Security

- each submitted operation is checked against its enabled module on both preview and execution routes;
- quick assignment and organization choices remain target-entity scoped and server revalidated.

## 0.0.5 - 2026-09-30

### Added

- optional organization fields in the correction preview for category, location, technician group and technician;
- target-entity-scoped Select2 searches for every organization field;
- one native GLPI update plan covering requester, entity and explicitly selected organization changes.

## 0.0.4 - 2026-09-30

### Fixed

- enforce target-entity permission during preview as well as execution;
- accept explicit removals for field relations represented by negative plan identifiers;
- compare actor type and role in addition to relation identifiers to prevent cross-table ID collisions.

## 0.0.3 - 2026-09-30

### Added

- native GLPI accordion presentation and a health shortcut with issue counter;
- missing-location organization diagnostic.

### Changed

- use GLPI Bootstrap modal, Select2 fields, buttons and spacing for the correction workflow;
- rely on GLPI native actor counts for technician, group and supplier assignment diagnostics.

### Fixed

- explicitly delete the selected requester through GLPI's native actor update contract before adding its replacement.

## 0.0.2 - 2026-09-30

### Fixed

- align the installable key, Composer namespace and GLPI controller namespace on `ticketops`;
- restore GLPI 11 controller discovery and prevent the startup HTTP 500 caused by the previous namespace mismatch;
- package releases under the `ticketops/` root and expose the TicketOps identity consistently.

## 0.0.1 - 2026-09-30

### Added

- GLPI 11.0.8 installable bootstrap with strict compatibility bounds;
- native central-profile rights for diagnostics and requester/entity correction;
- English and French gettext catalogs;
- scoped CSS and JavaScript foundations plus plugin branding;
- unit, static, JavaScript and guarded native-GLPI integration tests;
- hardened whitelist-based ZIP release build and GitHub Actions workflows;
- architecture, installation, permissions, security, workflow and upgrade documentation.
- configurable read-only organization diagnostics on saved tickets;
- access-scoped, paginated cross-entity requester search;
- explicit multi-requester selection and target-entity choice;
- immutable relation-aware preview with concurrency fingerprint;
- controlled native ticket update with CSRF, IDOR and permission checks.
- final TicketOps visual identity and `ticketops` installable directory/key.

### Security

- no generic CRUD or REST exposure and no direct ticket-table write;
- cumulative plugin, ticket, source-entity and target-entity authorization;
- local briefs, prompts, audits and environment files excluded from Git and releases.
