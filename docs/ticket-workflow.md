# Ticket workflow

## Ticket organization

The Ticket-only section uses GLPI 11's `post_itil_info_section` hook. It appears only for a saved, accessible ticket and distinguishes success, information, warning and blocking findings.

## Controlled correction

Requester correction is optional. When it is used, the modal requires an explicit existing requester and replacement. GLPI's native user selector is restricted to the operator's active entities; after selection, TicketOps reduces the target-entity selector to the intersection between the requester's effective profile entities and the operator's active entities. Inactive, deleted, expired and system accounts are rejected again by the server-side plan builder.

Preview and execution use the same immutable plan builder. The plan fingerprints ticket fields and actors. Execution reloads permissions and ticket state, rebuilds the plan, rejects stale previews, and submits one native `Ticket::update()` containing the target entity and final actor set. Incompatible relations require an explicit removal choice.

The workflow can also carry explicit category, location, technician and observer choices. Each option uses a native GLPI selector and is revalidated for the target entity on the server. A selected observer is appended without removing existing observers. Omitted organization fields retain their current value; TicketOps never infers an unrequested replacement.

Starting with 0.1.0, organization does not require a requester replacement. “Assign to me and start” produces a normal preview that explicitly selects the connected technician and GLPI's assigned status before execution. It is not a direct mutation shortcut.

## Linked records during an entity change

TicketOps sends one native `Ticket::update()` containing only the target entity, explicitly selected organization fields and the final actor collections. It never submits replacement input for associated assets, costs, contracts, problems, changes, projects, linked tickets, documents, followups, tasks, solutions or plugin-owned relations. Their relation identifiers therefore remain owned by GLPI and are not deleted by TicketOps.

GLPI forwards the new ticket entity to native child records registered through its `forward_entity_to` mechanism, including ticket costs and validations. Third-party plugins can register their own entity-bearing child records through the same GLPI mechanism. Unknown plugin relations are deliberately left untouched rather than guessed or rewritten.

Associated items stored in `glpi_items_tickets` are additionally checked against the target entity during preview. An incompatible associated item is a non-removable blocker: TicketOps refuses execution instead of silently unlinking it.

Native `ONUPDATE` rules and third-party update hooks still execute as part of `Ticket::update()`. Their independently configured effects cannot be guaranteed by TicketOps and must be included in disposable-instance testing before production use.

GLPI remains responsible for validation, history, hooks, its native `ONUPDATE` business rules and final notifications. The preview states that these rules run; TicketOps requests no additional replay and does not disable notifications globally.
