<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\MsgUser;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\MsgUserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MsgUserResource\RelationManagers;

class MsgUserResource extends Resource
{
    protected static ?string $model = MsgUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Utilisateurs emails';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('ms_id'),
                TextEntry::make('email'),
                TextEntry::make('abn_secret'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('ms_id')->searchable()->sortable(),
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
            ->recordUrl(
                fn (MsgUser $record): string => MsgUserResource::getUrl('view', ['record' => $record])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MsgEmailInsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMsgUsers::route('/'),
            'create' => Pages\CreateMsgUser::route('/create'),
            'edit' => Pages\EditMsgUser::route('/{record}/edit'),
            'view' => Pages\ViewMsgUser::route('/{record}/view'),
        ];
    }
}
