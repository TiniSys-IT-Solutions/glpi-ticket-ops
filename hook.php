<?php

declare(strict_types=1);

use GlpiPlugin\Ticketops\Install\Installer;

function plugin_ticketops_install(): bool
{
    return (new Installer())->install();
}

function plugin_ticketops_uninstall(): bool
{
    return (new Installer())->uninstall();
}
