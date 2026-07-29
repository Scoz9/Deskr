<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Scrapkit\PermissionHierarchy\Models\Role as HierarchyRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property int $hierarchy_rank
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Role extends HierarchyRole {}
