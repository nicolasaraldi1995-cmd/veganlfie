<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use App\Models\Gasto;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGastos extends ListRecords
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar_csv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Fecha', 'Concepto', 'Tipo', 'Monto', 'Notas']);
                        Gasto::orderBy('fecha', 'desc')->each(function ($g) use ($handle) {
                            fputcsv($handle, [
                                $g->fecha->format('d/m/Y'),
                                $g->concepto,
                                Gasto::TIPOS[$g->tipo] ?? $g->tipo,
                                $g->monto,
                                $g->notas,
                            ]);
                        });
                        fclose($handle);
                    }, 'gastos-'.now()->format('Y-m-d').'.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
