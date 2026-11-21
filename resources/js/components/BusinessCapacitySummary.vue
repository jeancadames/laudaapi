<script setup lang="ts">
import type { CapacityPolicy } from '@/types/businessCatalog';

withDefaults(
    defineProps<{ policy?: CapacityPolicy | null; hasPos?: boolean }>(),
    { hasPos: true },
);
</script>

<template>
    <div v-if="policy" class="space-y-1 text-sm text-muted-foreground">
        <p v-if="policy.users === 'shared_identity'">
            Usuarios: una misma persona con acceso activo ocupa un solo cupo por
            empresa, compartido entre las soluciones contratadas.
        </p>
        <p v-else>
            Usuarios: cada persona ocupa un cupo por cada solución en la que
            tiene acceso activo, dentro de la misma bolsa de la empresa.
        </p>
        <p>
            {{
                policy.owner_counts
                    ? 'El propietario se cuenta con la misma regla que los demás usuarios.'
                    : 'El propietario no consume cupo de usuario.'
            }}
        </p>
        <template v-if="hasPos">
            <p>
                Cajas: sesiones abiertas simultáneamente, sumadas entre todas
                las sucursales de la empresa.
            </p>
            <p>Sucursales: establecimientos activos de la empresa.</p>
            <p>
                Cantina y autoservicio quedan fuera de estas reglas de
                capacidad.
            </p>
        </template>
    </div>
    <p v-else class="text-sm text-muted-foreground">
        Esta versión no especifica las reglas de conteo de usuarios, cajas y
        sucursales.
    </p>
</template>
