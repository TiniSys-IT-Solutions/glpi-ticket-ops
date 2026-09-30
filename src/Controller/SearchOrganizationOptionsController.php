<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use GlpiPlugin\Ticketops\Service\OrganizationOptionService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Ticket;

final class SearchOrganizationOptionsController extends AbstractController
{
    #[Route('/TicketOps/Ticket/{id}/OrganizationOptions', name: 'ticketops_organization_options', methods: 'GET', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): JsonResponse
    {
        Session::checkLoginUser();
        $ticket = new Ticket();
        $entityId = $request->query->getInt('entity_id');
        $kind = $request->query->getString('kind');
        if (!in_array($kind, ['category', 'location', 'group', 'technician'], true)) {
            throw new BadRequestHttpException(__('Invalid operation parameters.', 'ticketops'));
        }
        if (!(Config::enabled('organization') || Config::enabled('quick_assignment')) || !$ticket->getFromDB($id)
            || !(new TicketOperationGuard())->canOperate($ticket, $entityId)) {
            throw new AccessDeniedHttpException();
        }

        return new JsonResponse((new OrganizationOptionService())->search(
            $kind,
            $request->query->getString('q'),
            $entityId,
            $request->query->getInt('page', 1),
        ));
    }
}
