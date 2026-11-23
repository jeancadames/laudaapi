# «Administrar mi paquete» · decisiones de interfaz y pendientes del backend

Frontend de Claude sobre `6eeb380` (rama `claude/administrar-mi-paquete`). Laravel decide precio, ciclo, fecha y estado. Vue solo compara listas de aplicaciones para nombrar lo que cambia.

## Decisiones compartidas

- **Superficie y objetivo.** Es una pantalla operativa de autoservicio para propietarios con poca práctica tecnológica. El cliente debe entender qué tiene, qué cambia, cuánto pagará y desde cuándo, y confirmar sin miedo a equivocarse.
- **Concepto.** «Antes y después» en una sola lista. Cada aplicación lleva su destino: «Se conserva», «Se agrega» o «Se da de baja». La fecha efectiva es el dato ancla de la revisión.
- **Color por función.** Usa los tokens del portal:
  - Naranja `primary` para la acción principal y la selección.
  - Esmeralda para lo que se conserva y lo completado.
  - Azul cielo para lo que se agrega y las esperas.
  - Ámbar para lo que se da de baja.
  - Rojo solo para la baja total («Revisar la baja del paquete» y «Confirmar la baja»).
- **Tipografía.** Igual que el configurador: título h1 `text-2xl/3xl`, secciones `text-lg`, precio `text-3xl tabular-nums`.
- **Composición.** Una columna de `max-w-2xl`, tarjetas `rounded-xl border`. En móvil los botones ocupan todo el ancho y miden 48 px de alto.
- **Recorrido.** Tres pasos: elegir, revisar y registrado.
  - La baja total va en una sección aparte, separada de quitar una aplicación, y siempre pasa por una revisión.
  - Confirmar exige una casilla de aceptación con la fecha y el importe.
  - El foco pasa al título de cada paso.
- **Después de confirmar.** Dos pasos separados:
  1. «Pagar la factura del paquete nuevo», con el estado real del pago.
  2. «Activar el paquete nuevo», que solo se ofrece con la acción del servidor.

  Así «LAUDA confirmó el pago» y «Ya puedes activar tu nuevo paquete» no se confunden.
- **Compra de reemplazo.** Muestra el aviso «Cambio de paquete» con la fecha y el enlace «Ver el cambio de paquete». Los títulos distinguen tres momentos: pagar, pago confirmado y activación disponible.
- **«Ver mi paquete».** La tarjeta «Tu paquete» muestra la empresa, las aplicaciones, el precio y «Administrar mi paquete», justo debajo del estado.
- **Recuperación.**
  - 409 al confirmar: la interfaz pide otra revisión. Si el precio o la fecha cambió, muestra un aviso y pide aceptar otra vez. Si ya había un cambio registrado, lo muestra.
  - Sin respuesta del servidor: «No sabemos si el cambio quedó registrado», con un reintento idéntico que no duplica el cambio.
  - Doble clic: solo se envía una confirmación.

## Pendientes para Codex

1. **Contrato.** `contrato-v1.ts` no tipa la prop `plan` de `Business/Package/Manage` ni las respuestas de revisión y confirmación. El frontend las copia de `BusinessPackageChanges::page()/review()` en `resources/js/lib/businessPackageManage.ts` (`ManagePlan`, `ChangeReview`, `RegisteredChange`).
2. **`summary.effective_at` en la compra de reemplazo.** Llega en `GET /v1/purchases/{id}` pero no está en `Purchase.summary`. El frontend lo lee con una comprobación (`replacementStart`). Propuesta: añadir `effective_at?: string` al contrato.
3. **409 sin código.** «Cambió la fecha o el precio», «Ya se registró un cambio» y «renovación pendiente» llegan solo con `message`. El frontend los distingue pidiendo otra revisión. Propuesta: códigos `price_changed`, `change_registered` y `renewal_pending`.
4. **Datos de la revisión.** La revisión no devuelve `cycle`, `currency` ni `components`. El frontend usa `plan.current.cycle` y DOP. Con `components` se podría explicar la tarifa igual que en «Tu plan personalizado».
5. **Antes de revisar faltan dos datos.** `plan` no trae el precio actual ni la fecha hasta la que hay acceso pagado, así que la pantalla de elección solo dice «hasta el final del período pagado». Propuesta: `current.total_cents` y `current.access_until`.
6. **La compra anterior no sabe del cambio.** `GET /v1/purchases/{id}` de la compra anterior no indica que hay un cambio programado, así que «Ver mi paquete» no lo anuncia hasta abrir «Administrar». Propuesta: `scheduled_change: { effective_at, products, manage_url } | null`.
7. **Zona horaria.** La compra no trae `timezone`. En la compra de reemplazo la fecha se muestra con `America/Santo_Domingo`; `plan.timezone` sí se usa en «Administrar».
8. **Sin verificar.**
   - La pantalla de la compra anterior cuando ya venció (todos los accesos `unavailable`).
   - A qué compra apunta `last_purchase_url` de Inicio después de un cambio.
