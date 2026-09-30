# Ticket workflow

## Ticket organization

The Ticket-only section uses GLPI 11's `post_itil_info_section` hook. It appears only for a saved, accessible ticket and distinguishes success, information, warning and blocking findings.

## Controlled correction

The modal requires an explicit existing requester, a requester replacement and a target entity. Search results are paginated and reduced to the intersection between the user's effective profile entities and the technician's active entities. Inactive, deleted, expired and system accounts are excluded.

Preview and execution use the same immutable plan builder. The plan fingerprints ticket fields and actors. Execution reloads permissions and ticket state, rebuilds the plan, rejects stale previews, and submits one native `Ticket::update()` containing the target entity and final actor set. Incompatible relations require an explicit removal choice; TicketOps never chooses a replacement group.

The 0.0.5 workflow can also carry explicit category, location, technician group and technician choices. Each option is searched and revalidated for the target entity on the server. Omitted organization fields retain their current value; TicketOps never infers an unrequested replacement.

Starting with 0.1.0, organization does not require a requester replacement. “Assign to me and start” produces a normal preview that explicitly selects the connected technician and GLPI's assigned status before execution. It is not a direct mutation shortcut.

GLPI remains responsible for validation, history, hooks, its native `ONUPDATE` business rules and final notifications. The preview states that these rules run; TicketOps requests no additional replay and does not disable notifications globally.
