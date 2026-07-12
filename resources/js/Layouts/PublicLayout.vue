<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const page = usePage();
const cartOpen = ref(false);
const sidebarOpen = ref(false);

const cartCount = computed(() => page.props.cartCount || 0);

const searchQuery = ref('');
const searchResults = ref([]);
const searchOpen = ref(false);
let searchTimeout = null;

function onSearchInput(e) {
    const val = e.target.value;
    searchQuery.value = val;
    clearTimeout(searchTimeout);
    if (val.length < 2) { searchResults.value = []; searchOpen.value = false; return; }
    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`/api/buscar?q=${encodeURIComponent(val)}`);
            searchResults.value = await res.json();
            searchOpen.value = searchResults.value.length > 0;
        } catch { searchResults.value = []; }
    }, 300);
}

function goToProduct(slug) {
    searchOpen.value = false;
    searchQuery.value = '';
    searchResults.value = [];
    router.get(route('productos.show', slug));
}

function searchSubmit() {
    if (searchQuery.value.length >= 2) {
        searchOpen.value = false;
        router.get(route('productos.index'), { buscar: searchQuery.value });
    }
}

defineExpose({ cartOpen });
</script>

<template>
    <div class="min-h-screen bg-surface text-text font-sans">
        <!-- Topbar -->
        <nav class="bg-surface-1/80 backdrop-blur-2xl border-b border-border sticky top-0 z-50">
            <div class="max-w-[1440px] mx-auto px-6 flex items-center justify-between h-16">
                <button @click="sidebarOpen = !sidebarOpen" aria-label="Menú" class="lg:hidden p-2 -ml-2 text-text-muted hover:text-text transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <Link :href="route('home')" class="flex items-center gap-3 group">
                    <img src="/images/logo.png" alt="VEGANLIFE" class="h-9 object-contain group-hover:opacity-90 transition" />
                    <div class="hidden sm:block leading-none">
                        <span class="text-[15px] font-semibold text-text tracking-tight">VEGANLIFE</span>
                        <span class="block text-[10px] text-text-muted tracking-widest uppercase">Distribuidora</span>
                    </div>
                </Link>

                <div class="flex items-center flex-1 max-w-md mx-3 sm:mx-6 relative" @keydown.escape="searchOpen = false">
                    <input type="text" placeholder="Buscar productos o marcas..."
                        :value="searchQuery"
                        @input="onSearchInput"
                        @keyup.enter="searchSubmit"
                        @focus="searchResults.length && (searchOpen = true)"
                        class="w-full pl-9 pr-3 py-2 text-[13px] bg-surface-2 border border-border rounded-xl focus:border-accent focus:ring-1 focus:ring-accent/20 placeholder:text-text-muted transition" />
                    <svg class="w-4 h-4 text-text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <!-- Autocomplete dropdown -->
                    <div v-if="searchOpen" class="absolute top-full left-0 right-0 mt-1 bg-surface-1 border border-border rounded-xl shadow-xl shadow-black/10 overflow-hidden z-[100]">
                        <button v-for="r in searchResults" :key="r.id" @mousedown.prevent="goToProduct(r.slug)"
                            class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2 transition text-left">
                            <div class="w-10 h-10 rounded-lg bg-surface-2 overflow-hidden shrink-0">
                                <img v-if="r.imagen" :src="r.imagen" class="w-full h-full object-cover" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[13px] text-text truncate font-medium">{{ r.nombre }}</p>
                                <p class="text-[11px] text-text-muted">{{ r.marca }}</p>
                            </div>
                        </button>
                        <button @mousedown.prevent="searchSubmit" class="w-full px-4 py-2.5 text-[12px] text-accent font-medium hover:bg-surface-2 transition text-left border-t border-border">
                            Ver todos los resultados →
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <template v-if="page.props.auth.user">
                        <Link :href="route('lista-precios')" class="hidden sm:flex items-center gap-2 text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Precios
                        </Link>
                        <Link :href="route('mis-pedidos')" class="hidden sm:flex items-center gap-2 text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Pedidos
                        </Link>
                        <Link :href="route('profile.edit')" class="hidden sm:flex items-center gap-2 text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            <span class="w-6 h-6 rounded-full bg-accent/20 flex items-center justify-center text-[10px] font-bold text-accent">
                                {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                            </span>
                            {{ page.props.auth.user.name }}
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="text-[12px] text-text-muted hover:text-red-400 px-2 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Salir
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="hidden sm:block text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Ingresar
                        </Link>
                        <Link :href="route('register')" class="hidden sm:block text-[13px] font-medium text-white bg-accent hover:bg-accent-bright px-4 py-2 rounded-xl transition-all">
                            Crear cuenta
                        </Link>
                    </template>

                    <button @click="cartOpen = true" aria-label="Carrito" class="relative p-2.5 text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition-all ml-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                        <span v-if="cartCount > 0" class="absolute -top-0.5 -right-0.5 bg-accent text-white text-[9px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1">
                            {{ cartCount }}
                        </span>
                    </button>
                </div>
            </div>
        </nav>

        <div class="flex max-w-[1440px] mx-auto">
            <!-- Sidebar -->
            <aside class="hidden lg:block w-[240px] shrink-0 sticky top-16 h-[calc(100vh-64px)] overflow-y-auto border-r border-border px-4 py-6">
                <div class="space-y-0.5">
                    <Link v-for="item in [
                        { href: route('home'), label: 'Inicio', active: route().current('home'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
                        { href: route('productos.index', { vista: 'categorias' }), label: 'Categorías', active: $page.props.filtros?.vista === 'categorias', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
                        { href: route('productos.index', { vista: 'marcas' }), label: 'Marcas', active: $page.props.filtros?.vista === 'marcas', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
                        { href: route('combos.index'), label: 'Combos', active: route().current('combos.*'), icon: 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7' },
                        { href: route('nuevos'), label: 'Nuevos', active: route().current('nuevos'), icon: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z' },
                        { href: route('veganlife'), label: 'VEGANLIFE', active: route().current('veganlife'), icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' },
                        { href: route('ofertas'), label: 'Ofertas', active: route().current('ofertas'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
                    ]" :key="item.label" :href="item.href"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium transition-all"
                        :class="item.active ? 'bg-accent/10 text-accent' : 'text-text-secondary hover:bg-surface-2 hover:text-text'">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        {{ item.label }}
                    </Link>

                    <div class="h-px bg-border my-4"></div>
                    <p class="text-[10px] font-semibold text-text-muted uppercase tracking-[0.15em] px-3 pb-1">Filtros</p>
                    <Link :href="route('productos.index', { sin_tacc: 1 })" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] text-text-secondary hover:bg-surface-2 hover:text-text transition-all">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent shrink-0"></span> SIN T.A.C.C.
                    </Link>
                    <Link :href="route('productos.index', { frio: 1 })" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] text-text-secondary hover:bg-surface-2 hover:text-text transition-all">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400 shrink-0"></span> Fríos
                    </Link>
                    <Link :href="route('productos.index', { congelado: 1 })" class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] text-text-secondary hover:bg-surface-2 hover:text-text transition-all">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0"></span> Congelados
                    </Link>
                </div>
            </aside>

            <!-- Mobile sidebar -->
            <Teleport to="body">
                <Transition name="fade"><div v-if="sidebarOpen" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" @click="sidebarOpen = false"></div></Transition>
                <Transition name="slide-left">
                    <aside v-if="sidebarOpen" class="fixed left-0 top-0 bottom-0 w-[280px] z-[55] bg-surface-1 border-r border-border p-5 overflow-y-auto">
                        <div class="flex justify-between items-center mb-5">
                            <span class="font-semibold text-text">Menú</span>
                            <button @click="sidebarOpen = false" class="p-1.5 text-text-muted hover:text-text hover:bg-surface-2 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="space-y-0.5">
                            <Link v-for="item in [{l:'Inicio',h:route('home')},{l:'Categorías',h:route('productos.index',{vista:'categorias'})},{l:'Marcas',h:route('productos.index',{vista:'marcas'})},{l:'Combos',h:route('combos.index')},{l:'Nuevos',h:route('nuevos')},{l:'VEGANLIFE',h:route('veganlife')},{l:'Ofertas',h:route('ofertas')}]"
                                :key="item.l" :href="item.h" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">{{ item.l }}</Link>
                            <div class="h-px bg-border my-3"></div>
                            <template v-if="!page.props.auth.user">
                                <Link :href="route('login')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">Ingresar</Link>
                                <Link :href="route('register')" class="block px-3 py-2.5 text-[13px] text-accent hover:bg-accent/10 rounded-xl transition" @click="sidebarOpen=false">Crear cuenta</Link>
                            </template>
                            <template v-else>
                                <Link :href="route('mis-pedidos')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">Mis pedidos</Link>
                                <Link :href="route('profile.edit')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">Mi cuenta</Link>
                            </template>
                        </div>
                    </aside>
                </Transition>
            </Teleport>

            <main class="flex-1 min-w-0"><slot /></main>
        </div>

        <!-- Cart slide -->
        <Teleport to="body">
            <Transition name="fade"><div v-if="cartOpen" class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="cartOpen = false"></div></Transition>
            <Transition name="slide-right">
                <div v-if="cartOpen" class="fixed top-0 right-0 bottom-0 w-full max-w-[400px] z-[70] bg-surface-1 border-l border-border flex flex-col">
                    <div class="flex items-center justify-between px-6 h-16 border-b border-border shrink-0">
                        <h2 class="text-[15px] font-semibold text-text">Carrito <span v-if="cartCount" class="text-text-muted font-normal">({{ cartCount }})</span></h2>
                        <button @click="cartOpen = false" class="p-1.5 text-text-muted hover:text-text hover:bg-surface-2 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div v-if="page.props.cartItems.length" class="px-6 py-3 border-b border-border">
                        <div class="flex justify-between text-[11px] mb-1.5">
                            <span v-if="page.props.cartTotal < 300000" class="text-red-400">Faltan ${{ (300000 - page.props.cartTotal).toLocaleString('es-AR') }} para comprar</span>
                            <span v-else-if="page.props.cartTotal < 450000" class="text-accent">${{ (450000 - page.props.cartTotal).toLocaleString('es-AR') }} para envío gratis</span>
                            <span v-else class="text-accent">Envío gratis</span>
                        </div>
                        <div class="w-full bg-surface-3 rounded-full h-1">
                            <div class="h-1 rounded-full bg-accent transition-all duration-700" :style="{ width: Math.min((page.props.cartTotal / 450000) * 100, 100) + '%' }"></div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <div v-if="page.props.cartItems.length">
                            <div v-for="item in page.props.cartItems" :key="item.presentacion_id" class="px-6 py-4 border-b border-border flex items-start gap-3 hover:bg-surface-2/50 transition">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-medium text-text truncate">{{ item.nombre }}</p>
                                    <p class="text-[11px] text-text-muted mt-0.5">{{ item.marca }} · {{ item.unidad }}</p>
                                    <p class="text-[12px] text-text-secondary mt-0.5">${{ item.precio.toLocaleString('es-AR') }}</p>
                                    <span v-if="item.frio" class="inline-flex items-center gap-1 mt-1 text-[10px] text-sky-400 bg-sky-500/10 px-1.5 py-0.5 rounded-md">❄ Frío</span>
                                    <span v-if="item.congelado" class="inline-flex items-center gap-1 mt-1 text-[10px] text-blue-400 bg-blue-500/10 px-1.5 py-0.5 rounded-md">❄ Congelado</span>
                                </div>
                                <div class="flex items-center bg-surface-3 rounded-lg shrink-0">
                                    <button @click="router.patch(route('cart.update'), { presentacion_id: item.presentacion_id, cantidad: item.cantidad - 1 }, { preserveScroll: true })"
                                        class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">−</button>
                                    <span class="w-6 h-7 flex items-center justify-center text-[12px] font-semibold text-text">{{ item.cantidad }}</span>
                                    <button @click="router.patch(route('cart.update'), { presentacion_id: item.presentacion_id, cantidad: item.cantidad + 1 }, { preserveScroll: true })"
                                        class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">+</button>
                                </div>
                                <p class="text-[13px] font-semibold text-text w-16 text-right shrink-0">${{ item.subtotal.toLocaleString('es-AR') }}</p>
                                <button @click="router.delete(route('cart.remove'), { data: { presentacion_id: item.presentacion_id }, preserveScroll: true })"
                                    class="text-text-muted hover:text-red-400 transition shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center h-full text-text-muted py-16">
                            <svg class="w-12 h-12 text-white-4 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                            </svg>
                            <p class="text-sm mb-2">Carrito vacío</p>
                            <Link :href="route('productos.index')" @click="cartOpen = false" class="text-accent text-sm hover:underline">Explorar productos</Link>
                        </div>
                    </div>

                    <div v-if="page.props.cartItems.length && page.props.cartItems.some(i => i.frio || i.congelado)" class="px-5 py-2.5 border-t border-border bg-sky-500/5">
                        <p class="text-[11px] text-sky-400 leading-relaxed">❄ Tu carrito tiene productos fríos/congelados. Consultá disponibilidad para tu zona.</p>
                    </div>
                    <div v-if="page.props.cartItems.length" class="border-t border-border px-6 py-5 shrink-0">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-text-secondary text-sm">Total</span>
                            <span class="text-xl font-semibold text-text">${{ page.props.cartTotal.toLocaleString('es-AR') }}</span>
                        </div>
                        <Link v-if="page.props.cartTotal >= 300000" :href="route('checkout.index')" @click="cartOpen = false"
                            class="block w-full text-center py-3 rounded-xl font-medium text-[13px] bg-accent text-white hover:bg-accent-bright transition-all">
                            Finalizar compra
                        </Link>
                        <span v-else class="block w-full text-center py-3 rounded-xl text-[13px] bg-surface-3 text-text-muted">Mínimo $300.000</span>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Footer -->
        <footer class="bg-surface-1 border-t border-border mt-16">
            <div class="max-w-[1440px] mx-auto px-6 py-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                    <div class="md:col-span-2">
                        <img src="/images/logo.png" alt="VEGANLIFE" class="h-10 mb-4 opacity-60" />
                        <p class="text-sm text-text-muted leading-relaxed max-w-sm">Distribuidora vegana. Productos naturales, sin crueldad animal.</p>
                        <p class="text-sm text-text-muted mt-2">San Nicolás 1255, Pergamino</p>
                    </div>
                    <div>
                        <h3 class="text-[13px] font-semibold text-text mb-4">Navegación</h3>
                        <div class="space-y-2.5">
                            <Link :href="route('productos.index')" class="block text-sm text-text-muted hover:text-accent transition">Catálogo</Link>
                            <Link :href="route('productos.index', { vista: 'categorias' })" class="block text-sm text-text-muted hover:text-accent transition">Categorías</Link>
                            <Link :href="route('productos.index', { vista: 'marcas' })" class="block text-sm text-text-muted hover:text-accent transition">Marcas</Link>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[13px] font-semibold text-text mb-4">Contacto</h3>
                        <div class="space-y-2.5 text-sm text-text-muted">
                            <p>02477 438329</p>
                            <p>2477504048</p>
                            <a href="https://wa.me/5492477504048" target="_blank" class="inline-block text-accent hover:text-accent-bright transition">WhatsApp</a>
                        </div>
                    </div>
                </div>
                <div class="mt-10 pt-6 border-t border-border text-center text-[11px] text-text-muted">
                    © {{ new Date().getFullYear() }} VEGANLIFE
                </div>
            </div>
        </footer>

        <!-- Floating social buttons -->
        <div class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 flex flex-col gap-2.5">
            <a href="https://www.instagram.com/veganlife.pergamino" target="_blank"
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 flex items-center justify-center shadow-lg shadow-pink-500/20 hover:scale-110 transition-transform"
                title="Instagram">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            </a>
            <a href="https://wa.me/5492477504048" target="_blank"
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-[#25D366] flex items-center justify-center shadow-lg shadow-green-500/20 hover:scale-110 transition-transform"
                title="WhatsApp">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: transform .3s cubic-bezier(.16,1,.3,1); }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
.slide-left-enter-active, .slide-left-leave-active { transition: transform .3s cubic-bezier(.16,1,.3,1); }
.slide-left-enter-from, .slide-left-leave-to { transform: translateX(-100%); }
</style>
