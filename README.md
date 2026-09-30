# Ticket Operations (TicketOps)

Ticket Operations is a GLPI 11 plugin for safe, previewed and controlled operations on existing tickets.

> Faciliter la qualification, l’organisation et l’affectation des tickets GLPI sans contourner le modèle multi-entités.

Version `0.0.1` is the installable foundation. It installs profile rights, translations and assets but does not display a ticket action or modify ticket data.

## Security commitments

Ticket Operations:

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

