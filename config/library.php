<?php

return [
    'jwt_secret' => env('JWT_SECRET'),
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'covers_directory' => env('COVERS_DIR', storage_path('app/private/covers')),
];
