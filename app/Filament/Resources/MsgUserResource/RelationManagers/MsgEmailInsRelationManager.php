<?php

namespace App\Filament\Resources\MsgUserResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

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
                Tables\Columns\TextColumn::make('score')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),

            ])->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'canceled' => 'canceled',
                        'rate' => 'rate',
                        'started' => 'started',
                    ])
                //
            ])
            ->actions([
                ViewAction::make()
                    ->form([
                        TextInput::make('from'),
                        TextInput::make('status'),
                        TextInput::make('score'),
                        FilamentJsonColumn::make('data')->viewerOnly(),
                    ]),
            ]);
    }
            
}
