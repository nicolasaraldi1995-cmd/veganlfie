<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({ items: Array, total: Number, envioGratis: Boolean, recomendados: Array, cliente: Object });
const modalImage = ref(null);
const form = useForm({ entrega: 'envio', notas: '' });
function submit() { form.post(route('checkout.store')); }
function updateQty(id, q) { router.patch(route('cart.update'), { presentacion_id: id, cantidad: q }, { preserveScroll: true }); }
function removeItem(id) { router.delete(route('cart.remove'), { data: { presentacion_id: id }, preserveScroll: true }); }
</script>
<template>
    <Head title="Finalizar pedido" />
    <PublicLayout>
        <div class="max-w-5xl mx-auto px-6 py-8">
            <h1 class="text-xl font-semibold text-text mb-6">Revisá tu pedido</h1>
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-surface-1 rounded-2xl border border-border">
                        <div class="px-6 py-4 border-b border-border">
                            <h2 class="font-medium text-text">Productos ({{ items.length }})</h2>
                            <p class="text-[11px] text-text-muted">Modificá cantidades o eliminá antes de confirmar</p>
                        </div>
                        <div class="divide-y divide-border">
                            <div v-for="item in items" :key="item.presentacion_id" class="px-6 py-4 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-medium text-text truncate">
                                        {{ item.nombre }}
                                        <span v-if="item.frio" class="inline-flex items-center gap-0.5 ml-1 text-[10px] text-sky-400 bg-sky-500/10 px-1.5 py-0.5 rounded-md align-middle">❄ Frío</span>
                                        <span v-if="item.congelado" class="inline-flex items-center gap-0.5 ml-1 text-[10px] text-blue-400 bg-blue-500/10 px-1.5 py-0.5 rounded-md align-middle">❄ Congelado</span>
                                    </p>
                                    <p class="text-[11px] text-text-muted">{{ item.marca }} · {{ item.unidad }} · ${{ item.precio.toLocaleString('es-AR') }} c/u</p>
                                </div>
                                <div class="flex items-center bg-surface-3 rounded-lg shrink-0">
                                    <button @click="updateQty(item.presentacion_id, item.cantidad - 1)" class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">−</button>
                                    <span class="w-6 h-7 flex items-center justify-center text-[12px] font-semibold text-text">{{ item.cantidad }}</span>
                                    <button @click="updateQty(item.presentacion_id, item.cantidad + 1)" class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">+</button>
                                </div>
                                <p class="text-[13px] font-semibold text-text w-20 text-right shrink-0">${{ item.subtotal.toLocaleString('es-AR') }}</p>
                                <button @click="removeItem(item.presentacion_id)" class="text-text-muted hover:text-red-400 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-border flex justify-between">
                            <Link :href="route('productos.index')" class="text-[13px] text-accent hover:text-accent-bright transition">+ Agregar más</Link>
                            <span class="font-semibold text-text">${{ total.toLocaleString('es-AR') }}</span>
                        </div>
                    </div>
                    <div class="bg-surface-1 rounded-2xl border border-border p-6">
                        <h2 class="font-medium text-text mb-4">Entrega</h2>
                        <div class="flex gap-3 mb-4">
                            <label v-for="o in [{v:'envio',l:'Envío a domicilio',d:envioGratis?'Gratis':'A coordinar'},{v:'retiro',l:'Retiro en local',d:'San Nicolás 1255'}]" :key="o.v"
                                class="flex-1 border rounded-xl p-4 cursor-pointer transition-all" :class="form.entrega===o.v?'border-accent bg-accent/10':'border-border hover:border-border-hover'">
                                <input v-model="form.entrega" type="radio" :value="o.v" class="hidden" />
                                <p class="text-[13px] font-medium text-text">{{ o.l }}</p>
                                <p class="text-[11px] text-text-muted mt-0.5">{{ o.d }}</p>
                            </label>
                        </div>
                        <label class="block text-[13px] text-text-secondary mb-1.5">Notas (opcional)</label>
                        <textarea v-model="form.notas" rows="2" placeholder="Horario, indicaciones..." class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition"></textarea>
                    </div>
                    <div v-if="recomendados.length">
                        <div class="flex items-center gap-3 mb-5"><div class="w-0.5 h-5 rounded-full bg-accent"></div><h2 class="text-[15px] font-semibold text-text">Te puede interesar</h2></div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3"><ProductCard v-for="p in recomendados" :key="p.id" :producto="p" @image-click="modalImage=$event" /></div>
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <div class="sticky top-20 space-y-4">
                        <div class="bg-surface-1 rounded-2xl border border-border p-6">
                            <div class="flex items-center justify-between mb-3"><h2 class="font-medium text-text">Tus datos</h2><Link :href="route('profile.edit')" class="text-[11px] text-accent hover:text-accent-bright transition">Editar</Link></div>
                            <div class="space-y-1 text-[13px] text-text-secondary">
                                <p class="font-medium text-text">{{ cliente.nombre }}</p>
                                <p v-if="cliente.negocio" class="text-[11px]">{{ cliente.negocio }}</p>
                                <p>{{ cliente.celular }}</p><p>{{ cliente.email }}</p><p>{{ cliente.direccion }}</p>
                                <p>{{ cliente.ciudad }}<span v-if="cliente.provincia">, {{ cliente.provincia }}</span></p>
                            </div>
                        </div>
                        <div v-if="items.some(i => i.frio || i.congelado)" class="bg-sky-500/5 border border-sky-500/15 rounded-2xl px-5 py-4">
                            <div class="flex items-start gap-2.5">
                                <span class="text-sky-400 text-sm mt-0.5">❄</span>
                                <div>
                                    <p class="text-[12px] font-medium text-sky-400 mb-0.5">Productos congelados</p>
                                    <p class="text-[11px] text-text-muted leading-relaxed">Tu pedido incluye productos fríos/congelados. La disponibilidad depende de tu zona y del comisionista asignado. Te confirmaremos por WhatsApp.</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-surface-1 rounded-2xl border border-border p-6">
                            <div class="flex justify-between text-[13px] text-text-secondary mb-1"><span>Subtotal</span><span>${{ total.toLocaleString('es-AR') }}</span></div>
                            <div class="flex justify-between text-[13px] text-text-secondary mb-4"><span>Envío</span><span>{{ form.entrega==='retiro'?'Retiro':(envioGratis?'Gratis':'A coordinar') }}</span></div>
                            <div class="h-px bg-border mb-4"></div>
                            <div class="flex justify-between font-semibold text-lg text-text mb-5"><span>Total</span><span>${{ total.toLocaleString('es-AR') }}</span></div>
                            <form @submit.prevent="submit">
                                <div v-if="Object.keys(form.errors).length" class="mb-3 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-2.5">
                                    <p v-for="(error, key) in form.errors" :key="key" class="text-[12px] text-red-400">{{ error }}</p>
                                </div>
                                <button type="submit" :disabled="form.processing" class="w-full bg-accent hover:bg-accent-bright text-white font-medium py-3 rounded-xl transition-all disabled:opacity-50">{{ form.processing ? 'Procesando...' : 'Confirmar pedido' }}</button>
                            </form>
                            <p class="text-[10px] text-center text-text-muted mt-3">Podés modificar desde "Mis pedidos"</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage=null" />
    </PublicLayout>
</template>
