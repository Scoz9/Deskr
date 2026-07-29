<?php

return [

    /*
     * The user model that can be suspended. Used by the `suspend:clear`
     * command to clear expired temporary suspensions.
     */
    'model' => 'App\\Models\\User',

    /*
     * The table the published migration adds the suspension columns to.
     */
    'table' => 'users',

    /*
     * The columns used to store suspensions. `suspended_at` marks a
     * permanent suspension, `suspended_until` a temporary one.
     */
    'columns' => [
        'suspended_at' => 'suspended_at',
        'suspended_until' => 'suspended_until',
    ],

    /*
     * Response returned by the `not-suspended` middleware when a
     * suspended user tries to access a protected route.
     */
    'middleware' => [
        'status' => 403,
        'message' => 'Your account has been suspended.',
    ],

];
