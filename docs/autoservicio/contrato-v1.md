# Contrato de autoservicio Business v1

El [contrato TypeScript con muestras de prueba](contrato-v1.ts) es el formato de intercambio. Laravel implementa el catálogo y el cálculo (bloque 2), junto con la confirmación, facturación y consulta de la compra (bloque 3).

## Conexión de la pantalla de Claude

Inicio devuelve `business_self_service.configurator_url` a dueños y administradores autorizados cuando Business está habilitado. La navegación GET a `/app/business/self-service` renderiza `Business/Package/Build`. La prop `configurator` contiene `company: {id, name}`, `availability` (`available` o `catalog_unpublished`), `catalog` (objeto `Catalog`, o `null` sin versión), `catalog_url`, `calculate_url`, `confirm_url` y `home_url`. Usar el catálogo incluido para la carga inicial y `catalog_url` para actualizarlo. Cuando el checkout está habilitado, la página entrega `confirm_url`. Si una factura sigue en curso, Inicio devuelve `pending_purchase` con su enlace de reanudación. La página y el catálogo incluyen `resume_purchase_id` cuando corresponde. La página lo conserva incluso si el catálogo pierde disponibilidad.

Después de la activación, Inicio devuelve `business_self_service.last_purchase_url` para mostrar «Ver mi paquete». Si hay una compra en curso, su reanudación mantiene prioridad. Los enlaces de compra generados por Laravel usan `?compra=<uuid>`; se admite `?purchase=<uuid>` para enlaces anteriores. En las facturas de autoservicio se ofrece «Ver implementación» cuando los pagos confirmados cubren el total.

El adaptador envía `products`, `level` (inicialmente `initial`) y `cycle` a `calculate_url`. Consume `recurring`, `one_time` y `due_today`, cada uno con `net_cents`, `tax_cents` y `total_cents`. Cada producto trae `includes` para mostrar y `inclusion_codes` para identificar las inclusiones. Las rutas limitan el cálculo a 120 solicitudes por minuto por usuario, con un contador independiente del checkout. Las respuestas de error del API mantienen los códigos de la tabla inferior, incluidos `catalog_unavailable` y `forbidden`.

## Operaciones del portal

Base `/app/business/self-service/v1`. Respuestas JSON; el frontend envía `Accept: application/json`, la sesión del portal y CSRF en los POST. El backend toma la empresa del contexto autorizado, verifica pertenencia y rol activo de dueño o administrador. La sesión debe estar verificada. Respuestas privadas sin caché compartida. Los importes son enteros en centavos de DOP. Las fechas son ISO 8601 con zona.

| Método y ruta relativa | Solicitud | Éxito y respuesta |
|---|---|---|
| GET `/catalog` | Sin parámetros | 200 · `Catalog`. Si hay compra en curso, `resume_purchase_id` permite retomarla incluso si el catálogo cambió. |
| POST `/preview` | `Selection` | 200 · `Preview`. Cotiza para la empresa actual. No genera factura ni inicia un pago. |
| POST `/purchases` | `Confirm` | 201 · `Purchase` al crear. 200 y la misma compra al reintentar la misma operación. |
| GET `/purchases/{purchase_id}` | UUID devuelto por el servidor | 200 · `Purchase` con factura, saldo y estado de pago. Devuelve el acceso de cada aplicación para el usuario que consulta. Funciona aunque la propuesta ya haya vencido. |

Los objetos se devuelven directamente, sin envoltura. Todas las acciones de tipo `Link` son navegación GET a una página que continúa el proceso. La pantalla de destino gestiona sus confirmaciones. La confirmación del ingreso corresponde al administrador de LAUDA.

### Selección y precio

El caso base comienza con `products: ['ecf', 'cumplimiento']`, `level: 'initial'` y `cycle: 'monthly'`. Los cuatro productos facturables están en el catálogo. La lista `includes` explica al cliente que Fácil ECF forma parte de e-CF. Vue utiliza los códigos habilitados por el servidor en cada respuesta del catálogo. Los niveles del catálogo son opciones globales; si una selección carece de tarifa en un nivel, `invalid_selection` indica el campo a corregir. La primera compra de autoservicio usa implementación guiada, sin complementos opcionales. Las opciones asistidas y los cambios de contratos previos tienen recorridos propios.

