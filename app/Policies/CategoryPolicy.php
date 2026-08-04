<?php

namespace App\Policies;

use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * Nothing to override, same as {@see TeamPolicy}: what a category may not
 * lose — the tickets filed under it — is guarded in the controller and by
 * the database, not by an ability.
 */
class CategoryPolicy extends BasePolicy {}
