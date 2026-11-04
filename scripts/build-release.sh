#!/usr/bin/env bash
set -euo pipefail

PLUGIN_KEY='ticketops'
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}"
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
ARCHIVE="${DIST_DIR}/${PLUGIN_KEY}-${VERSION}.zip"

# Stable translation headers and ZIP metadata for the same source and toolchain.
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-$(git log -1 --format=%ct)}"
[[ "${SOURCE_DATE_EPOCH}" =~ ^[0-9]+$ ]] || { echo 'Invalid SOURCE_DATE_EPOCH' >&2; exit 1; }
export SOURCE_DATE_EPOCH

for command in composer node php python3 rg rsync xgettext msgmerge msgfmt msgattrib msgfilter msgcmp; do
  command -v "${command}" >/dev/null 2>&1 || { echo "Missing command: ${command}" >&2; exit 1; }
done

composer validate --strict --no-check-publish
composer quality
node --check public/js/ticketoperations.js
php -r '$xml = simplexml_load_file("ticketops.xml"); exit($xml === false ? 1 : 0);'

rm -rf "${DIST_DIR}"
vendor/bin/extract-locales
msgfilter --keep-header -i locales/en_GB.po -o locales/ticketops.pot true
rm -f locales/ticketoperations.pot
sed -i "s/Project-Id-Version: PACKAGE VERSION/Project-Id-Version: TicketOps ${VERSION}/" locales/ticketops.pot locales/en_GB.po
msgattrib --clear-fuzzy --output-file=locales/en_GB.po locales/en_GB.po
msgmerge --no-fuzzy-matching locales/fr_FR.po locales/ticketops.pot -o locales/fr_FR.po.new
mv locales/fr_FR.po.new locales/fr_FR.po
msgattrib --no-obsolete --output-file=locales/en_GB.po locales/en_GB.po
msgattrib --no-obsolete --output-file=locales/fr_FR.po locales/fr_FR.po
VERSION="${VERSION}" python3 - <<'PY'
import datetime
import os
import pathlib
import re

stamp = datetime.datetime.fromtimestamp(int(os.environ['SOURCE_DATE_EPOCH']), datetime.timezone.utc).strftime('%Y-%m-%d %H:%M+0000')
for name in ('en_GB.po', 'fr_FR.po', 'ticketops.pot'):
    path = pathlib.Path('locales') / name
    content = re.sub(r'(?m)^"(POT-Creation-Date|PO-Revision-Date): [^"\n]*\\n"$',
                     lambda match: '"' + match[1] + ': ' + stamp + '\\n"', path.read_text())
    content = re.sub(r'(?m)^"Project-Id-Version: [^"\n]*\\n"$',
                     lambda match: '"Project-Id-Version: TicketOps ' + os.environ['VERSION'] + '\\n"', content)
    for field, value in (('PO-Revision-Date', stamp), ('Last-Translator', 'TiniSys IT Solutions'), ('Language-Team', 'TiniSys IT Solutions')):
        if f'"{field}:' not in content:
            content = content.replace('"Report-Msgid-Bugs-To: \\n"', '"Report-Msgid-Bugs-To: \\n"\n' + '"' + field + ': ' + value + '\\n"')
    path.write_text(content)
PY
msgfmt --check --check-format --statistics -o locales/en_GB.mo locales/en_GB.po
msgfmt --check --check-format --statistics -o locales/fr_FR.mo locales/fr_FR.po
msgcmp --use-fuzzy locales/en_GB.po locales/ticketops.pot
msgcmp --use-fuzzy locales/fr_FR.po locales/ticketops.pot
for locale in en_GB fr_FR; do
  if msgattrib --untranslated "locales/${locale}.po" | rg -q '^msgid '; then
    echo "${locale} catalog contains untranslated messages" >&2
    exit 1
  fi
done

mkdir -p "${PACKAGE_DIR}"

release_entries=(
  setup.php hook.php composer.json composer.lock ticketops.xml
  LICENSE README.md CHANGELOG.md SECURITY.md CONTRIBUTING.md
  src front public locales docs
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
php scripts/check-bootstrap.php "${PACKAGE_DIR}" composer
php scripts/check-bootstrap.php "${PACKAGE_DIR}" native
find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

ARCHIVE="${ARCHIVE}" BUILD_DIR="${BUILD_DIR}" PLUGIN_KEY="${PLUGIN_KEY}" python3 - <<'PY'
import os
import datetime
import pathlib
import re
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
build_dir = pathlib.Path(os.environ['BUILD_DIR'])
plugin_key = os.environ['PLUGIN_KEY']
plugin_dir = build_dir / plugin_key
timestamp = datetime.datetime.fromtimestamp(max(315532800, int(os.environ['SOURCE_DATE_EPOCH'])), datetime.timezone.utc)

with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in sorted(plugin_dir.rglob('*')):
        if path.is_file():
            entry = zipfile.ZipInfo(path.relative_to(build_dir).as_posix(), timestamp.timetuple()[:6])
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            output.writestr(entry, path.read_bytes())

with zipfile.ZipFile(archive) as package:
    names = set(package.namelist())
    required = {
        f'{plugin_key}/setup.php', f'{plugin_key}/hook.php',
        f'{plugin_key}/composer.json', f'{plugin_key}/ticketops.xml',
        f'{plugin_key}/front/config.php',
        f'{plugin_key}/public/css/ticketoperations.css',
        f'{plugin_key}/public/js/ticketoperations.js',
        f'{plugin_key}/public/icon.png', f'{plugin_key}/public/logo.png',
        f'{plugin_key}/locales/en_GB.mo', f'{plugin_key}/locales/fr_FR.mo',
        f'{plugin_key}/locales/ticketops.pot',
        f'{plugin_key}/vendor/autoload.php',
    }
    missing = required - names
    if missing:
        raise SystemExit(f'Missing required entries: {sorted(missing)}')
    if any(not name.startswith(f'{plugin_key}/') for name in names):
        raise SystemExit('Invalid archive root')
    setup = package.read(f'{plugin_key}/setup.php').decode()
    version = re.search(r"const PLUGIN_TICKETOPERATIONS_VERSION = '([^']+)'", setup).group(1)
    if archive.name != f'{plugin_key}-{version}.zip':
        raise SystemExit('Archive name does not match the packaged version')
    if package.testzip() is not None:
        raise SystemExit('Corrupt archive entry')
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
