<script setup lang="ts">
import LegalLayout from '@/layouts/LegalLayout.vue';
</script>

<template>
    <LegalLayout>
        <template #header>
            <p class="text-xs uppercase tracking-[0.25em] text-muted-foreground">Legal</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Términos y Condiciones</h1>
            <p class="mt-1 text-sm text-muted-foreground">Última actualización: Enero 2026</p>
        </template>

        <h2>1. Aceptación de los Términos</h2>
        <p>
            Al utilizar LaudaAPI o enviar cualquiera de nuestros formularios, usted confirma que ha leído y
            acepta estos Términos y Condiciones, y que la información proporcionada es veraz y actualizada.
        </p>

        <h2>2. Servicios incluidos en la prueba gratuita</h2>
        <p>La prueba gratuita de <strong>30 días</strong> incluye:</p>
        <ul>
            <li>Acceso limitado a los módulos habilitados.</li>
            <li>Uso razonable de los endpoints disponibles.</li>
            <li>Soporte básico por correo electrónico.</li>
        </ul>

        <p><strong>No incluye:</strong></p>
        <ul>
            <li>Certificaciones adicionales ante la DGII.</li>
            <li>Integraciones personalizadas.</li>
            <li>SLA o soporte prioritario.</li>
        </ul>

        <h2>3. Finalización de la prueba</h2>
        <p>Al finalizar los 30 días:</p>
        <ul>
            <li>El acceso se suspende automáticamente.</li>
            <li>Los datos pueden eliminarse después de 30 días adicionales.</li>
            <li>No se realiza ningún cargo automático.</li>
        </ul>

        <h2>4. Uso permitido</h2>
        <ul>
            <li>No utilizar LaudaAPI para actividades ilegales.</li>
            <li>No intentar vulnerar la seguridad del sistema.</li>
            <li>No realizar ingeniería inversa ni extracción masiva.</li>
        </ul>

        <h2>5. Limitación de responsabilidad</h2>
        <p>
            LaudaAPI no se hace responsable por pérdidas de datos, interrupciones externas o uso indebido de
            la plataforma.
        </p>

        <h2>6. Modificaciones</h2>
        <p>
            Podemos actualizar estos términos en cualquier momento. Los cambios se publicarán en esta página.
        </p>

        <h2>7. Contacto</h2>
        <p>
            Para consultas legales: <a href="mailto:legal@laudaapi.com">legal@laudaapi.com</a>
        </p>
    </LegalLayout>
</template>
