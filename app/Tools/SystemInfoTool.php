<?php

namespace App\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Facades\App;

class SystemInfoTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('system_info')
            ->for('Get system details like PHP version, Laravel version, or the current environment.')
            ->withBooleanParameter('confirmed', 'Confirm you want to see system info')
            ->using($this);
    }

    public function __invoke(bool $confirmed): string
    {
        return json_encode([
            'php_version' => PHP_VERSION,
            'laravel_version' => App::version(),
            'environment' => App::environment(),
            'os' => PHP_OS_FAMILY,
        ]);
    }
}
