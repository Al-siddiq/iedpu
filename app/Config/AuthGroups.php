<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    // Shield groups are not the portal authorisation system. Portal grants are
    // database-scoped and evaluated by App\\Libraries\\Auth\\ScopeAuthorizer.
    // No starter superadmin/admin/developer grants or implicit default group.
    public string $defaultGroup = '';
    public array $groups = [];
    public array $permissions = [];
    public array $matrix = [];

}
