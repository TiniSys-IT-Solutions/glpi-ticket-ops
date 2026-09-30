# Security policy

Do not report vulnerabilities containing sensitive details in a public issue. Contact the TiniSys IT Solutions maintainers through an agreed private channel.

TicketOps combines its own profile rights with native GLPI ticket, entity and actor permissions. Client-side checks are never security boundaries. The project does not call GLPI's REST API internally and does not write directly to ticket or actor tables.

Never include GenBio production data, infrastructure details, exports, captures, logs or credentials in reports or fixtures.
