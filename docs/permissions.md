# Permissions

TicketOps defines two independent profile rights:

| Technical name | French label | Purpose |
|---|---|---|
| `plugin_ticketoperations_diagnostic` | Voir le diagnostic d’organisation des tickets | Read the future ticket organization diagnostic |
| `plugin_ticketoperations_requester_entity_switch` | Corriger le demandeur et l’entité d’un ticket | Execute the future combined correction |

The rights appear only on central profiles. Installation seeds central profiles with their respective `READ` and `UPDATE` bit; non-central profiles receive zero. Administrators must review these defaults.

These rights never replace native GLPI authorization. Future operations must also require access to the ticket, native update permission and access to both source and target entities and actors.
