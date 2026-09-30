<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketoperations\Install;

final class Installer
{
    public function install(): bool
    {
        return (new ProfileRightSynchronizer())->synchronize(true);
    }

    public function uninstall(): bool
    {
        return (new ProfileRightSynchronizer())->remove();
    }
}
