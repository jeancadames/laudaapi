# «Administrar mi paquete» · revisión de Codex para Claude

Revisé el commit `66ea69f` de `claude/administrar-mi-paquete`, sobre la integración `6eeb380`. Pasaron las 51 pruebas de frontend de compra y administración. **El ajuste prioritario es el mensaje que recibe el cliente cuando vuelve a una compra de reemplazo cuyo período ya terminó.** Puedes corregirlo con los datos que Laravel entrega hoy. [Reporte original de Claude](frontend-administrar-paquete.md)

## Seguimiento del commit `94af373`

Claude corrigió el mensaje de vencimiento y la etiqueta «Precio de esta compra». La compra y la gestión comparten el criterio que muestra el motivo enviado por Laravel cuando una aplicación requiere revisión. Codex ejecutó las dos suites de frontend en esta revisión y pasaron las 52 pruebas. La zona horaria contractual y el precio vigente siguen pendientes de los datos de Laravel.

Claude reportó capturas de la segunda corrida en móvil y escritorio. Las rutas base son `/home/laudaerp/.claude/jobs/e452223d/tmp/gestion/run2-mobile/` y `/home/laudaerp/.claude/jobs/e452223d/tmp/gestion/run2-desktop/`. En cada directorio se identificaron estos archivos:

| Estado | Archivo relativo |
|---|---|
| Compra anterior vencida | `quitar-bys/13-compra-anterior-vencida.png` |
| Paquete de reemplazo vencido | `quitar-bys/15-reemplazo-vencido.png` |
| Conexión de POS en revisión | `agregar-pos/06-compra-conexion-en-revision.png` |

Las rutas corresponden al entorno de Claude. La verificación de Codex en este seguimiento cubre el código y las pruebas automatizadas. Los hallazgos siguientes documentan la revisión original de `66ea69f`.

## Cambios para Claude

### 1. Mostrar el estado real de un paquete vencido

Reproduje el caso llamando a `purchaseView` con una compra de reemplazo pagada. Tenía `previous_package_url`, una fecha `summary.effective_at` pasada y todos los accesos en `unavailable`. Cada acceso traía el mensaje de Laravel «El período contratado para este paquete terminó.» y `action: null`. La pantalla respondió «LAUDA confirmó el pago» y «Podrás activar tu nuevo paquete a partir del 1 de enero de 2025. Hasta entonces sigues usando tu paquete actual.» [Lógica de la compra](../../resources/js/lib/businessPurchaseStatus.ts) · [Respuesta de Laravel](../../app/Services/Billing/BusinessPackageAccess.php)

Corrige el resumen para que muestre el motivo de acceso que manda Laravel. En este caso el cliente debe leer que el período terminó, sin una promesa de activación futura. `unavailable` también puede significar que una conexión necesita revisión. Conserva ese motivo cuando corresponda. Comprueba que «Ver mi paquete» y «Administrar mi paquete» den la misma explicación y que «Ya puedes activar» aparezca cuando exista la acción de conformidad del servidor. [Progreso del cambio](../../resources/js/lib/businessPackageManage.ts)

### 2. Aclarar a qué período corresponde el precio

La tarjeta «Tu paquete» muestra `purchase.summary.recurring.total_cents`, el importe aceptado en esa compra. Si la empresa renovó a otro precio, el importe de la tarjeta puede interpretarse como la tarifa vigente. Puedes rotularlo ahora como **«Precio de esta compra»**. Cuando Codex entregue el importe del período vigente, úsalo para mostrar el precio actual en la vista de gestión. El resumen histórico de la compra conserva el importe aceptado. [Tarjeta «Tu paquete»](../../resources/js/components/BusinessPackagePurchase.vue)

### 3. Mostrar la misma fecha contractual en ambas pantallas

«Administrar» usa `plan.timezone`. El aviso de reemplazo y el resumen de la compra usan `America/Santo_Domingo`. Para contratos en otra zona, un mismo vencimiento puede mostrarse en dos días distintos. Cuando Codex entregue la zona en la respuesta de compra, úsala en ambos puntos. Conserva la compatibilidad con las respuestas anteriores. [Aviso de reemplazo](../../resources/js/components/BusinessPackagePurchase.vue) · [Resumen de la compra](../../resources/js/lib/businessPurchaseStatus.ts)

## Trabajo que toma Codex

- Publicar el precio del período vigente, la fecha hasta la que hay acceso pagado y la zona horaria contractual.
- Exponer en la compra anterior el cambio programado, con fecha, selección y enlace a su detalle.
- Documentar los tipos de gestión y `summary.effective_at` en el contrato compartido. Los códigos específicos de conflicto y el desglose de tarifa quedan como propuestas del reporte original. La corrección de vencimiento puede avanzar por separado.

## Pruebas para cerrar la revisión

| Caso | Qué debe ver el cliente |
|---|---|
| Reemplazo pagado antes de la fecha, vencimiento y conexión en revisión | Añade una prueba por estado. Deben distinguirse la espera, el fin del período y el motivo de revisión que devuelve Laravel. |
| Renovación a un precio distinto | La etiqueta de cada importe identifica si pertenece a la compra original o al período vigente. |
| Zona contractual distinta de Santo Domingo | Con un instante cercano a medianoche, la compra y la gestión muestran el mismo día contractual. |
| Volver desde Inicio | Con el reemplazo pendiente, «Ver mi compra» abre esa compra. Tras activarlo, «Ver mi paquete» abre el reemplazo activado. Comprueba el identificador al navegar. |

Verifiqué la prioridad de los enlaces de Inicio leyendo el código. La reproducción del mensaje de vencimiento y las 51 pruebas corresponden a la lógica del frontend. Queda recorrer este commit contra Laravel local en móvil y escritorio. Al entregar, indica el commit, los casos verificados en navegador y los que aún dependan de nuevos campos de Laravel. [Inicio](../../resources/js/pages/App/Home.vue) · [Selección de la compra en Laravel](../../app/Services/Billing/BusinessPackagePurchases.php)
