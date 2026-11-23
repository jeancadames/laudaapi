# Diseño del portal del cliente (autoservicio)

Decisiones compartidas para las pantallas que ve el cliente en LAUDA: Inicio,
Panel de control, Conexiones Business, Armar mi paquete y Cuenta.

## Objetivo

Que el cliente se oriente solo: ver qué tiene, abrirlo, agregar lo que le falta
y pagar, sin lenguaje interno ni saltos a «otro sitio».

## Estructura

- **Todo dentro del menú lateral.** Conexiones Business también: con sesión de
  cliente usa `AppLayout`; sin sesión, o en una aplicación receptora, conserva
  su marco propio.
- **Encabezado común** (`components/portal/PortalPage.vue`): empresa · título ·
  una frase · acciones a la derecha (debajo en móvil). Ancho `max-w-6xl`, fondo
  `#F7F7F5`.
- **Secciones** (`PortalSection.vue`): título, conteo y una frase de ayuda.
- **Tarjeta de aplicación** (`AppTile.vue`): sigla, nombre, estado, descripción y
  acciones al pie. Misma forma en Inicio, Panel y Conexiones. Cuadrícula
  1 / 2 / 3 columnas (`sm:grid-cols-2 lg:grid-cols-3`); nunca dos columnas de
  alturas distintas.
- **Menú lateral por tarea:** (sin título) Inicio, Panel de control, Conexiones ·
  Mi paquete · Pagos · Mi empresa · LAUDA 360.

## Color y estados

- Rojo solo para la acción principal de autoservicio (Armar / Agregar a mi
  paquete / Conectar). Negro para «Abrir». Blanco con borde para lo secundario
  (`components/portal/buttons.ts`).
- Verde = activa o conectada · ámbar = pendiente o confirmación · gris = sin
  conectar o disponible.

## Tipografía y espacio

- Título 24–28 px seminegrita; secciones 18 px; texto de apoyo `slate-600`
  (no `slate-400/500` sobre blanco, por contraste).
- Tarjetas `rounded-2xl`, sombra mínima; nada de `rounded-[2rem]` ni paneles
  oscuros decorativos.

## Lenguaje

Sin términos internos frente al cliente: nada de «tenant», «snapshots»,
«entitlements», «App Store», «Owner», «App Hub», números de ID ni zonas horarias
en código (`lib/timezoneLabel.ts` → «hora de Santo Domingo»). Tampoco avisos de
planes futuros («en la siguiente etapa…»).

## Interacción

- Confirmaciones junto a lo que afectan (quitar acceso aparece dentro de la
  tarjeta de la aplicación y recibe el foco).
- Buscador del Panel solo cuando hay más de 4 aplicaciones.
- Móvil: botones a todo el ancho en tarjetas; sin desplazamiento horizontal.
