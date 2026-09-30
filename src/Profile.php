<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketoperations;

use CommonGLPI;
use Session;

final class Profile extends \Profile
{
    public const RIGHT_DIAGNOSTIC = 'plugin_ticketoperations_diagnostic';
    public const RIGHT_REQUESTER_ENTITY_SWITCH = 'plugin_ticketoperations_requester_entity_switch';

    public static function canViewDiagnostic(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_DIAGNOSTIC, READ);
    }

    public static function canSwitchRequesterAndEntity(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_REQUESTER_ENTITY_SWITCH, UPDATE);
    }

    /** @param bool|int $withtemplate */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return $item instanceof \Profile
            && $item->getID() > 0
            && ($item->fields['interface'] ?? '') === 'central'
            ? self::createTabEntry(__('TicketOps', 'ticketoperations'), 0, $item::getType(), 'ti ti-ticket')
            : '';
    }

    /**
     * @param int $tabnum
     * @param bool|int $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$item instanceof \Profile || ($item->fields['interface'] ?? '') !== 'central') {
            return false;
        }

        $canEdit = Session::haveRight('profile', UPDATE);
        if ($canEdit) {
            echo "<form method='post' action='" . $item->getFormURL() . "'>";
        }
        $item->displayRightsChoiceMatrix(self::rights(), [
            'canedit' => $canEdit,
            'title' => __('TicketOps', 'ticketoperations'),
        ]);
        if ($canEdit) {
            echo "<div class='center'>";
            echo \Html::hidden('id', ['value' => $item->getID()]);
            echo \Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo '</div>';
            \Html::closeForm();
        }

        return true;
    }

    /** @return list<array{label: string, field: string, rights: array<int, string>}> */
    public static function rights(): array
    {
        return [
            self::right(__('View ticket organization diagnostics', 'ticketoperations'), self::RIGHT_DIAGNOSTIC, READ, __('Read')),
            self::right(__('Correct a ticket requester and entity', 'ticketoperations'), self::RIGHT_REQUESTER_ENTITY_SWITCH, UPDATE, __('Update')),
        ];
    }

    /** @return array{label: string, field: string, rights: array<int, string>} */
    private static function right(string $label, string $field, int $right, string $rightLabel): array
    {
        return ['label' => $label, 'field' => $field, 'rights' => [$right => $rightLabel]];
    }
}
