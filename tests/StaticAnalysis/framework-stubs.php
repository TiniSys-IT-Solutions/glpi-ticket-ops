<?php

declare(strict_types=1);

namespace Glpi\Controller {
    abstract class AbstractController {}
}

namespace Glpi\Http {
    final class Firewall
    {
        public const STRATEGY_AUTHENTICATED = 'authenticated';
        public const STRATEGY_CENTRAL_ACCESS = 'central_access';
    }
}

namespace Glpi\Security\Attribute {
    #[\Attribute] final class SecurityStrategy
    {
        public function __construct(string $strategy) {}
    }
}

namespace Glpi\Exception\Http {
    class AccessDeniedHttpException extends \RuntimeException {}
    class BadRequestHttpException extends \RuntimeException {}
}

namespace Symfony\Component\Routing\Attribute {
    #[\Attribute] final class Route
    {
        /** @param list<string>|string $methods @param array<string, string> $requirements */
        public function __construct(string $path, string $name = '', array|string $methods = [], array $requirements = []) {}
    }
}

namespace Symfony\Component\HttpFoundation {
    class ParameterBag
    {
        public function getString(string $key, string $default = ''): string
        {
            return '';
        }
        public function getInt(string $key, int $default = 0): int
        {
            return 0;
        }
        /** @return array<mixed> */
        public function all(?string $key = null): array
        {
            return [];
        }
    }
    class Request
    {
        public ParameterBag $query;
        public ParameterBag $request;
    }
    class JsonResponse
    {
        /** @param mixed $data */
        public function __construct($data = null, int $status = 200) {}
    }
    class Response
    {
        /** @param array<string, string> $headers */
        public function __construct(string $content = '', int $status = 200, array $headers = []) {}
    }
}
