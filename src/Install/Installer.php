<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Install;

use GlpiPlugin\Ticketops\Config;

final class Installer
{
    public function install(): bool
    {
        Config::installDefaults();

        return (new ProfileRightSynchronizer())->synchronize(true);
    }

    public function uninstall(): bool
    {
        Config::uninstall();

        return (new ProfileRightSynchronizer())->remove();
    }
}
