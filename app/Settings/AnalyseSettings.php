<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AnalyseSettings extends Settings
{

    public array $commercials;
    
    public array $ndd_rejecteds;

    public array $scorings;


    public static function group(): string
    {
        return 'analyse';
    }
}