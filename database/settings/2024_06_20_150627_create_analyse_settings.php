<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('analyse.commercials', []);
        $this->migrator->add('analyse.ndd_rejected', []);
    }
};
