<?php

declare(strict_types=1);

use GlpiPlugin\Ticketoperations\Install\Installer;

function plugin_ticketoperations_install(): bool
{
    return (new Installer())->install();
}

function plugin_ticketoperations_uninstall(): bool
{
    return (new Installer())->uninstall();
}
