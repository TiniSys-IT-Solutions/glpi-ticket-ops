# Native GLPI integration test

This runner mutates profile-right rows and may run only against a disposable GLPI 11.0.8 installation containing synthetic data.

1. Install GLPI 11.0.8 in a disposable database.
2. Place this plugin at `plugins/ticketoperations` and run `composer install` in the plugin.
3. Create `.ticketoperations-disposable-test` at the GLPI root.
4. Run `TICKETOPERATIONS_TEST_GLPI_ROOT=/absolute/disposable/glpi php tests/Integration/foundation.php`.

The runner performs native installation, checks every profile received both rights, uninstalls, and verifies cleanup. It is not a string-search contract test.
