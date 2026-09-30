<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Profile;
use GlpiPlugin\Ticketops\Service\UserEntitySearchService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SearchUsersController extends AbstractController
{
    #[Route('/TicketOps/Users', name: 'ticketops_users', methods: 'GET')]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request): JsonResponse
    {
        Session::checkLoginUser();
        if (!Config::enabled('requester_entity_switch') || !Profile::canSwitchRequesterAndEntity()) {
            throw new AccessDeniedHttpException();
        }

        return new JsonResponse((new UserEntitySearchService())->search(
            $request->query->getString('q'),
            $request->query->getInt('page', 1),
        ));
    }
}
