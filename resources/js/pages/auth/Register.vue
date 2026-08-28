<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
</script>

<template>
    <AuthBase
        title="Crear tu cuenta LAUDAAPI"
        description="Una cuenta para gestionar tu empresa, contratar soluciones y acceder al ecosistema LAUDAAPI."
    >
        <Head title="Crear cuenta" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Nombre completo</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Nombre completo"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="correo@ejemplo.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Contraseña"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        Confirmar contraseña
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmar contraseña"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-start gap-3">
                        <input
                            id="terms"
                            type="checkbox"
                            name="terms"
                            value="1"
                            required
                            :tabindex="5"
                            class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-red-600"
                        />

                        <label
                            for="terms"
                            class="cursor-pointer text-sm leading-relaxed font-medium"
                        >
                            Acepto los
                            <a
                                href="/legal"
                                target="_blank"
                                class="font-bold text-red-600 hover:underline"
                            >
                                Términos de Uso
                            </a>
                            y la
                            <a
                                href="/legal"
                                target="_blank"
                                class="font-bold text-red-600 hover:underline"
                            >
                                Política de Privacidad
                            </a>
                            de LAUDAAPI.
                        </label>
                    </div>

                    <InputError :message="errors.terms" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :tabindex="6"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes una cuenta?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="7"
                >
                    Iniciar sesión
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
