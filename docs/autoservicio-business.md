# Autoservicio Business · Plan compartido para Codex y Claude

Un dueño de negocio debe poder elegir las aplicaciones que necesita, saber cuánto pagará, contratar y abrirlas desde su celular. La prueba de éxito es completar una compra de prueba con una cuenta de cliente, con una transferencia y su comprobante. LAUDA verifica el ingreso en el banco y confirma el importe recibido. El objetivo posterior es activar automáticamente al cumplirse los requisitos de implementación e identidad.

## Responsables y alcance

| Responsable | Entrega | Revisa |
|---|---|---|
| Codex | Backend Laravel de `laudaapi`, catálogo, cálculo, contratación, cobro, activación y pruebas de integración | El recorrido Vue de Claude con el backend real |
| Claude | Frontend Vue/Inertia de `laudaapi`, selección, resumen, pago, recuperación y acceso a las aplicaciones | El contrato del backend, los cálculos y las pruebas de Codex |

El repositorio principal es `laudaapi`. e-CF, POS, Cumplimiento y BYS participan como soluciones conectadas. En la primera entrega técnica, Codex comprobará si sus integraciones permiten que una empresa nueva obtenga acceso por sí misma. Si alguna exige un cambio en otro repositorio, identificará la operación concreta y acordaremos su alcance antes de modificarlo.

## 1. Fijar el contrato y probar las dependencias

Entrega del bloque 1 para Claude y Codex: [contrato HTTP v1, estados y dependencias verificadas](autoservicio/contrato-v1.md) y [tipos y ejemplos para el frontend](autoservicio/contrato-v1.ts).

**Codex primero.** Seguir una compra en entorno de pruebas desde una cuenta nueva. Identificar cómo se presenta el comprobante y cómo LAUDA confirma la transacción, cómo se establece el vínculo de la empresa con cada solución y qué exige la implementación guiada. Hoy el checkout crea una factura, el cliente puede ver la activación y la acción de activar pertenece a administración. Registrar qué paso ya funciona, cuál requiere implementación y qué datos de la cuenta receptora se necesitan para probarlo.

Entregar a Claude el contrato versionado, las rutas y ejemplos de respuesta para estas operaciones. Son operaciones propuestas. Se reutilizarán las rutas existentes donde ya cubran la necesidad.

| Operación | Entrada del cliente | Resultado que consume la interfaz |
|---|---|---|
| Cargar el configurador o retomar una compra | Sesión y empresa autorizada | Productos disponibles, inclusiones, opciones válidas y compra pendiente si existe |
| Calcular el paquete | Identificadores de productos, ciclo y opciones elegidas | Selección aplicada, paquete o componentes, capacidades, impuestos, cargos únicos, total de hoy, importe por período, moneda y referencia de la propuesta |
| Confirmar la compra | Referencia de la propuesta revisada, aceptación y clave para reconocer reintentos | Identificador de compra y siguiente acción. Si el catálogo cambió, resumen actualizado para que el cliente confirme el nuevo importe |
| Consultar o retomar el estado | Identificador de compra | Estado de pago y acceso por solución, motivo cuando falte un paso, acción disponible y URL cuando proceda |

Los importes viajan en centavos enteros y con moneda explícita. Laravel resuelve la empresa y los permisos, calcula el precio y verifica el pago. Vue presenta los importes y las acciones devueltos. El contrato incluirá ejemplos de éxito, catálogo sin publicar, selección inválida, precio actualizado, pago pendiente, pago fallido, vinculación pendiente y acceso disponible.

**Claude en paralelo.** Diseñar el flujo móvil y los textos con esos escenarios. Las muestras provisionales sirven para diseñar. La integración y la revisión final se hacen con las respuestas reales.

## 2. Elegir aplicaciones y calcular un precio verificable

**Codex.** Ampliar el cálculo actual, que parte de una oferta individual o un paquete predefinido. Evaluar combinaciones de ofertas publicadas que cubran exactamente las aplicaciones elegidas. Comparar el mismo ciclo, capacidades y condiciones equivalentes. Aplicar la combinación válida más económica, sin duplicar aplicaciones, cargos de implementación, capacidad compartida ni descuentos. En empate, preferir la combinación con menos componentes y aplicar un orden estable. Guardar el desglose que explique el resultado. Comprobar cómo se conservan las capacidades de empresa, los términos aceptados y la tarifa anual explícita cuando exista. Mantener legibles los contratos y las cotizaciones anteriores.

**Claude.** Añadir «Armar mi paquete» al inicio de las cuentas con permiso para contratar. La primera pregunta será «¿Qué quieres hacer en tu negocio?». Cada opción nombra la tarea y la aplicación. Indicar «Incluye Fácil ECF» en facturación electrónica y «Incluye comercio electrónico estándar» en POS. Al marcar o quitar opciones, actualizar el resumen y descartar respuestas atrasadas. Conservar la selección al volver desde el resumen.

## 3. Contratar, pagar y activar

**Codex.** Conectar la propuesta confirmada con la aceptación y la facturación existentes. Conservar el precio y las condiciones aceptadas, comprobar los permisos de la empresa y hacer los reintentos seguros. El cliente adjunta el comprobante a la factura. En Administración → Pagos, LAUDA coteja la referencia y el importe con el banco. Al aprobar, Laravel registra la transacción, aplica el pago y recalcula el saldo. Al rechazar, devuelve el motivo y permite presentar otro comprobante. Adaptar el flujo guiado para que el cliente pueda cumplir los requisitos de identidad, vinculación y conformidad. Automatizar la activación cuando se verifiquen el pago y esos requisitos. Registrar la evidencia real de cada comprobación. Si un paso falla después del pago, reanudar desde ese punto conservando factura y pago.

