<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use GlpiPlugin\Ticketops\Service\UserEntitySearchService;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ticket;

final class SearchOrganizationOptionsController extends AbstractController
{
    #[Route('/TicketOps/Ticket/{id}/NativeFields', name: 'ticketops_native_fields', methods: 'GET', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): Response
    {
        Session::checkLoginUser();
        $ticket = new Ticket();
        if (!$ticket->getFromDB($id) || !(new TicketOperationGuard())->canOperate($ticket)) {
            throw new AccessDeniedHttpException();
        }
        if ($request->query->getString('scope') === 'entity') {
            $userId = $request->query->getInt('user_id');
            $entityIds = array_values(array_unique(array_map('intval', Session::getActiveEntities())));
            $suggestedEntityId = in_array((int) $ticket->fields['entities_id'], $entityIds, true)
                ? (int) $ticket->fields['entities_id']
                : 0;
            if ($userId > 0) {
                $resolved = (new UserEntitySearchService())->resolve($userId);
                if ($resolved === null) {
                    throw new AccessDeniedHttpException();
                }
                $entityIds = $resolved['entity_ids'];
                $suggestedEntityId = $resolved['suggested_entity_id'] ?? 0;
            }

            return new Response((string) \Dropdown::show('Entity', [
                'name' => 'target_entity_id',
                'value' => $suggestedEntityId,
                'condition' => ['id' => $entityIds],
                'width' => '100%',
                'comments' => false,
                'addicon' => false,
                'display_emptychoice' => true,
                'emptylabel' => __('Choose a target entity', 'ticketops'),
                'permit_select_parent' => true,
                'class' => 'form-select ticketops-entity',
                'display' => false,
            ]), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
        if ($request->query->getString('scope') === 'requester') {
            if (!Config::enabled('requester_entity_switch')) {
                throw new AccessDeniedHttpException();
            }

            return new Response((string) \User::dropdown([
                'name' => 'new_requester_id',
                'value' => 0,
                'entity' => Session::getActiveEntities(),
                'right' => 'all',
                'width' => '100%',
                'comments' => false,
                'display_emptychoice' => true,
                'emptylabel' => __('No requester change', 'ticketops'),
                'class' => 'form-select ticketops-user-search',
                'display' => false,
            ]), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $entityId = $request->query->getInt('entity_id');
        if (!Config::enabled('organization') || !(new TicketOperationGuard())->canOperate($ticket, $entityId)) {
            throw new AccessDeniedHttpException();
        }

        $common = [
            'value' => 0,
            'entity' => $entityId,
            'width' => '100%',
            'comments' => false,
            'addicon' => false,
            'display_emptychoice' => true,
            'emptylabel' => __('Keep current value', 'ticketops'),
            'display' => false,
        ];
        $categoryCondition = match ((int) ($ticket->fields['type'] ?? 0)) {
            Ticket::INCIDENT_TYPE => ['is_incident' => 1],
            Ticket::DEMAND_TYPE => ['is_request' => 1],
            default => [],
        };

        $fields = [
            'category' => \Dropdown::show(\ITILCategory::class, $common + [
                'name' => 'category_id',
                'class' => 'form-select ticketops-category',
                'condition' => $categoryCondition,
            ]),
            'location' => \Dropdown::show(\Location::class, $common + [
                'name' => 'location_id',
                'class' => 'form-select ticketops-location',
                'entity_sons' => true,
            ]),
            'technician' => \User::dropdown([
                'name' => 'technician_id',
                'value' => 0,
                'entity' => $entityId,
                'right' => 'own_ticket',
                'width' => '100%',
                'comments' => false,
                'display_emptychoice' => true,
                'emptylabel' => __('Keep current value', 'ticketops'),
                'class' => 'form-select ticketops-technician',
                'display' => false,
            ]),
            'observer' => \User::dropdown([
                'name' => 'observer_id',
                'value' => 0,
                'entity' => $entityId,
                'right' => 'all',
                'width' => '100%',
                'comments' => false,
                'display_emptychoice' => true,
                'emptylabel' => __('Keep current value', 'ticketops'),
                'class' => 'form-select ticketops-observer',
                'display' => false,
            ]),
        ];

        $html = '';
        foreach ($fields as $kind => $field) {
            $html .= '<div class="col-md-6 mb-3"><label class="form-label">'
                . htmlspecialchars(self::label($kind), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</label>' . $field . '</div>';
        }

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private static function label(string $kind): string
    {
        return match ($kind) {
            'category' => __('ITIL category', 'ticketops'),
            'location' => __('Location', 'ticketops'),
            'observer' => __('Observer', 'ticketops'),
            default => __('Technician', 'ticketops'),
        };
    }
}
