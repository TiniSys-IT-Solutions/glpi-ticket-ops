# Security model

TicketOps does not grant requesters new access, bypass entity isolation, expose all users globally or replace GLPI business rules.

The attributed endpoints require the authenticated central strategy. Mutations are POST-only and GLPI's controller listener checks the `X-Glpi-Csrf-Token` token. Controllers reload the ticket, validate the plugin right, native view/update rights and source/target entity access, and return bounded errors.

Requester selection uses GLPI's native active-entity restriction. The selected account is then resolved server-side against its status, validity dates, effective recursive profile entities and the operator's active entities. Browser state is input, never authorization. Execution consumes a short-lived, one-use server-side token bound to the complete previewed plan and current user. It then locks the ticket row in a transaction and rebuilds the same plan with current permissions and linked-record state before one native update.

Requester replacement and relation removals use typed identities; numeric actor IDs from different link tables cannot authorize each other. Native ticket UPDATE is required. Technician assignment, self-assignment and status changes are validated separately using GLPI capabilities, and the quick action cannot assign another user. Shared out-of-scope attachments block transfer without deletion or migration.

Generic CRUD, massive actions and API exposure are not enabled.
