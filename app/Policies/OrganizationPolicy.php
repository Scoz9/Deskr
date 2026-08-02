<?php

namespace App\Policies;

use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * No override needed: an organization has no protected row like `superAdmin`
 * on `Role`, so the five permission-based abilities `BasePolicy` already
 * gives are the whole policy. The class still has to exist — Laravel
 * resolves `App\Policies\OrganizationPolicy` for `App\Models\Organization`
 * by the name alone, and `$authorizesResources` on the controller has
 * nothing to authorize against without it.
 */
class OrganizationPolicy extends BasePolicy {}
