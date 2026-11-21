<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiResponder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public, non-sensitive P1 foundation endpoints.
 * These endpoints do not assert database, authentication or module readiness.
 */
final class SystemController extends BaseController
{
    public function health(): ResponseInterface
    {
        return ApiResponder::success($this->request, $this->response, [
            'status' => 'ok',
            'service' => 'iedpu-north-zone-portal',
            'api_version' => 'v1',
            'database_checked' => false,
        ]);
    }

    public function bootstrap(): ResponseInterface
    {
        return ApiResponder::success($this->request, $this->response, [
            'api_version' => 'v1',
            'authentication_available' => false,
            'capabilities' => [
                'member_registration' => false,
                'member_verification' => false,
                'branch_administration' => false,
                'committee_administration' => false,
                'technical_administration' => false,
                'finance' => false,
                'official_membership_ids' => false,
                'announcements' => false,
            ],
        ]);
    }
}