El backend devuelve la cobertura exacta de menor precio entre opciones equivalentes en nivel, ciclo y alcance. Devuelve cada componente una sola vez. Para anualidad se comparan los precios anuales publicados y las reglas de anualización vigentes. `recurring` contiene la tarifa por ciclo; `one_time`, los cargos únicos; `due_today_cents`, la suma de ambos totales con impuestos. `balance_cents` indica cuánto queda por pagar después de facturar. La capacidad de documentos es mensual también para un cobro anual. La interfaz muestra las condiciones devueltas y solo describe una renovación automática cuando esté pactada.

Las referencias de propuesta son opacas y vinculadas a selección, empresa, condiciones y vencimiento. El cliente solo envía esa referencia, consentimiento explícito y un UUID por intento lógico de compra. Ante pérdida de respuesta vuelve a enviar la misma clave. Ante un cambio de condiciones muestra el nuevo resumen y obtiene una aceptación y clave nuevas. El backend mantiene la compra ya creada recuperable aunque cambie el catálogo. Para una empresa con compra pendiente, el enlace de reanudación debe seguir disponible desde Inicio o el historial aunque ya no se admita una nueva contratación.

El catálogo Business arranca con una tasa del 0 %. En el configurador se omite la fila de impuestos cuando ambos importes `tax_cents` son cero. Si administración publica una tasa para futuras contrataciones, el cliente ve el importe calculado por el servidor antes de aceptar.

### Transferencia y revisión por LAUDA

En una instalación con las migraciones aplicadas, `payable` lleva a la factura mediante la acción «Enviar comprobante». La factura muestra las instrucciones de la cuenta receptora configuradas en `LAUDA_BUSINESS_TRANSFER_INSTRUCTIONS`. El cliente introduce la referencia bancaria y adjunta un PDF, JPG o PNG de hasta 5 MB. El formulario está implementado en la página de facturación, y el configurador puede navegar a la URL devuelta.

| Operación | Solicitud | Resultado |
|---|---|---|
| POST `/purchases/{purchase_id}/proof` | Multipart, campos `reference`, `proof` y `submission_key` (UUID estable por envío). | JSON 200 con `received: true`. La compra pasa a `processing`. La factura conserva el saldo durante la revisión. |
| Administración → Pagos | Un administrador de LAUDA descarga el archivo privado y coteja el movimiento en el banco. | Puede aprobar el importe recibido en la moneda de la factura o rechazar el comprobante con un motivo visible para el cliente. |

Con un rechazo, `failed.message` contiene el motivo y la acción abre la misma factura para corregir el envío. Un abono parcial deja `payable` con el saldo restante. Cuando los pagos confirmados cubren la factura, la compra pasa a `paid`. La revisión conserva el responsable, la referencia y la relación entre comprobante, transacción y pago. Una referencia bancaria confirmada se aplica una sola vez. Cada factura admite un comprobante en revisión a la vez. Los reintentos del mismo envío o aprobación recuperan el resultado.

Con activación e identidad disponibles, la consulta devuelve `link_required` si falta vincular al usuario y `consent_required` si el paquete guiado tiene sus vínculos listos para dar conformidad o si hay una implementación entregada. Solo ofrece `ready` cuando existe cobertura vigente del contrato y el usuario puede abrir la aplicación. La revocación del vínculo o la pérdida de cobertura retira el enlace. En altas guiadas nuevas, el propietario o administrador de la empresa confirma el alcance en la pantalla de implementación. Esa acción registra su conformidad y activa el contrato en la misma transacción, tras comprobar otra vez saldo, membresía, vínculos y conflictos comerciales. «Ver mis aplicaciones» retoma la compra por su UUID incluso después de activar.

### Casos para construir las pantallas

| Muestra exportada | Comportamiento de la pantalla |
|---|---|
| `catalog`, `preview`, `customPreview` | Con el catálogo inicial al 0 %, «Pagas hoy» muestra RD$2,000 por Fiscal (e-CF y Cumplimiento) y RD$3,000 al añadir BYS. Son también los importes mensuales en estas muestras. |
| `catalogUnavailable`, `invalidSelection` | Explicar la indisponibilidad o señalar el campo a corregir, conservando la selección. |
| `priceChanged` | Mostrar el nuevo desglose para revisión antes de cobrar. |
| `payable`, `waitingForPayment`, `failedPayment` | Abrir el formulario desde la acción, mostrar la revisión pendiente y conservar el motivo del rechazo al volver a la factura. |
| `paidNeedsLink`, `active` | Distinguir pago recibido de acceso disponible. Usar la etiqueta y la URL de cada acción devuelta por el servidor. El ejemplo de e-CF abre su dashboard. |

