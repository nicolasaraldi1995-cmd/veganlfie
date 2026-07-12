<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class MisPedidosController extends Controller
{
    public function index()
    {
        $pedidos = auth()->user()->pedidos()
            ->where('estado', '!=', 'draft')
            ->with(['items.presentacion.producto.marca'])
            ->latest()
            ->paginate(10);

        return Inertia::render('MisPedidos', [
            'pedidos' => $pedidos,
        ]);
    }
}
