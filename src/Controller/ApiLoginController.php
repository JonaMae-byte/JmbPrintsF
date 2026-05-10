<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Target route for JWT json_login. The api_login firewall runs JsonLoginAuthenticator
 * first; this action should not run for a normal successful or failed credential check.
 */
final class ApiLoginController
{
    #[Route(path: '/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): Response
    {
        return new Response(
            '{"code":500,"message":"Login was not handled by json_login; check security firewalls."}',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'application/json']
        );
    }
}