En `processing` se puede actualizar el estado desde el portal. Un contrato de importe cero puede aparecer pagado sin un movimiento bancario. Cuando una factura que ya dio lugar a un contrato vuelve a tener saldo pendiente, la compra devuelve `reversed` y retira las acciones de apertura. En cada consulta se vuelve a comprobar el saldo y la cobertura.

### Errores

Los errores de dominio devuelven directamente `Problem`. Los fallos de sesión, CSRF y límites de frecuencia pueden provenir del middleware Laravel; la interfaz también maneja esos códigos HTTP sin depender de un cuerpo concreto.

| HTTP | Código de dominio o situación | Recuperación |
|---|---|---|
| 401 / 419 / 429 | Sesión caducada, CSRF vencido o demasiadas peticiones | Reautenticar, refrescar la sesión o respetar la espera indicada. Tras una confirmación incierta, recuperar la compra o reutilizar la misma clave. |
| 403 / 404 | `forbidden` / `purchase_not_found` | Orientar al dueño de la empresa. Una compra de otra empresa recibe 404. |
| 409 | `price_changed`, `proposal_expired` | Mostrar `replacement` o volver a cotizar y confirmar explícitamente. |
| 409 | `key_conflict`, `existing_purchase`, `transition_required` | Recuperar el intento original, reanudar la compra devuelta o explicar que hay un contrato previo que requiere calcular el cambio antes de cobrar. |
| 422 | `invalid_selection` | Mostrar errores por campo. |
| 503 | `catalog_unavailable`, `temporarily_unavailable` | Mantener el estado del cliente y permitir reintentar. En catálogo sin publicar no se ofrecen los precios de muestra. |

## Dependencias comprobadas en el flujo actual

| Tramo | Comportamiento actual en el código | Trabajo pendiente |
|---|---|---|
| Oferta y empresa | El [catálogo vigente](../../app/Services/Billing/BusinessCatalogService.php) y el [cálculo](../../app/Services/Billing/BusinessQuoteCalculator.php) trabajan con una oferta elegida. La emisión de la cotización está en [rutas administrativas](../../routes/admin_business.php). | Los bloques 2 y 3 comparan coberturas exactas y registran en una transacción la cotización, el consentimiento y la factura. La factura conserva el desglose que aceptó el cliente. |
| Factura y pago | El cliente adjunta evidencia privada. Administración confirma el ingreso bancario. Laravel concilia el pago, registra la transacción y admite abonos parciales. | Configurar las instrucciones reales de la transferencia y comprobar el circuito con la cuenta receptora de LAUDA. |
| Implementación | El [servicio actual](../../app/Services/Billing/BusinessImplementationService.php) comprueba pagos confirmados de la empresa, exige saldo cubierto y divide las acciones de implementación entre cliente y personal LAUDA. | La modalidad guiada de autoservicio permite completar desde el portal con pago confirmado y vínculos verificados. |
| Activación | La [activación de contrato](../../app/Services/Billing/BusinessContractActivationService.php) verifica implementación completada, factura conciliada, ausencia de conflictos comerciales, vínculo de empresa y miembro habilitado por solución. La operación asistida exige un administrador de plataforma. | La conformidad guiada activa el paquete y conserva la evidencia del vínculo y del usuario por solución. Queda verificar el alta local en las aplicaciones receptoras. |
| Cuenta en cada aplicación | Las [rutas de identidad](../../routes/business_identity.php) permiten vincular y lanzar aplicaciones. Los cuatro receptores verifican un usuario local y contexto empresarial existentes al aceptar el vínculo. e-CF utiliza la cuenta fiscal; POS la empresa actual de su propietario; BYS y Cumplimiento comprueban el rol de propietario para vincular. | Probar con una cuenta completamente nueva el alta local y su regreso al portal en cada solución. Si se desea apertura directa a Fácil, verificar la navegación hasta Fácil desde e-CF. |

### Cómo reproducir la comprobación

