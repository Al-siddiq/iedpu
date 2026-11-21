<?php
declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Api\ApiResponder;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/** Preserve framework CSRF verification; normalise API failures to JSON 403. */
final class ApiCsrf extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            return parent::before($request, $arguments);
        } catch (SecurityException $error) {
            $path = trim($request->getUri()->getPath(), '/');
            if (preg_match('~(?:^|/)api/v1(?:/|$)~', $path)) {
                return ApiResponder::error($request, service('response'), 403,
                    'csrf_failed', 'Security token missing or expired. Refresh the token and retry manually.');
            }
            throw $error;
        }
    }
}
