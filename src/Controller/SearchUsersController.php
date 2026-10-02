<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Profile;
use GlpiPlugin\Ticketops\Service\UserEntitySearchService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SearchUsersController extends AbstractController
{
    #[Route('/TicketOps/Users/{id}', name: 'ticketops_user_entities', methods: 'GET', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_CENTRAL_ACCESS)]
    public function resolve(int $id): JsonResponse
    {
        Session::checkLoginUser();
        if (!Session::haveRight('ticket', UPDATE) || !Config::enabled('requester_entity_switch') || !Profile::canSwitchRequesterAndEntity()) {
            throw new AccessDeniedHttpException();
        }
        $resolved = (new UserEntitySearchService())->resolve($id);
        if ($resolved === null) {
            throw new BadRequestHttpException(__('No accessible user found.', 'ticketops'));
        }

        return new JsonResponse([
            'id' => $id,
            'entities' => array_map(
                static fn(int $entityId): array => ['id' => $entityId, 'name' => \Dropdown::getDropdownName('glpi_entities', $entityId)],
                $resolved['entity_ids'],
            ),
            'suggested_entity_id' => $resolved['suggested_entity_id'],
        ]);
    }
}
