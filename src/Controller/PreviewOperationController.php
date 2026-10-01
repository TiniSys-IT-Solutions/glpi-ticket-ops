<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Security\PreviewTokenStore;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use GlpiPlugin\Ticketops\Service\TicketOperationPlanner;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Ticket;

final class PreviewOperationController extends AbstractController
{
    #[Route('/TicketOps/Ticket/{id}/Preview', name: 'ticketops_preview', methods: 'POST', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): JsonResponse
    {
        Session::checkLoginUser();
        $ticket = new Ticket();
        if (!Config::operationsEnabled() || !$ticket->getFromDB($id) || !(new TicketOperationGuard())->canOperate($ticket)) {
            throw new AccessDeniedHttpException();
        }
        $actorId = $request->request->getInt('replaced_actor_id');
        $userId = $request->request->getInt('new_requester_id');
        $entityId = $request->request->getInt('target_entity_id');
        if (($actorId > 0 xor $userId > 0) || $entityId < 0) {
            throw new BadRequestHttpException(__('Invalid operation parameters.', 'ticketops'));
        }
        if (!(new TicketOperationGuard())->canOperate($ticket, $entityId)) {
            throw new AccessDeniedHttpException();
        }
        $remove = array_values(array_filter(array_map('intval', (array) $request->request->all('remove_relation_ids')), static fn(int $id): bool => $id !== 0));
        $organization = $this->organizationChanges($request);
        $organizationOnly = $organization;
        unset($organizationOnly['status']);
        if (isset($organization['status'])) {
            unset($organizationOnly['technician']);
        }
        if (($actorId > 0 && !Config::enabled('requester_entity_switch'))
            || ($organizationOnly !== [] && !Config::enabled('organization'))
            || (isset($organization['status']) && !Config::enabled('quick_assignment'))) {
            throw new AccessDeniedHttpException();
        }

        $plan = (new TicketOperationPlanner())->build($ticket, $actorId, $userId, $entityId, $remove, $organization);
        $response = $plan->toArray();
        $response['previewToken'] = (new PreviewTokenStore())->issue($plan, Session::getLoginUserID());

        return new JsonResponse($response);
    }

    /** @return array<string, int> */
    private function organizationChanges(Request $request): array
    {
        $changes = [];
        foreach (['category', 'location', 'technician', 'observer', 'status'] as $kind) {
            $value = $request->request->getInt($kind . '_id');
            if ($value > 0) {
                $changes[$kind] = $value;
            }
        }

        return $changes;
    }
}
