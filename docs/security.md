# Security model

TicketOps does not grant requesters new access, bypass entity isolation, expose all users globally or replace GLPI business rules.

The three attributed endpoints require the authenticated central strategy. Mutations are POST-only and GLPI's controller listener checks the `X-Glpi-Csrf-Token` token. Controllers reload the ticket, validate the plugin right, native view/update rights and source/target entity access, and return bounded errors.

Requester search applies account status/dates, a maximum page size, effective recursive profile entities and the technician's active entities before returning a result. Browser state is input, never authorization. Execution rebuilds the plan and compares the ticket fingerprint. Generic CRUD, massive actions and API exposure are not enabled.
