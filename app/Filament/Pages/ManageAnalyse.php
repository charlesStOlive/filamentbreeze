<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\AnalyseSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class ManageAnalyse extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = AnalyseSettings::class;

    public static function getNavigationLabel(): string
    {
        return 'Options';
    }

    protected static ?int $navigationSort = 2;

    public function form(Form $form): Form
    {
        return $form

            ->schema([
                Section::make('Commerciaux')
                    ->description('Liste des adresses emails commerciaux qui seront exploités lors d\'un transfert de mail.')
                    ->schema([
                        Repeater::make('commercials')
                            ->schema([
                                TextInput::make('email')->email()->required(),
                            ])
                            ->addActionLabel('Ajouter un commercial')
                            ->grid(2),
                    ]),
                Section::make('Scoring')
                    ->description('Grille des scores.')
                    ->schema([
                        Repeater::make('scorings')
                            ->schema([
                                TextInput::make('score-min')->integer()->required(),
                                TextInput::make('score-max')->integer()->required(),
                                TextInput::make('group-name')->required(),
                            ])
                            ->addActionLabel('Ajouter un score mix/max')
                            ->columns(3),
                    ]),
                Section::make('Nom de domaine exterieurs')
                    ->description('Liste des noms de domainex exterieurs qui ne seront pas étudié si le contact est inconnu.')
                    ->schema([
                        Repeater::make('ndd_rejecteds')
                            ->schema([
                                TextInput::make('ndd')->prefixIcon('heroicon-m-at-symbol')->placeholder('exemple.com')->required(),
                            ])
                            ->addActionLabel('Ajouter un Nom de domaine')
                            ->grid(2),
                    ]),
            ])->columns(1);
    }
}
