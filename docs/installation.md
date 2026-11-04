# Installation

TicketOps 0.1.12 requires GLPI 11.0.8 up to, but excluding, 11.1.0 and PHP 8.2 or newer.

1. Extract the release ZIP in GLPI's `plugins/` directory.
2. Ensure the resulting path is exactly `plugins/ticketops`.
3. In **Setup > Plugins**, install and activate TicketOps.
4. Review the two TicketOps rights on each central profile before production use.
5. Open the plugin configuration page and enable only the required modules. Requester/entity correction is disabled by default.

Uninstallation removes the plugin rights. This release creates no plugin table and stores no business data.

## Repeated startup warnings

`Unable to load plugin "ticketops" information` is emitted by GLPI when it cannot find or read the plugin's `setup.php`. Check that the deployed directory is named exactly `ticketops` and that `setup.php` is readable by both the web server and the account running GLPI cron. Check both configured locations, `plugins/` and `marketplace/`, and retain one complete installation. Do not delete a directory until the intended installation has been identified and backed up.

If GLPI reports that it cannot find `GlpiPlugin\Ticketops\Controller\ExecuteOperationController`, check for an old or mixed deployment. The installable key, namespace and archive root were aligned in version 0.0.2. Replace the complete plugin directory with a current release; avoid overlaying old files or renaming it to `ticketoperations` or `glpi-ticket-ops`.

After correcting the deployment, clear GLPI's cache using `php bin/console cache:clear` from the GLPI root, under the normal GLPI service account, then review the plugin's installation and activation state in **Setup > Plugins**. Verify a new ticket-page request and a normal cron run produce no new occurrences. Historic log entries remain until normal log rotation.

These warnings occur during GLPI startup, before TicketOps can handle them. Keep GLPI error reporting enabled and fix the missing files or incorrect deployment rather than suppressing the warnings.
