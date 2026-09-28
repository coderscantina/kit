<?php

declare(strict_types=1);

use App\Mcp\KitServer;
use Laravel\Mcp\Facades\Mcp;

// Streamable HTTP, authenticated by a personal access token and nothing
// else: no session middleware runs here, and a token does not authenticate
// anywhere but this route (AppServiceProvider::configureAccessTokens).
Mcp::web('/mcp', KitServer::class)
    ->middleware(['auth:sanctum', 'app.access', 'throttle:app'])
    ->name('mcp');
