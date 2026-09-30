# Upgrade

Before every upgrade:

1. back up GLPI according to the GLPI administration guide;
2. review `CHANGELOG.md` and compatibility bounds;
3. replace the plugin directory with the release archive contents;
4. run the GLPI plugin upgrade action;
5. review central-profile rights and execute the documented checks.

Do not copy development dependencies, `.local/`, tests or source-control metadata into the production plugin directory.

