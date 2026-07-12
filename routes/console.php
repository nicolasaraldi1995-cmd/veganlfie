<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// En hosting con cron real (`* * * * * php artisan schedule:run`) esto alcanza solo.
// En Windows/Laragon local, un Programador de Tareas corre backup:database directo
// (ver el Task "VeganLife DB Backup"), así que esta línea queda inactiva hasta la migración.
Schedule::command('backup:database')->dailyAt('03:00');
