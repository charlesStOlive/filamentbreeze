<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AnalyseSettings extends Settings
{

    public static function group(): string
    {
        return 'analyse';
    }
}