<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiResponder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * P1 deny-by-default endpoint. Never substitute mock business operations.
 */
final class UnavailableController extends BaseController
{
    public function disabled(): ResponseInterface
    {
        return ApiResponder::error(
            $this->request,
            $this->response,
            403,
            'feature_not_enabled',
            'This function is not available at the current implementation stage.'
        );
    }

    public function notFound(): ResponseInterface
    {
        return ApiResponder::error(
            $this->request,
            $this->response,
            404,
            'not_found',
            'The requested API endpoint does not exist.'
        );
    }
}
