<?php

use DeliciousBrains\WP_Offload_Media\Providers\Storage\Bunny_Provider;

// Backwards compatibility loader for Bunny Storage provider.
// The real class lives in classes/providers/storage/bunny-provider.php and
// is loaded by the plugin's autoloader. This file exists to match the
// expected output path for external tooling.

class_alias( Bunny_Provider::class, 'DeliciousBrains\\WP_Offload_Media\\Providers\\Storage\\Bunny_Storage_Provider' );
