<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListaPreciosController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\MisPedidosController;
use App\Http\Controllers\NuevosController;
use App\Http\Controllers\OfertasController;
use App\Http\Controllers\PedidoClienteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VeganlifeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
Route::get('/api/buscar', [ProductoController::class, 'buscar'])->middleware('throttle:60,1')->name('productos.buscar');
Route::get('/productos/{producto:slug}', [ProductoController::class, 'show'])->name('productos.show');

Route::get('/marcas/{marca:slug}', [MarcaController::class, 'show'])->name('marcas.show');
Route::get('/categorias/{categoria:slug}', [CategoriaController::class, 'show'])->name('categorias.show');

Route::get('/combos', [ComboController::class, 'index'])->name('combos.index');
Route::middleware('auth')->group(function () {
    Route::get('/lista-de-precios', [ListaPreciosController::class, 'index'])->name('lista-precios');
    Route::get('/lista-de-precios/pdf', [ListaPreciosController::class, 'pdf'])->name('lista-precios.pdf');
});
Route::get('/nuevos', NuevosController::class)->name('nuevos');
Route::get('/veganlife', VeganlifeController::class)->name('veganlife');
Route::get('/ofertas', OfertasController::class)->name('ofertas');

Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/carrito/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/carrito/add-combo', [CartController::class, 'addCombo'])->name('cart.add-combo');
    Route::patch('/carrito/update', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/carrito/remove', [CartController::class, 'remove'])->name('cart.remove');
});

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/checkout/confirmacion/{pedido}', [CheckoutController::class, 'confirmacion'])->name('checkout.confirmacion');
});

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/mis-pedidos', [MisPedidosController::class, 'index'])->name('mis-pedidos');
    Route::get('/mis-pedidos/{pedido}', [PedidoClienteController::class, 'show'])->name('pedido.show');
    Route::middleware('throttle:30,1')->group(function () {
        Route::patch('/mis-pedidos/{pedido}/item', [PedidoClienteController::class, 'updateItem'])->name('pedido.update-item');
        Route::post('/mis-pedidos/{pedido}/item', [PedidoClienteController::class, 'addItem'])->name('pedido.add-item');
        Route::delete('/mis-pedidos/{pedido}/item', [PedidoClienteController::class, 'removeItem'])->name('pedido.remove-item');
    });
    Route::get('/mis-pedidos/{pedido}/pdf', [PedidoClienteController::class, 'pdf'])->name('pedido.pdf');
});

require __DIR__.'/auth.php';
