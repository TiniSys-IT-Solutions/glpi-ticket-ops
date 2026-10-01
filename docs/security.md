# Security model

TicketOps does not grant requesters new access, bypass entity isolation, expose all users globally or replace GLPI business rules.

The three attributed endpoints require the authenticated central strategy. Mutations are POST-only and GLPI's controller listener checks the `X-Glpi-Csrf-Token` token. Controllers reload the ticket, validate the plugin right, native view/update rights and source/target entity access, and return bounded errors.

Requester selection uses GLPI's native active-entity restriction. The selected account is then resolved server-side against its status, validity dates, effective recursive profile entities and the operator's active entities. Browser state is input, never authorization. Execution rebuilds the plan, compares the ticket-state fingerprint, and consumes a short-lived, one-use server-side token bound to the complete previewed plan and current user. Generic CRUD, massive actions and API exposure are not enabled.
