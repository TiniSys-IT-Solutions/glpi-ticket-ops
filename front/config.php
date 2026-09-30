<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketops\Config as TicketOpsConfig;

Session::checkLoginUser();
if (!TicketOpsConfig::canManage()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    TicketOpsConfig::save($_POST);
    Session::addMessageAfterRedirect(__('TicketOps settings saved.', 'ticketops'));
    Html::redirect(TicketOpsConfig::url());
}

$values = TicketOpsConfig::values();
Html::header(__('TicketOps settings', 'ticketops'), $_SERVER['PHP_SELF'], 'config', 'plugins');
echo "<div class='container-xl'><div class='card'><div class='card-header'><h2 class='card-title'>";
echo htmlescape(__('TicketOps modules', 'ticketops'));
echo "</h2></div><div class='card-body'><form method='post'>";
foreach ([
    'diagnostic_enabled' => __('Ticket organization diagnostics', 'ticketops'),
    'requester_entity_switch_enabled' => __('Requester and entity correction', 'ticketops'),
    'organization_enabled' => __('Ticket organization operation', 'ticketops'),
    'quick_assignment_enabled' => __('Quick assignment', 'ticketops'),
] as $key => $label) {
    echo "<label class='form-check form-switch mb-3'>";
    echo "<input class='form-check-input' type='checkbox' name='" . htmlescape($key) . "' value='1'";
    echo $values[$key] ? ' checked' : '';
    echo "><span class='form-check-label'>" . htmlescape($label) . '</span></label>';
}
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::submit(_sx('button', 'Save'), ['class' => 'btn btn-primary']);
echo '</form></div></div></div>';
Html::footer();
