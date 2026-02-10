<script setup lang="ts">
import LegalLayout from '@/layouts/LegalLayout.vue';
</script>

<template>
    <LegalLayout>
        <template #header>
            <p class="text-xs uppercase tracking-[0.25em] text-muted-foreground">Legal</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Política de Privacidad</h1>
            <p class="mt-1 text-sm text-muted-foreground">Última actualización: Enero 2026</p>
        </template>

        <p>
            En <strong>LaudaAPI</strong> protegemos su información conforme a la Ley 172-13 de la República
            Dominicana y buenas prácticas internacionales inspiradas en el GDPR.
        </p>

        <h2>1. Datos que recopilamos</h2>
        <h3>Formulario de contacto</h3>
        <ul>
            <li>Nombre</li>
            <li>Email</li>
            <li>Teléfono</li>
            <li>Empresa</li>
            <li>Mensaje</li>
        </ul>

        <h3>Formulario de activación</h3>
        <ul>
            <li>Rol</li>
            <li>Sistema utilizado</li>
            <li>Volumen estimado</li>
            <li>Información operativa</li>
        </ul>

        <h2>2. Finalidad del tratamiento</h2>
        <ul>
            <li>Responder consultas.</li>
            <li>Activar la prueba gratuita.</li>
            <li>Proveer soporte técnico.</li>
            <li>Mejorar nuestros servicios.</li>
        </ul>

        <h2>3. Base legal</h2>
        <p>
            El tratamiento se realiza con su <strong>consentimiento explícito</strong>, otorgado al aceptar los
            Términos y Condiciones.
        </p>

        <h2>4. Conservación de datos</h2>
        <ul>
            <li>Contacto: hasta 12 meses.</li>
            <li>Activación: hasta 24 meses.</li>
            <li>Prueba: 30 días + 30 días de retención.</li>
        </ul>

        <h2>5. Derechos del usuario</h2>
        <p>Puede solicitar:</p>
        <ul>
            <li>Acceso</li>
            <li>Rectificación</li>
            <li>Eliminación</li>
            <li>Oposición</li>
            <li>Portabilidad</li>
        </ul>

        <p>
            Escriba a: <a href="mailto:privacidad@laudaapi.com">privacidad@laudaapi.com</a>
        </p>

        <h2>6. Seguridad</h2>
        <p>
            Implementamos cifrado, control de acceso, auditoría y medidas técnicas para proteger sus datos.
        </p>

        <h2>7. Transferencias</h2>
        <p>
            No vendemos datos. Solo compartimos información con proveedores esenciales bajo acuerdos de
            confidencialidad.
        </p>

        <h2>8. Actualizaciones</h2>
        <p>
            Podemos actualizar esta política en cualquier momento. La versión vigente estará disponible aquí.
        </p>
    </LegalLayout>
</template>
