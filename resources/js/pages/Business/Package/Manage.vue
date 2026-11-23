<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Change = {
    products: string[];
    effective_at: string;
    total_cents: number;
    terms_hash: string;
    purchase_url?: string | null;
};

const { plan } = defineProps<{ plan: {
    current: { products: string[]; cycle: 'monthly' | 'yearly' };
    available: string[];
    names: Record<string, string>;
    company: string;
    timezone: string;
    change: Change | null;
    preview_url: string;
    confirm_url: string;
    back_url: string;
} }>();

const products = ref([...plan.current.products]);
const reviewed = ref<Change | null>(null);
const saved = ref(plan.change);
const busy = ref(false);
const error = ref('');
const result = computed(() => saved.value ?? reviewed.value);
const different = computed(() => [...products.value].sort().join() !== [...plan.current.products].sort().join());
const removed = computed(() => result.value ? plan.current.products.filter(code => !result.value!.products.includes(code)) : []);
const added = computed(() => result.value?.products.filter(code => !plan.current.products.includes(code)) ?? []);
const names = (values: string[]) => values.map(code => plan.names[code] ?? code).join(', ');
const date = (value: string) => new Intl.DateTimeFormat('es-DO', {
    dateStyle: 'long', timeStyle: 'short', timeZone: plan.timezone,
}).format(new Date(value));
const money = (cents: number) => new Intl.NumberFormat('es-DO', {
    style: 'currency', currency: 'DOP',
}).format(cents / 100);

async function submit(confirm: boolean) {
    if (busy.value || saved.value || (confirm && !reviewed.value)) return;
    busy.value = true;
    error.value = '';
    try {
        const response = await fetch(confirm ? plan.confirm_url : plan.preview_url, {
            method: 'POST', credentials: 'same-origin', redirect: 'error',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
            body: JSON.stringify(confirm
                ? { products: reviewed.value!.products, terms_hash: reviewed.value!.terms_hash, accepted: true }
                : { products: products.value }),
        });
        const data = await response.json();
        if (!response.ok) {
            if (response.status === 409) reviewed.value = null;
            error.value = data.message ?? 'Revisa la selección y vuelve a intentarlo.';
        } else if (confirm) {
            saved.value = data;
        } else {
            reviewed.value = data;
        }
    } catch {
        error.value = 'No pudimos consultar el resultado. Vuelve a intentarlo.';
    } finally {
        busy.value = false;
    }
}

function edit() {
    reviewed.value = null;
    error.value = '';
}
</script>

<template>
    <Head title="Administrar mi paquete" />
    <AppLayout>
        <main class="mx-auto w-full max-w-2xl space-y-7 px-4 py-8 sm:px-6">
            <Link :href="plan.back_url" class="inline-flex min-h-11 items-center text-sm underline">Volver a mi paquete</Link>
            <header class="space-y-2">
                <p class="text-sm text-muted-foreground">{{ plan.company }}</p>
                <h1 class="text-3xl font-semibold tracking-tight">Administrar mi paquete</h1>
                <p class="text-muted-foreground">Paquete contratado · {{ names(plan.current.products) }}</p>
            </header>
            <form v-if="!result" class="space-y-5" @submit.prevent="submit(false)">
                <fieldset :disabled="busy" class="space-y-2">
                    <legend class="mb-2 font-semibold">Aplicaciones para el próximo período</legend>
                    <p class="pb-2 text-sm text-muted-foreground">Elige las que quieres conservar o agregar. Tu acceso actual continúa hasta el final del período pagado.</p>
                    <label v-for="(_, code) in plan.names" :key="code" class="flex min-h-12 items-center gap-3 border-b py-2">
                        <input v-model="products" type="checkbox" :value="code" :disabled="!plan.available.includes(code) && !products.includes(code)" class="size-5" />
                        {{ plan.names[code] }}
                    </label>
                </fieldset>
                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :disabled="busy || !different">{{ busy ? 'Consultando…' : 'Revisar cambio' }}</Button>
                    <Button type="button" variant="outline" :disabled="busy" @click="products = []; submit(false)">Dar de baja todo el paquete</Button>
                </div>
            </form>
            <section v-else class="space-y-4" aria-live="polite">
                <h2 class="text-xl font-semibold">{{ saved ? 'Cambio registrado' : result.products.length ? 'Revisa tu próximo paquete' : 'Revisa la baja del paquete' }}</h2>
                <dl class="space-y-3 rounded-lg border p-4">
                    <div><dt class="text-sm text-muted-foreground">Fin del paquete actual</dt><dd>{{ date(result.effective_at) }} ({{ plan.timezone }})</dd></div>
                    <div><dt class="text-sm text-muted-foreground">Aplicaciones del próximo período</dt><dd>{{ result.products.length ? names(result.products) : 'Ninguna' }}</dd></div>
                    <div v-if="removed.length && result.products.length"><dt class="text-sm text-muted-foreground">Se dan de baja</dt><dd>{{ names(removed) }}</dd></div>
                    <div v-if="added.length"><dt class="text-sm text-muted-foreground">Se agregan</dt><dd>{{ names(added) }}</dd></div>
                    <div><dt class="text-sm text-muted-foreground">Precio {{ plan.current.cycle === 'yearly' ? 'anual' : 'mensual' }}</dt><dd class="text-lg font-semibold">{{ money(result.total_cents) }}</dd></div>
                </dl>
                <p class="text-sm">{{ result.products.length
                    ? 'La confirmación genera la factura del paquete nuevo. Puedes enviar el comprobante desde la compra. Podrás activar las aplicaciones a partir de la fecha indicada, una vez que LAUDA confirme el ingreso.'
                    : 'El acceso termina en la fecha indicada. La baja no genera otra factura.' }}</p>
                <div v-if="!saved" class="flex flex-wrap gap-3">
                    <Button :disabled="busy" @click="submit(true)">{{ busy ? 'Registrando…' : result.products.length ? 'Confirmar cambio' : 'Confirmar baja' }}</Button>
                    <Button variant="outline" :disabled="busy" @click="edit">Editar selección</Button>
                </div>
                <Button v-if="saved?.purchase_url" as-child><Link :href="saved.purchase_url">Ver mi nueva compra</Link></Button>
            </section>
            <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
        </main>
    </AppLayout>
</template>
