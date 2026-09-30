# Architecture

## Foundation

The plugin uses the GLPI-key-aligned PSR-4 namespace `GlpiPlugin\Ticketops`. `setup.php` registers assets and the profile tab; `hook.php` delegates installation to `src/Install`. Rights remain in GLPI's native profile-right storage, so no plugin database exists.

The source folders reflect intended dependency direction:

- `Controller`: thin HTTP adapters using GLPI 11 attributed routes;
- `Ui`: official hook rendering and progressive enhancement;
- `Service`: application orchestration and GLPI adapters;
- `Security`: authorization and entity-scope policies;
- `Domain`: immutable plans and findings independent of HTTP;
- `Install`: lifecycle and profile-right synchronization.

All layers are populated. `TicketSnapshot` is the canonical normalized state shared by diagnostic, plan and concurrency checks. `TicketOperationPlan` is immutable and contains every warning, blocker, relation decision and expected side effect.

## Confirmed GLPI 11 integration points

- `post_itil_info_section` renders the Ticket-only diagnostic block and its stable fallback action.
- Controllers under `src/Controller` are automatically discovered by `Glpi\Routing\PluginRoutesLoader`.
- GLPI prefixes plugin routes with `/plugins/ticketops/` or the marketplace equivalent.
- Preview and execution are POST-only and rely on GLPI's `CheckCsrfListener`; search is read-only GET.

## Deliberate omissions

There is no menu, plugin table or generic CRUD model. A single guarded legacy configuration endpoint is justified by the need to enable each implemented functional module independently; it stores values through GLPI's native configuration API.

Ticket mutation is a single native `Ticket::update()` carrying the target entity and final actor lists inside a database transaction. GLPI performs its own consistency checks, history, hooks and notification. A database rollback cannot undo an already executed external hook; this limitation is why TicketOps performs every deterministic validation before update and does not add external side effects.

Organization option searches are handled by a dedicated authenticated controller. Category, location, assignment group and technician candidates are constrained to the selected target entity and revalidated by the immutable plan builder. The executor applies only explicitly selected changes in the same native ticket update as the requester and entity correction.
