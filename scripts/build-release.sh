#!/usr/bin/env bash
set -euo pipefail

PLUGIN_KEY='ticketoperations'
REPOSITORY_NAME='glpi-ticket-operations'
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/build"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_KEY}"

cd "${ROOT_DIR}"
PLUGIN_VERSION="$(sed -n "s/^const PLUGIN_TICKETOPERATIONS_VERSION = '\([^']*\)';/\1/p" setup.php)"
[[ -n "${PLUGIN_VERSION}" ]] || { echo 'Unable to read plugin version' >&2; exit 1; }
if [[ -n "${TAG_NAME}" && ! "${TAG_NAME}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo 'Expected an optional vX.Y.Z release tag' >&2
  exit 1
fi
VERSION="${TAG_NAME#v}"
[[ -n "${VERSION}" ]] || VERSION="${PLUGIN_VERSION}"
[[ "${VERSION}" == "${PLUGIN_VERSION}" ]] || {
  echo "Version mismatch: ${VERSION} != ${PLUGIN_VERSION}" >&2
  exit 1
}
ARCHIVE="${DIST_DIR}/${REPOSITORY_NAME}-${VERSION}.zip"

for command in composer node php python3 rg rsync; do
  command -v "${command}" >/dev/null 2>&1 || { echo "Missing command: ${command}" >&2; exit 1; }
done

GETTEXT_AVAILABLE=true
for command in xgettext msgmerge msgfmt msgattrib; do
  command -v "${command}" >/dev/null 2>&1 || GETTEXT_AVAILABLE=false
done

composer validate --strict --no-check-publish
composer quality
node --check public/js/ticketoperations.js
php -r '$xml = simplexml_load_file("ticketoperations.xml"); exit($xml === false ? 1 : 0);'

if [[ "${GETTEXT_AVAILABLE}" == true ]]; then
  vendor/bin/extract-locales
  sed -i "s/Project-Id-Version: PACKAGE VERSION/Project-Id-Version: Ticket Operations ${VERSION}/" locales/ticketoperations.pot locales/en_GB.po
  msgattrib --clear-fuzzy --output-file=locales/en_GB.po locales/en_GB.po
  msgmerge --no-fuzzy-matching locales/fr_FR.po locales/ticketoperations.pot -o locales/fr_FR.po.new
  mv locales/fr_FR.po.new locales/fr_FR.po
  msgfmt --check --check-format --statistics -o locales/en_GB.mo locales/en_GB.po
  msgfmt --check --check-format --statistics -o locales/fr_FR.mo locales/fr_FR.po
  for locale in en_GB fr_FR; do
    if msgattrib --untranslated "locales/${locale}.po" | rg -q '^msgid '; then
      echo "${locale} catalog contains untranslated messages" >&2
      exit 1
    fi
  done
else
  echo 'GNU gettext unavailable; validating the committed catalogs' >&2
  for locale in en_GB fr_FR; do
    [[ -s "locales/${locale}.po" && -s "locales/${locale}.mo" ]] || {
      echo "Missing catalog for ${locale}" >&2
      exit 1
    }
    rg -Fq "Project-Id-Version: Ticket Operations ${VERSION}" "locales/${locale}.po" || {
      echo "${locale} catalog version does not match ${VERSION}" >&2
      exit 1
    }
    rg -q '^#, fuzzy' "locales/${locale}.po" && {
      echo "${locale} catalog contains fuzzy translations" >&2
      exit 1
    }
  done
  [[ -s locales/ticketoperations.pot ]] || { echo 'Missing catalog template' >&2; exit 1; }
  python3 - <<'PY'
import ast
import pathlib

for locale in ('en_GB', 'fr_FR'):
    messages = {}
    current_id = current_value = mode = None

    def flush():
        global current_id, current_value
        if current_id:
            messages[current_id] = current_value
        current_id = current_value = None

    for raw in pathlib.Path(f'locales/{locale}.po').read_text(encoding='utf-8').splitlines() + ['']:
        line = raw.strip()
        if line.startswith('msgid '):
            flush()
            current_id = ast.literal_eval(line[6:])
            current_value = ''
            mode = 'id'
        elif line.startswith('msgstr '):
            current_value = ast.literal_eval(line[7:])
            mode = 'str'
        elif line.startswith('"'):
            if mode == 'id':
                current_id += ast.literal_eval(line)
            elif mode == 'str':
                current_value += ast.literal_eval(line)
        elif not line:
            flush()
            mode = None
    untranslated = sorted(key for key, value in messages.items() if not value)
    if untranslated:
        raise SystemExit(f'{locale} catalog contains untranslated messages: {untranslated}')
PY
fi

rm -rf "${DIST_DIR}"
mkdir -p "${PACKAGE_DIR}"

release_entries=(
  setup.php hook.php composer.json composer.lock ticketoperations.xml
  LICENSE README.md CHANGELOG.md SECURITY.md CONTRIBUTING.md
  src public locales docs
)
for entry in "${release_entries[@]}"; do
  [[ -e "${entry}" ]] || { echo "Missing release entry: ${entry}" >&2; exit 1; }
  rsync -a --exclude '.*' --exclude '*~' --exclude '*.bak' --exclude '*.orig' --exclude '*.rej' "${entry}" "${PACKAGE_DIR}/"
done

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"
find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

ARCHIVE="${ARCHIVE}" BUILD_DIR="${BUILD_DIR}" PLUGIN_KEY="${PLUGIN_KEY}" python3 - <<'PY'
import os
import pathlib
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
build_dir = pathlib.Path(os.environ['BUILD_DIR'])
plugin_key = os.environ['PLUGIN_KEY']
plugin_dir = build_dir / plugin_key

with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in sorted(plugin_dir.rglob('*')):
        if path.is_file():
            output.write(path, path.relative_to(build_dir))

with zipfile.ZipFile(archive) as package:
    names = set(package.namelist())
    required = {
        f'{plugin_key}/setup.php', f'{plugin_key}/hook.php',
        f'{plugin_key}/composer.json', f'{plugin_key}/ticketoperations.xml',
        f'{plugin_key}/public/css/ticketoperations.css',
        f'{plugin_key}/public/js/ticketoperations.js',
        f'{plugin_key}/public/icon.png', f'{plugin_key}/public/logo.png',
        f'{plugin_key}/locales/en_GB.mo', f'{plugin_key}/locales/fr_FR.mo',
        f'{plugin_key}/locales/ticketoperations.pot',
        f'{plugin_key}/vendor/autoload.php',
    }
    missing = required - names
    if missing:
        raise SystemExit(f'Missing required entries: {sorted(missing)}')
    if any(not name.startswith(f'{plugin_key}/') for name in names):
        raise SystemExit('Invalid archive root')
    forbidden_parts = ('/.git/', '/.local/', '/.agents/', '/.codex/', '/tests/', '/scripts/', '/dist/')
    forbidden_files = ('/.gitignore', '/AGENTS.md', '/phpunit.xml', '/phpstan.neon', '/.php-cs-fixer.php')
    if any(any(part in name for part in forbidden_parts)
           or any(name.endswith(part) for part in forbidden_files)
           or name.endswith(('~', '.bak', '.orig', '.rej', '.swp', '.swo'))
           for name in names):
        raise SystemExit('Development or forbidden files found in archive')

print(f'Verified {archive}: {len(names)} entries')
PY

echo "${ARCHIVE}"
