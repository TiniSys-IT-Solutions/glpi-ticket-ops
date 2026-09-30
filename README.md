<p align="center">
  <img src="public/logo.png" alt="TicketOps" width="220">
</p>

<h1 align="center">TicketOps</h1>

<p align="center">
  <a href="https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/releases"><img src="https://img.shields.io/badge/version-0.0.1-0ea5e9" alt="Version 0.0.1"></a>
  <img src="https://img.shields.io/badge/GLPI-11.0.8%E2%80%93%3C11.1.0-0b7285" alt="GLPI 11.0.8 to below 11.1.0">
  <img src="https://img.shields.io/badge/PHP-%E2%89%A58.2-777bb4" alt="PHP 8.2 or newer">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--3.0--or--later-16a34a" alt="GPL-3.0-or-later"></a>
  <a href="https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/actions/workflows/quality.yml"><img src="https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/actions/workflows/quality.yml/badge.svg" alt="Quality"></a>
</p>

TicketOps is a GLPI 11 plugin for safe, previewed and controlled operations on existing tickets.

> Faciliter la qualification, l’organisation et l’affectation des tickets GLPI sans contourner le modèle multi-entités.

Version `0.0.1` is the installable foundation. It installs profile rights, translations and assets but does not display a ticket action or modify ticket data.

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

The installable directory must be named exactly `ticketoperations`. See [installation](docs/installation.md), [architecture](docs/architecture.md), [permissions](docs/permissions.md), [security](docs/security.md) and [ticket workflow](docs/ticket-workflow.md).

Screenshots will be added when the read-only diagnostic UI is introduced in `0.0.2`.

## Project links

- [Releases](https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/releases)
- [Issues](https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops/issues)
- [Security policy](SECURITY.md)
- [Changelog](CHANGELOG.md)
