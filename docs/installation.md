# Installation

TicketOps 0.0.1 requires GLPI 11.0.8 up to, but excluding, 11.1.0 and PHP 8.2 or newer.

1. Extract the release ZIP in GLPI's `plugins/` directory.
2. Ensure the resulting path is exactly `plugins/ticketoperations`.
3. In **Setup > Plugins**, install and activate TicketOps.
4. Review the two TicketOps rights on each central profile before production use.

Uninstallation removes the plugin rights. This release creates no plugin table and stores no business data.
