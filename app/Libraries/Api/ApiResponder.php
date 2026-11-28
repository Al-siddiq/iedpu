<?php

declare(strict_types=1);

namespace App\Libraries\Api;

use CodeIgniter\HTTP\RequestInterface;
use App\Libraries\Security\RequestId;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Stable, same-origin /api/v1 JSON contract.
 *
 * Do not include exception objects, environment variables, tokens, session
 * data, SQL queries or user-supplied request bodies in API responses.
 */
final class ApiResponder
{
    public static function success(
        RequestInterface $request,
        ResponseInterface $response,
        array $data,
        int $status = 200
    ): ResponseInterface {
        return self::send($request, $response, $status, [
            'success' => true,
            'data' => $data,
        ]);
    }

    public static function error(
        RequestInterface $request,
        ResponseInterface $response,
        int $status,
        string $code,
        string $message,
        array $fields = []
    ): ResponseInterface {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return self::send($request, $response, $status, [
            'success' => false,
            'error' => $error,
        ]);
    }

    private static function send(
        RequestInterface $request,
        ResponseInterface $response,
        int $status,
        array $payload
    ): ResponseInterface {
        $requestId = RequestId::current();

        $payload['meta'] = [
            'request_id' => $requestId,
        ];

        return $response
            ->setStatusCode($status)
            ->setHeader('X-Request-ID', $requestId)
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setJSON($payload);
    }
}
