<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    account: {
        name: string;
        email: string;
    };
    defaults: {
        country_code: string;
        currency: string;
        timezone: string;
    };
}>();
</script>

<template>
    <AuthBase
        title="Configura tu cuenta LAUDAAPI"
        description="Completa la información básica de tu empresa. Cada solución mantiene su propio onboarding operativo."
    >
        <Head title="Configurar cuenta" />

        <div class="space-y-6">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border p-3">
                    <UserRound class="mb-2 h-5 w-5 text-red-600" />
                    <p class="text-sm font-bold">1. Tu cuenta</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Identidad central
                    </p>
                </div>

                <div class="rounded-xl border p-3">
                    <Building2 class="mb-2 h-5 w-5 text-red-600" />
                    <p class="text-sm font-bold">2. Tu empresa</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Datos del App Hub
                    </p>
                </div>

                <div class="rounded-xl border p-3">
                    <CheckCircle2 class="mb-2 h-5 w-5 text-red-600" />
                    <p class="text-sm font-bold">3. Listo</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Explora soluciones
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-muted/30 p-4 text-sm">
                <p class="font-semibold">
                    {{ account.email }}
                </p>
                <p class="mt-1 text-muted-foreground">
                    Esta es tu cuenta central LAUDAAPI.
                </p>
            </div>

            <Form
                action="/onboarding"
                method="post"
                v-slot="{ errors, processing }"
                class="space-y-5"
            >
                <div class="grid gap-2">
                    <Label for="name">
                        Tu nombre
                    </Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="name"
                        :value="props.account.name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">
                        Teléfono
                    </Label>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        autocomplete="tel"
                        placeholder="Ej. 809-555-0000"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="border-t pt-5">
                    <h2 class="font-bold">
                        Empresa
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Esta información pertenece al App Hub.
                        POS, e-CF, CRM, Social y las demás soluciones
                        conservan su configuración propia.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="company_name">
                        Nombre comercial
                    </Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        required
                        placeholder="Nombre de tu empresa"
                    />
                    <InputError :message="errors.company_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="legal_name">
                        Razón social
                    </Label>
                    <Input
                        id="legal_name"
                        name="legal_name"
                        placeholder="Opcional"
                    />
                    <InputError :message="errors.legal_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax_id">
                        RNC / identificación fiscal
                    </Label>
                    <Input
                        id="tax_id"
                        name="tax_id"
                        placeholder="Opcional"
                    />
                    <InputError :message="errors.tax_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="country_code">
                            País
                        </Label>
                        <Input
                            id="country_code"
                            name="country_code"
                            required
                            maxlength="2"
                            class="uppercase"
                            :value="props.defaults.country_code"
                        />
                        <p class="text-xs text-muted-foreground">
                            Código ISO: DO, US, etc.
                        </p>
                        <InputError :message="errors.country_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">
                            Moneda
                        </Label>
                        <select
                            id="currency"
                            name="currency"
                            required
                            :value="props.defaults.currency"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="DOP">
                                DOP · Peso dominicano
                            </option>
                            <option value="USD">
                                USD · Dólar
                            </option>
                            <option value="EUR">
                                EUR · Euro
                            </option>
                        </select>
                        <InputError :message="errors.currency" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="company_size">
                        Tamaño aproximado
                    </Label>
                    <select
                        id="company_size"
                        name="company_size"
                        required
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="" disabled selected>
                            Selecciona una opción
                        </option>
                        <option value="1-10">
                            1–10 personas
                        </option>
                        <option value="11-50">
                            11–50 personas
                        </option>
                        <option value="51-200">
                            51–200 personas
                        </option>
                        <option value="201+">
                            201 o más
                        </option>
                    </select>
                    <InputError :message="errors.company_size" />
                </div>

                <input
                    type="hidden"
                    name="timezone"
                    :value="props.defaults.timezone"
                />

                <InputError :message="errors.timezone" />

                <div class="flex gap-3 rounded-xl border p-4">
                    <ShieldCheck
                        class="mt-0.5 h-5 w-5 shrink-0 text-red-600"
                    />
                    <p class="text-sm leading-6 text-muted-foreground">
                        Completar este formulario no compra ni activa
                        ninguna solución. Después podrás explorar planes
                        o iniciar Transformación 360.
                    </p>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Finalizar configuración
                </Button>
            </Form>
        </div>
    </AuthBase>
</template>
