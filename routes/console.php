<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('invites:prune')->daily();
Schedule::command('notifications:prune')->daily();
Schedule::command('reactive:gc')->everyFifteenMinutes();
// Audit entries past their retention and old webhook deliveries.
Schedule::command('model:prune')->daily();
