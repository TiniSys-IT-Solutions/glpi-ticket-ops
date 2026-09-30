# Contributing

1. Read `AGENTS.md` and the architecture/security documentation.
2. Use only synthetic test data and keep working material in ignored `.local/`.
3. Verify every GLPI API against the local GLPI 11.0.8 sources.
4. Add unit tests for pure logic and native GLPI integration tests for runtime behavior.
5. Run `composer quality` and `./scripts/build-release.sh`.
6. Inspect `git status`, `git ls-files` and the ZIP before publishing.

Do not run `git add .` blindly. Do not commit, push or tag without explicit authorization.

