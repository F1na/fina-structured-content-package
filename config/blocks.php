<?php

return [

    /*
    |--------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------
    |
    | Prefix applied to all routes registered in routes/blocks.php.
    |
    */

    'route_prefix' => env('BLOCKS_ROUTE_PREFIX', 'api'),

    /*
    |--------------------------------------------------------------------
    | Route auto-registration
    |--------------------------------------------------------------------
    |
    | Set to false to stop the package from auto-registering its default,
    | unguarded route group. Do this when your API is segmented by guard
    | (e.g. /admin, /healthFacility) and you instead mount the routes
    | yourself, once per segment, via Fina\StructuredContent\StructuredContent::routes()
    | inside your own guarded route groups.
    |
    */

    'register_routes' => env('BLOCKS_REGISTER_ROUTES', true),

    /*
    |--------------------------------------------------------------------
    | File-owning block types
    |--------------------------------------------------------------------
    |
    | Filesystem disk (see config/filesystems.php) a block type such as
    | ImageBlockType stores its own file on, and the max upload size in
    | kilobytes (Laravel's validation "max" rule unit for files). There is
    | no shared media library: each block manages its own file.
    |
    */

    'media_disk' => env('BLOCKS_MEDIA_DISK', 'public'),

    'media_max_size' => env('BLOCKS_MEDIA_MAX_SIZE', 10240),

];