Resultado del bloque 1: las cuatro suites del flujo existente pasaron con 129 pruebas y 775 aserciones. Los tipos compilaron en modo estricto. También se verificaron la cobertura exacta y los importes de las tres propuestas de ejemplo, incluida la de precio actualizado.

Pruebas de integración de los bloques 2 y 3 con Laravel y SQLite aislados. Cubren Inicio, permisos, las 15 selecciones en ambos ciclos, tarifa anual explícita, impuesto configurable, aceptación, cambios de precio, reintentos, consulta de saldo y aislamiento entre empresas.

```sh
LAUDA_BUSINESS_MYSQL=0 vendor/bin/phpunit --do-not-cache-result tests/Feature/Billing/BusinessPackagePreviewTest.php
```

Desde la raíz de `laudaapi`, las siguientes suites levantan Laravel con usuarios, facturas y una SQLite sintética en un directorio temporal. Recorren aceptación, factura, conciliación, conformidad y activación actuales. Las pruebas insertan pagos de prueba y simulan los vínculos externos. Antes del despliegue se requiere la migración `2026_11_23_000001_create_business_transfer_proofs`, acceso de escritura al disco privado y las instrucciones de la cuenta receptora de LAUDA. El servidor de subida debe admitir archivos de 5 MB más el cuerpo del formulario. La prueba de comprobantes usa una transferencia sintética y recorre el envío, el rechazo, el reenvío y la aprobación por un administrador.

```sh
LAUDA_BUSINESS_MYSQL=0 vendor/bin/phpunit --do-not-cache-result \
  tests/Feature/Billing/BusinessQuoteAcceptanceTest.php \
  tests/Feature/Billing/BusinessQuoteCheckoutTest.php \
  tests/Feature/Billing/BusinessImplementationTest.php \
  tests/Feature/Billing/BusinessContractActivationTest.php

node_modules/.bin/tsc --noEmit --strict --skipLibCheck --target es2022 docs/autoservicio/contrato-v1.ts
```

## Administrar un paquete activado

La respuesta de la compra incluye `manage_url` cuando existe el contrato y se aplicó la migración de cambios. La página devuelve la selección vigente, las aplicaciones disponibles y las URLs de revisión y confirmación. El propietario o un administrador de la empresa puede usarlas. Una compra de reemplazo incluye `previous_package_url` para volver al cambio registrado.

- La revisión recibe `products` con la selección completa del próximo ciclo. Una lista vacía solicita la baja total. Laravel devuelve los productos, `effective_at`, el importe por ciclo en centavos (`total_cents`) y `terms_hash`. Mantiene el nivel y el ciclo actuales. La revisión no emite factura.
- La confirmación recibe la misma selección, `accepted: true` y el hash revisado. Si cambia el precio o la fecha, el servidor devuelve 409 y se requiere una nueva revisión. Repetir una confirmación idéntica recupera el cambio registrado.
- El acceso anterior vence en el instante indicado, incluidas las renovaciones ya pagadas. Una renovación aceptada pendiente de facturar o conciliar requiere resolverla antes de programar el cambio.
- La baja total tiene importe cero. Para una selección con productos, la confirmación devuelve `purchase_url` y genera una factura por el primer ciclo del paquete nuevo. Se conserva el flujo de transferencia, comprobante y confirmación por LAUDA. El titular puede dar conformidad y activar a partir del vencimiento anterior con pago completo y vínculos verificados.
- El programador de Laravel actualiza cada minuto los estados de las suscripciones y sus productos vencidos. La autorización de acceso comprueba la fecha también entre ejecuciones.

En «Ver mi paquete», `current_period.total_cents` es el precio del período contractual en curso, incluidos impuestos. Al terminar ese período vale `null` si aún no ha empezado otro. `current_period.access_until` indica hasta cuándo se ha confirmado el pago, incluidas las renovaciones prepagadas, y puede ser una fecha pasada. Si el pago inicial se revierte, la fecha es `null`. «Administrar mi paquete» recibe los mismos dos valores en `plan.current`. El resumen de la compra conserva su precio original.

La consulta de la compra anterior devuelve `scheduled_change` mientras haya un cambio registrado con fecha efectiva futura. Contiene `effective_at`, la selección completa de `products` para el siguiente ciclo (vacía para la baja total) y `manage_url` para consultar el detalle. En la fecha efectiva el aviso deja de aparecer. El cambio registrado sigue disponible en «Administrar mi paquete».
