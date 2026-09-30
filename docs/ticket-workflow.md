# Ticket workflow

## Foundation 0.0.1

No button or ticket operation is displayed. The profile rights and static assets are installed for later milestones.

## Planned read-only diagnostic 0.0.2

A Ticket-only section named **Ticket organization** will use GLPI 11's `post_itil_info_section` hook. It will appear only for a saved, accessible ticket and will distinguish success, information, warning and blocking findings.

The hook is preferred over editing a core template. A future JavaScript enhancement may move a control closer to the entity badge, but the original hook location must remain fully usable.

