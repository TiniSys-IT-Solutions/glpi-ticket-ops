<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Install;

use GlpiPlugin\Ticketops\Profile as TicketOpsProfile;

final class ProfileRightSynchronizer
{
    public function synchronize(bool $grantCentralDefaults): bool
    {
        global $DB, $GLPI_CACHE;

        $required = array_column(TicketOpsProfile::rights(), 'field');
        $success = true;
        foreach ($DB->request(['SELECT' => ['id', 'interface'], 'FROM' => \Profile::getTable()]) as $profile) {
            $profileId = (int) $profile['id'];
            $existing = \ProfileRight::getProfileRights($profileId, $required);
            foreach (RightSet::missing($required, $existing) as $name) {
                $rights = 0;
                if ($grantCentralDefaults && ($profile['interface'] ?? '') === 'central') {
                    $rights = $name === TicketOpsProfile::RIGHT_DIAGNOSTIC ? READ : UPDATE;
                }
                $success = $DB->insert(\ProfileRight::getTable(), [
                    'profiles_id' => $profileId,
                    'name' => $name,
                    'rights' => $rights,
                ]) && $success;
            }
        }

        $GLPI_CACHE->set('all_possible_rights', []);
        $this->refreshActiveProfileRights();

        return $success;
    }

    public function remove(): bool
    {
        return \ProfileRight::deleteProfileRights(array_column(TicketOpsProfile::rights(), 'field'));
    }

    public function refreshActiveProfileRights(): void
    {
        $activeProfile = $_SESSION['glpiactiveprofile'] ?? null;
        if (!is_array($activeProfile)) {
            return;
        }
        $profileId = (int) ($activeProfile['id'] ?? 0);
        if ($profileId <= 0) {
            return;
        }

        $required = array_column(TicketOpsProfile::rights(), 'field');
        $configured = \ProfileRight::getProfileRights($profileId, $required);
        foreach ($required as $right) {
            $activeProfile[$right] = (int) ($configured[$right] ?? 0);
        }
        $_SESSION['glpiactiveprofile'] = $activeProfile;
    }
}
