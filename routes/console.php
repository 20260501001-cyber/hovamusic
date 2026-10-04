<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hova:purge-uploads')->hourly()->withoutOverlapping();
