<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CuentaClienteService
{
    /**
     * Para cada cliente con al menos un pedido no cancelado: total pedido,
     * total pagado (pedidos + pagos generales) y saldo. El saldo de un
     * pedido puntual nunca se ve afectado por un pago general: eso es a
     * propósito, para no marcar como "pagado" un pedido específico sin que
     * el usuario lo indique a mano. Un pago SÍ se descuenta del pago general
     * cuando está atado a un pedido puntual que se cancela -- ver
     * Pago::scopeVigentes().
     */
    public function resumenPorCliente(): Collection
    {
        $pedidosPorCliente = DB::table('pedidos')
            ->whereNull('deleted_at')
            ->where('estado', '!=', 'canceled')
            ->whereNotNull('user_id')
            ->selectRaw('user_id, SUM(total) as total, MIN(created_at) as desde')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $pagadoPorCliente = Pago::whereNotNull('user_id')->vigentes()
            ->selectRaw('user_id, SUM(monto) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return User::whereIn('id', $pedidosPorCliente->keys())
            ->get()
            ->map(function (User $user) use ($pedidosPorCliente, $pagadoPorCliente) {
                $fila = $pedidosPorCliente->get($user->id);
                $totalPedidos = (float) ($fila->total ?? 0);
                $totalPagado = (float) ($pagadoPorCliente->get($user->id) ?? 0);

                return [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'negocio' => $user->negocio,
                    'celular' => $user->celular,
                    'email' => $user->email,
                    'role' => $user->role,
                    'total' => $totalPedidos,
                    'pagado' => $totalPagado,
                    'saldo' => $totalPedidos - $totalPagado,
                    'desde' => Carbon::parse($fila->desde),
                ];
            })
            ->values();
    }

    /**
     * Igual que resumenPorCliente() pero para un solo cliente, calculado al
     * momento (sin pasar por un mapa precalculado). Se usa en columnas de
     * tabla que necesitan reflejar un pago recién registrado sin esperar a
     * que se recargue la página.
     */
    public function saldoDe(User $user): ?float
    {
        $totalPedidos = (float) Pedido::where('user_id', $user->id)
            ->where('estado', '!=', 'canceled')
            ->sum('total');

        if ($totalPedidos <= 0) {
            return null;
        }

        $totalPagado = (float) Pago::where('user_id', $user->id)->vigentes()->sum('monto');

        return $totalPedidos - $totalPagado;
    }
}
