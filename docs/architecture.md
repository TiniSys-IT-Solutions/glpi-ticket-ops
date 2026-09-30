# Architecture

## Foundation

The plugin uses the PSR-4 namespace `GlpiPlugin\Ticketoperations`. `setup.php` registers assets and the profile tab; `hook.php` delegates installation to `src/Install`. Rights remain in GLPI's native profile-right storage, so no plugin database exists.

The source folders reflect intended dependency direction:

- `Controller`: thin HTTP adapters using GLPI 11 attributed routes;
- `Ui`: official hook rendering and progressive enhancement;
- `Service`: application orchestration and GLPI adapters;
- `Security`: authorization and entity-scope policies;
- `Domain`: immutable plans and findings independent of HTTP;
- `Install`: lifecycle and profile-right synchronization.

Only `Install` is populated in 0.0.1. Empty layers are retained as named directories during development but are not packaged until they contain runtime code.

## Confirmed GLPI 11 integration points

- `post_itil_info_section` is reserved for the 0.0.2 Ticket-only diagnostic block.
- Controllers under `src/Controller` are automatically discovered by `Glpi\Routing\PluginRoutesLoader`.
- GLPI prefixes plugin routes with `/plugins/ticketoperations/` or the marketplace equivalent.
- Controller mutations will be POST-only and rely on GLPI's `CheckCsrfListener`.

## Deliberate omissions

There is no menu, configuration page, ticket route, plugin table, legacy `front/` endpoint or Twig template. Each would be speculative or enlarge the attack surface before a feature requires it.
