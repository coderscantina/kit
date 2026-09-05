<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('invites:prune')->daily();
Schedule::command('reactive:gc')->everyFifteenMinutes();
