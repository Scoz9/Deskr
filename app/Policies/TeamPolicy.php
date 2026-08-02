<?php

namespace App\Policies;

use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * Nothing to override, same as {@see OrganizationPolicy}: a team has no
 * protected row like `superAdmin` on `Role`, so the five permission-based
 * abilities `BasePolicy` gives are the whole policy. What a team may not
 * lose — its categories and its tickets — is guarded in the controller and
 * by the database, not by an ability.
 */
class TeamPolicy extends BasePolicy {}
