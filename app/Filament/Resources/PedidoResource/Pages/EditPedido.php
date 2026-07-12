<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPedido extends EditRecord
{
    protected static string $resource = PedidoResource::class;

    protected function afterSave(): void
    {
        $this->record->recalcularTotal();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmar')
                ->label('Confirmar pedido')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Confirmar este pedido?')
                ->modalDescription('El cliente ya no podrá modificarlo.')
                ->visible(fn () => $this->record->estado === 'pending')
                ->action(function () {
                    $this->record->update(['estado' => 'confirmed']);
                    Notification::make()->title('Pedido confirmado')->success()->send();
                    $this->redirect(PedidoResource::getUrl('index'));
                }),
            Actions\Action::make('cancelar')
                ->label('Cancelar pedido')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->estado, ['pending', 'confirmed']))
                ->action(function () {
                    $this->record->update(['estado' => 'canceled']);
                    Notification::make()->title('Pedido cancelado')->warning()->send();
                    $this->redirect(PedidoResource::getUrl('index'));
                }),
        ];
    }
}