**Claude.** El resumen muestra qué incluye, «Pagas hoy», el importe por mes o año y cualquier cargo único. Explicar las condiciones de renovación que devuelva el servidor. Después del pago, mostrar una acción principal según el estado, por ejemplo «Enviar comprobante», «Vincular mi empresa» o «Abrir Fácil ECF». Al volver de la factura o entrar de nuevo desde Inicio, recuperar la compra. Un pago pendiente y un pago recibido con activación pendiente deben tener mensajes distintos.

**Empresas con contratos previos.** La lógica actual revisa servicios activos y períodos ya pagados. Incluir este recorrido como entrega diferenciada: calcular qué se conserva, cuándo entra el nuevo paquete y cuánto se cobra; mostrar el cambio para aceptación y evitar solapamientos. Validar las reglas de crédito o prorrateo con el responsable comercial si la política vigente no cubre el caso. Se puede verificar primero el alta nueva. El cambio de paquete para clientes existentes tendrá su propia prueba de autoservicio.

El alta guiada registra la conformidad explícita de la empresa cuando se han confirmado el pago y los vínculos bilaterales. Laravel registra la conformidad y el contrato de forma atómica. La compra vuelve a comprobar la cobertura individual al mostrar las aplicaciones. Quedan por validar el alta desde cero en las soluciones conectadas y el cambio de paquete para contratos previos.

## 4. Revisión cruzada e integración

Trabajar en ramas o copias de trabajo separadas a partir de la misma revisión. Codex mantiene rutas, servicios, modelos y pruebas PHP. Claude mantiene páginas, componentes, tipos de consumo y pruebas de interfaz. Si cambia el contrato, quien propone el cambio actualiza el ejemplo afectado y avisa al otro antes de integrar.

1. **Codex entrega backend a Claude.** Compartir revisión o diff, contrato, ejemplos y comandos de prueba. Claude compara las respuestas con la interfaz y señala cálculos dudosos, estados sin salida o datos faltantes. Codex corrige y aporta el resultado de las pruebas.
2. **Claude entrega frontend a Codex.** Compartir revisión o diff, pasos para reproducir y evidencia móvil y de escritorio. Codex verifica permisos, correspondencia de importes, navegación atrás, interrupciones y que los enlaces de acceso solo aparezcan cuando la solución esté disponible. Claude corrige y vuelve a verificar.
3. **Cerrar juntos en entorno de pruebas.** Repetir la compra integrada desde Inicio hasta abrir las aplicaciones. Registrar el resultado de cada caso y resolver los hallazgos de ambos revisores. Para validar comprensibilidad, pedir a una persona poco familiarizada con LAUDA que complete la tarea desde su celular y observar dónde necesita ayuda.

**Prueba integrada del portal.** Claude informó 17 de 17 pasos en móvil (390 px) y 17 de 17 en escritorio al combinar el frontend `43ac32d` con el backend de trabajo en una copia temporal. Probó el paquete Fiscal de RD$2,000, el rechazo y reenvío de un comprobante, un abono de RD$500, la conciliación del saldo, la conformidad y el regreso a la misma compra. Para el lanzamiento introdujo un vínculo sintético y comprobó la redirección de e-CF. La primera vinculación y la apertura en cada aplicación receptora siguen pendientes de validación con una empresa nueva.

## Casos de aceptación compartidos

| Caso | Resultado esperado |
|---|---|
| e-CF + Cumplimiento | Con el catálogo inicial de prueba, Fiscal da una base mensual de RD$2,000. Fácil ECF aparece incluido. Con la tasa inicial del 0 %, «Pagas hoy» muestra RD$2,000. La fila de impuestos aparece cuando hay un importe que cobrar. |
| e-CF + Cumplimiento + BYS | Con las mismas tarifas de prueba, Fiscal + BYS da una base mensual de RD$3,000. Se conceden exactamente tres productos y se aplican una sola vez las capacidades compartidas que correspondan. |
| Cuatro productos y anualidad | El motor compara todas las alternativas equivalentes usando las tarifas del ciclo elegido. La factura coincide con el resumen aceptado. |
| Catálogo actualizado durante la compra | Se conserva la selección y se solicita aceptación del nuevo importe antes de crear el cobro. |
| Doble clic, recarga o confirmación administrativa repetida | Una compra, una factura y una activación. El cliente puede retomar el mismo proceso. |
| Pago rechazado, pendiente o confirmado con un vínculo pendiente | La interfaz indica lo ocurrido y la acción disponible. Al completarse el vínculo se continúa sin cobrar de nuevo. |
| Empresa con servicios pagados o usuario sin permiso | La primera ve cómo se tratará su período pagado antes de aceptar el cambio. El segundo recibe orientación y no puede contratar por esa empresa. |
| Persona que usa solo el celular | Puede explicar qué eligió, cuánto pagará hoy y por período, volver a editar y abrir la solución contratada sin instrucciones del equipo. |

Los importes de los ejemplos son datos de prueba del catálogo inicial. El configurador debe consumir exclusivamente la versión publicada y vigente para las compras reales.

## Punto de partida en el código

- [Catálogo inicial](../database/seeders/BusinessCatalogSeeder.php) y [cálculo de cotización](../app/Services/Billing/BusinessQuoteCalculator.php).
- [Emisión administrativa](../routes/admin_business.php), [operaciones del cliente](../routes/business_quotes.php) y [facturación](../app/Services/Billing/BusinessQuoteCheckoutService.php).
- [Requisitos de activación](../app/Services/Billing/BusinessContractActivationService.php) e [implementación](../app/Services/Billing/BusinessImplementationService.php).
- [Inicio del cliente](../resources/js/pages/App/Home.vue) y [pantallas Business existentes](../resources/js/pages/Business).
