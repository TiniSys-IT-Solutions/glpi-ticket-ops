<p align="center">
  <img src="public/logo.png" alt="TicketOps" width="220">
</p>

<h1 align="center">TicketOps</h1>

<p align="center">
  <a href="https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/releases"><img src="https://img.shields.io/badge/version-0.1.7-0ea5e9" alt="Version 0.1.7"></a>
  <img src="https://img.shields.io/badge/GLPI-11.0.8%E2%80%93%3C11.1.0-0b7285" alt="GLPI 11.0.8 to below 11.1.0">
  <img src="https://img.shields.io/badge/PHP-%E2%89%A58.2-777bb4" alt="PHP 8.2 or newer">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--3.0--or--later-16a34a" alt="GPL-3.0-or-later"></a>
  <a href="https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/actions/workflows/quality.yml"><img src="https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/actions/workflows/quality.yml/badge.svg" alt="Quality"></a>
</p>

TicketOps is a GLPI 11 plugin for safe, previewed and controlled operations on existing tickets.

> Faciliter la qualification, l’organisation et l’affectation des tickets GLPI sans contourner le modèle multi-entités.

Version `0.1.7` is the hardened preproduction baseline: native GLPI selectors, requester/entity intersection, immutable previews, relation-preserving updates and focused organization diagnostics without informational noise.

Diagnostics, requester/entity correction, organization and quick assignment can be enabled independently from the TicketOps configuration page. Diagnostics are enabled by default; every mutation module remains disabled until an administrator explicitly enables it.

## Usage

On a saved ticket, the **Ticket organization** panel reports requester, assignment, category, location and entity consistency. Authorized technicians can open **Reorganize ticket**, optionally replace a requester, choose a compatible target entity and explicitly update category, location, technician or observer. Native GLPI selectors are scoped to the target entity, the complete operation is previewed, and a ticket modified after preview is refused without mutation.

## Security commitments

TicketOps:

- grants no new authorization to requesters;
- never bypasses entity isolation;
- does not make all users globally visible;
- does not replace GLPI business rules;
- helps an already-authorized technician perform a combined, previewed and controlled operation.

## Requirements

- GLPI 11.0.8 through versions strictly below 11.1.0;
- PHP 8.2 or newer;
- Composer 2, Node.js and GNU gettext for development and releases.

## Development

```bash
composer install
composer quality
./scripts/build-release.sh
```

The installable directory must be named exactly `ticketops`. See [installation](docs/installation.md), [architecture](docs/architecture.md), [permissions](docs/permissions.md), [security](docs/security.md) and [ticket workflow](docs/ticket-workflow.md).

The operation is limited to Ticket in the central interface. Change and Problem are intentionally unsupported.

## Project links

- [Releases](https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/releases)
- [Issues](https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/issues)
- [Security policy](SECURITY.md)
- [Changelog](CHANGELOG.md)
