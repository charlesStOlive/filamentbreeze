<?php

namespace App\Filament\Resources\MsgUserResource\Pages;

use App\Filament\Resources\MsgUserResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;

class ViewMsgUser extends ViewRecord
{
    protected static string $resource = MsgUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
           Actions\Action::make('testConnection')
                ->label('Simuler un email')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->form([
                    TextInput::make('test_from')->label('From')->default('alexis.clement@suscillon.com'),
                    TextInput::make('test_tos')->label('To')->helperText('Séparer les valeurs par une ",", la première valeur sera la cible MsgraphUser, elle doit exister !')->default(fn () => $this->record->email),
                    TextInput::make('subject')->label('Sujet')->default('Hello World !'),
                    RichEditor::make('body')->label('body')->default('<p>Du contenu</p>'),
                ])
                ->modalHeading('Créer un faux email')
                ->modalSubmitActionLabel('Exécuter le test')
                ->action(function (Forms\Set $set, array $data) {
                    $data['from']['emailAddress']['address'] = $email = trim($data['test_from']);
                    $toRecipients = [];
                    $tos = explode(',', trim($data['test_tos']));
                    foreach ($tos as $to) {
                        $toRecipients[] = ['emailAddress' => ['address' => trim($to)]];
                    }
                    $data['toRecipients'] = $toRecipients;
                    unset($data['test_from']);
                    unset($data['test_tos']);
                    //\Log::info($data);
                    $sellsy = new SellsyService();
                    $result = $sellsy->searchContactByEmail($email);
                    $set('test_result', $result);
                }),
        ];
    }

}
