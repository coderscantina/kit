<?php

declare(strict_types=1);

use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    AiServiceProvider::class,
    NotificationServiceProvider::class,
    HorizonServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
