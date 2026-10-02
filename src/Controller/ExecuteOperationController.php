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
use GlpiPlugin\Ticketops\Service\TicketOperationExecutor;
use GlpiPlugin\Ticketops\Service\TicketOperationPlanner;
use RuntimeException;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Ticket;

final class ExecuteOperationController extends AbstractController
{
    #[Route('/TicketOps/Ticket/{id}/Execute', name: 'ticketops_execute', methods: 'POST', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_CENTRAL_ACCESS)]
    public function __invoke(Request $request, int $id): JsonResponse
    {
        Session::checkLoginUser();
        $ticket = new Ticket();
        if (!Config::operationsEnabled() || !$ticket->getFromDB($id) || !(new TicketOperationGuard())->canOperate($ticket)) {
            throw new AccessDeniedHttpException();
        }
        $targetEntityId = $request->request->getInt('target_entity_id');
        if (!(new TicketOperationGuard())->canOperate($ticket, $targetEntityId)) {
            throw new AccessDeniedHttpException();
        }
        $remove = array_values(array_filter($request->request->all('remove_relation_keys'), static fn(mixed $key): bool => is_string($key) && $key !== ''));
        $organization = $this->organizationChanges($request);
        $ticketTitle = array_key_exists('ticket_title', $request->request->all())
            ? $request->request->getString('ticket_title')
            : null;
        $organizationOnly = $organization;
        unset($organizationOnly['status']);
        if (isset($organization['status'])) {
            unset($organizationOnly['technician']);
        }
        if (($request->request->getString('replaced_actor_key') !== '' && !Config::enabled('requester_entity_switch'))
            || (($organizationOnly !== [] || $ticketTitle !== null) && !Config::enabled('organization'))
            || (isset($organization['status']) && !Config::enabled('quick_assignment'))) {
            throw new AccessDeniedHttpException();
        }
        $plan = (new TicketOperationPlanner())->build(
            $ticket,
            $request->request->getString('replaced_actor_key'),
            $request->request->getInt('new_requester_id'),
            $targetEntityId,
            $remove,
            $organization,
            $ticketTitle,
        );
        if (!hash_equals($plan->fingerprint, $request->request->getString('fingerprint'))
            || !(new PreviewTokenStore())->consume($request->request->getString('preview_token'), $plan, Session::getLoginUserID())) {
            throw new BadRequestHttpException(__('The submitted operation is obsolete. Apply it again.', 'ticketops'));
        }
        try {
            $updated = (new TicketOperationExecutor())->execute($plan);
        } catch (RuntimeException $exception) {
            return new JsonResponse(['ok' => false, 'error' => $exception->getMessage()], 409);
        }

        return new JsonResponse(['ok' => true, 'redirect' => $updated->canViewItem() ? $updated->getLinkURL() : Ticket::getSearchURL()]);
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
