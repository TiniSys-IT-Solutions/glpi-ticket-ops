# Ticket workflow

## Ticket organization

The Ticket-only section uses GLPI 11's `post_itil_info_section` hook. It appears only for a saved, accessible ticket and distinguishes success, information, warning and blocking findings.

## Controlled correction

Requester correction is optional. When it is used, the modal requires an explicit existing requester and replacement. GLPI's native user selector is restricted to the operator's active entities; after selection, TicketOps reduces the target-entity selector to the intersection between the requester's effective profile entities and the operator's active entities. Inactive, deleted, expired and system accounts are rejected again by the server-side plan builder.

The dialog displays the ticket number and title in its truncated header, an editable title when the organization module is enabled, and the current entity name beside the entity replacement choice. Requester correction uses the same flat section layout as optional ticket organization.

Apply runs internal preparation and execution consecutively, without a separate Preview button or result summary. Blocking issues are displayed in the dialog. Incompatible relations still require an explicit removal choice followed by another Apply. Controls are locked during submission and repeated clicks are ignored.

Preparation and execution use the same immutable plan builder. The plan fingerprints the title, status, scalar native ticket fields, actors and an inventory of native linked records. The detailed inventory is loaded for preparation and execution only, not for routine diagnostic rendering. Execution takes a transactional read lock on the ticket, reloads permissions and ticket state, rebuilds the plan, rejects stale preparations, and submits one native `Ticket::update()` containing the target entity, an explicitly changed title and final actor set. Incompatible removable relations require an explicit removal choice identified by kind, item type, actor role and link ID. Explicit requester, technician, category or location replacements do not require a redundant removal confirmation.

A changed title must contain between 1 and 255 characters, matching the native GLPI title field. It is included in the immutable plan and its one-use token; an unchanged title is omitted from the update.

The workflow can also carry explicit category, location, technician and observer choices. Each option uses a native GLPI selector and is revalidated for the target entity on the server. A selected observer is appended without removing existing observers. Omitted organization fields retain their current value; TicketOps never infers an unrequested replacement.

Starting with 0.1.0, organization does not require a requester replacement. “Assign to me and start” produces a normal preview that explicitly selects the connected technician and GLPI's assigned status before execution. It follows the same native permission checks and is not a direct mutation shortcut.

## Linked records during an entity change

TicketOps sends one native `Ticket::update()` containing only the target entity, explicitly selected organization fields and the final actor collections. It never submits replacement input for associated assets, costs, contracts, problems, changes, projects, linked tickets, documents, followups, tasks, solutions or plugin-owned relations. Their relation identifiers therefore remain owned by GLPI and are not deleted by TicketOps.

GLPI forwards the new ticket entity to native child records registered through its `forward_entity_to` mechanism, including ticket costs and validations. Third-party plugins can register their own entity-bearing child records through the same GLPI mechanism. Unknown plugin relations are deliberately left untouched rather than guessed or rewritten.

Associated items stored in `glpi_items_tickets` are additionally checked against the target entity during preparation. An incompatible associated item is a non-removable blocker: TicketOps refuses execution instead of silently unlinking it.

Document links are inventoried using GLPI's native `getAssociatedDocumentsCriteria(true)`, including attachments on followups, tasks, solutions and validations. This server-side inventory does not expose private document content or titles in the dialog. An attachment exclusively linked to this ticket and its children retains its links and follows native ticket access. A document shared with another record is blocked on transfer when it is outside the target entity and recursive scope. TicketOps never copies, moves or unlinks documents automatically.

Followups, tasks, solutions, validations, costs and links to contracts, problems, changes, project tasks and other tickets are included in the concurrency inventory. The plugin never rewrites those relationships or guesses third-party plugin data. Existing links to other native records retain their current transfer policy; an additional out-of-scope blocking policy has not been enabled.

Native ticket UPDATE is required in addition to the plugin right and ticket/entity checks. Technician changes use GLPI assignment or self-assignment capabilities; the quick action must select the connected user and an allowed status transition. User eligibility uses GLPI's native validity checks; technician candidates must have the appropriate ticket rights in the target entity. Closed tickets can retain native entity/category/location corrections, but title or actor changes that GLPI would filter are rejected explicitly.

Target gabarit checks inspect explicitly submitted fields and removals. Unchanged missing fields are left to the native update and ONUPDATE rules, so the planner does not introduce new mandatory inputs for unrelated organization corrections.

Native `ONUPDATE` rules and third-party update hooks still execute as part of `Ticket::update()`. Their independently configured effects cannot be guaranteed by TicketOps and must be included in disposable-instance testing before production use.

GLPI remains responsible for validation, history, hooks, its native `ONUPDATE` business rules and final notifications. The internal plan records that these rules run; TicketOps requests no additional replay and does not disable notifications globally.
