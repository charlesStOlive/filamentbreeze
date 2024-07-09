<?php

namespace App\Filament\Resources\MsgUserResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;

class MsgEmailInsRelationManager extends RelationManager
{
    protected static string $relationship = 'msg_email_ins';

    public function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('from')
            ->columns([
                Tables\Columns\TextColumn::make('from')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'canceled' => 'danger',
                        'rate' => 'success',
                        'started' => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'canceled' => 'heroicon-o-pencil',
                        'rate' => 'heroicon-o-clock',
                        'started' => 'heroicon-o-check-circle',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),

            ])->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'canceled' => 'canceled',
                        'rate' => 'rate',
                        'started' => 'started',
                    ]),
                Filter::make('is_forwarded')->toggle(),
                Filter::make('is_canceled')->toggle(),
                Filter::make('has_sellsy_call')->toggle(),
                Filter::make('has_client')->toggle(),
                Filter::make('has_contact')->toggle(),
                Filter::make('has_contact_job')->toggle(),
                Filter::make('has_score')->toggle(),
                //
            ])
            ->actions([
                ViewAction::make()
                    ->form([
                        Section::make('base')
                            ->schema([
                                TextInput::make('from')->columnSpan(4),
                                Checkbox::make('is_canceled')->columnSpan(4),
                                TextInput::make('subject')->columnSpan(2),
                                TextInput::make('new_subject')->columnSpan(2),
                                TextInput::make('category')->columnSpan(2),
                                TextInput::make('status')->columnSpan(2),
                                Checkbox::make('is_forwarded')->columnSpan(4),
                                FilamentJsonColumn::make('tos')->label('autres cibles')->columnSpan(4)->viewerOnly(),
                        ])->columns(4)->columnSpan(4),
                    Section::make('Selssy')
                            ->schema([
                                Checkbox::make('has_sellsy_call'),
                                FilamentJsonColumn::make('data_sellsy')->columnSpan(4)->viewerOnly(),
                                Checkbox::make('has_sellsy_call'),
                                Checkbox::make('has_client'),
                                Checkbox::make('has_contact'),
                                Checkbox::make('has_contact_job'),
                                Checkbox::make('has_score'),
                        ])->columns(4)->columnSpan(4),
                    Section::make('Commercials')
                            ->schema([
                                Checkbox::make('is_from_commercial')->columnSpan(4),
                                Checkbox::make('has_regex_key'),
                                Checkbox::make('willbe_forwarded'),
                                TextInput::make('forwarded_to')->columnSpan(4),
                        ])->columns(4)->columnSpan(4),
                        FilamentJsonColumn::make('data_mail')->columnSpan(4)->viewerOnly(),
                    ]),
            ]);
    }
            
}
