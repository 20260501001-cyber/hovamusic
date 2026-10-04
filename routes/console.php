<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hova:purge-uploads')->hourly()->withoutOverlapping();
Schedule::command('hova:spotify-track')->dailyAt('06:00')->timezone(config('hova.display_timezone'))->withoutOverlapping();
