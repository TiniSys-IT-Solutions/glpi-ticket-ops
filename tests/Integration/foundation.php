<?php

declare(strict_types=1);

$root = getenv('TICKETOPERATIONS_TEST_GLPI_ROOT');
if (!$root || !is_file($root . '/.ticketops-disposable-test')) {
    fwrite(STDERR, "Set TICKETOPERATIONS_TEST_GLPI_ROOT to an explicitly marked disposable GLPI 12.0.1 installation.\n");
    exit(2);
}
session_save_path($root . '/files/_sessions');
require $root . '/vendor/autoload.php';
$kernel = new Glpi\Kernel\Kernel();
$kernel->boot();
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (GLPI_VERSION !== '12.0.1') {
    throw new RuntimeException('Integration tests require exactly GLPI 12.0.1.');
}
$rights = array_column(GlpiPlugin\Ticketops\Profile::rights(), 'field');
if (!(new GlpiPlugin\Ticketops\Install\Installer())->install()) {
    throw new RuntimeException('Plugin installation failed.');
}
foreach ($DB->request(['SELECT' => ['id'], 'FROM' => Profile::getTable()]) as $profile) {
    $stored = ProfileRight::getProfileRights((int) $profile['id'], $rights);
    if (array_diff($rights, array_keys($stored)) !== []) {
        throw new RuntimeException('Profile rights were not synchronized.');
    }
}
if (!(new GlpiPlugin\Ticketops\Install\Installer())->uninstall()) {
    throw new RuntimeException('Plugin uninstallation failed.');
}
foreach ($rights as $right) {
    if (countElementsInTable(ProfileRight::getTable(), ['name' => $right]) !== 0) {
        throw new RuntimeException('Plugin right remains after uninstall.');
    }
}
echo "PASS: GLPI 12.0.1 install, profile-right synchronization and uninstall\n";
