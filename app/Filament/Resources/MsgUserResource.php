<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\MsgUser;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\MsgUserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MsgUserResource\RelationManagers;

class MsgUserResource extends Resource
{
    protected static ?string $model = MsgUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ms_id'),
                TextColumn::make('email'),
                TextColumn::make('abn_secret'),
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('suscribe')
                    ->label('Souscrire')
                    ->requiresConfirmation()
                    ->action(fn (MsgUser $record) => $record->suscribe())
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMsgUsers::route('/'),
            'create' => Pages\CreateMsgUser::route('/create'),
            'edit' => Pages\EditMsgUser::route('/{record}/edit'),
        ];
    }
}
