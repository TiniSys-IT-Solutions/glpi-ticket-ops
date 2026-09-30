<?php

declare(strict_types=1);

$root = getenv('TICKETOPERATIONS_TEST_GLPI_ROOT');
if (!$root || !is_file($root . '/.ticketoperations-disposable-test')) {
    fwrite(STDERR, "Set TICKETOPERATIONS_TEST_GLPI_ROOT to an explicitly marked disposable GLPI 11.0.8 installation.\n");
    exit(2);
}
session_save_path($root . '/files/_sessions');
require $root . '/vendor/autoload.php';
$kernel = new Glpi\Kernel\Kernel();
$kernel->boot();
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (GLPI_VERSION !== '11.0.8') {
    throw new RuntimeException('Integration tests require exactly GLPI 11.0.8.');
}
$rights = array_column(GlpiPlugin\Ticketoperations\Profile::rights(), 'field');
if (!(new GlpiPlugin\Ticketoperations\Install\Installer())->install()) {
    throw new RuntimeException('Plugin installation failed.');
}
foreach ($DB->request(['SELECT' => ['id'], 'FROM' => Profile::getTable()]) as $profile) {
    $stored = ProfileRight::getProfileRights((int) $profile['id'], $rights);
    if (array_diff($rights, array_keys($stored)) !== []) {
        throw new RuntimeException('Profile rights were not synchronized.');
    }
}
if (!(new GlpiPlugin\Ticketoperations\Install\Installer())->uninstall()) {
    throw new RuntimeException('Plugin uninstallation failed.');
}
foreach ($rights as $right) {
    if (countElementsInTable(ProfileRight::getTable(), ['name' => $right]) !== 0) {
        throw new RuntimeException('Plugin right remains after uninstall.');
    }
}
echo "PASS: GLPI 11.0.8 install, profile-right synchronization and uninstall\n";
