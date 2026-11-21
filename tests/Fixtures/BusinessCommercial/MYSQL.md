# Admisión comercial compartida: QA MySQL

El ejecutor verifica ocho escenarios centrales en sus dos órdenes de llegada. Reutiliza el arranque, manifiestos y limpieza de Business F2 y la validación de instancia descartable de BYS. No arranca MySQL, no carga configuración operativa y no sustituye MySQL por SQLite cuando falla la conexión.

Con `BYS_MYSQL_RUNTIME` definido con la ruta de una instancia descartable **ya disponible y validada** (`/tmp/bys-mysql-check-` seguido de 16 dígitos hexadecimales):

```bash
python3 tests/Fixtures/BusinessCommercial/mysql_check.py --runtime="$BYS_MYSQL_RUNTIME"
```

La instancia debe ser MySQL 8.0 con datadir temporal exacto, marcador privado y red deshabilitada. El ejecutor toma el lock común `verification.lock`. La infraestructura F2 crea cinco esquemas/usuarios limitados y los retira al terminar; **esta batería solo prepara y verifica el esquema central**, no las migraciones nativas de los otros cuatro proyectos.

| Caso | Invariante |
| --- | --- |
| `last_slot` | POS y BYS compiten por un cupo: un éxito y un rechazo, un solo consumo reservado. |
| `same_operation` | La misma clave/contenido conserva una sola reserva y recibo. |
| `conflicting_operation` | Una clave no admite dos contenidos ni agrega alias del rechazado. |
| `fiscal_alias` | POS/e-CF con el mismo emisor y NCF comparten documento y cupo. |
| `commit_cancel` | Confirmación y cancelación eligen un resultado terminal coherente. |
| `reserve_cancel` | La cancelación impide que una reserva retrasada resucite la operación. |
| `reference_commit` | Cumplimiento espera la confirmación del origen; reintentar no duplica consumo. |
| `shared_commit_cancel` | Cancelar un origen no devuelve el cupo de un documento confirmado por otro. |

Los procesos usan el controlador y servicio nativos con sobres firmados. Una conexión se pausa inmediatamente después de adquirir el mutex de empresa; la otra anuncia su llegada inmediatamente antes del mismo `FOR UPDATE`. Antes de liberar la primera, el ejecutor exige evidencia de `performance_schema.data_lock_waits` para ambos IDs de conexión y el esquema de esta ejecución. Conserva SQL mode, aislamiento, PID, conexiones, estados, conteos, salidas y huellas de fuentes. No usa un servidor HTTP de un solo trabajador para simular concurrencia. Las pruebas HTTP/HTTPS y de los adaptadores locales se conservan por separado.

Para completar solo un caso u orden pendiente:

```bash
python3 tests/Fixtures/BusinessCommercial/mysql_check.py --runtime="$BYS_MYSQL_RUNTIME" --case=commit_cancel --order=reverse
```

`selected_cases_verified=true` acredita únicamente la selección. `mysql_verified=true` requiere los 16 escenarios, fuentes estables y retirada comprobada de los cinco esquemas/usuarios. La salida 0 de una selección no acredita la matriz completa. No repetir aprobaciones previas salvo cambios que las afecten.

La autoprueba explícita comprueba preparación e invariantes en orden secuencial:

```bash
python3 tests/Fixtures/BusinessCommercial/mysql_check.py --sqlite-self-test
python3 -m unittest discover -s tests/Fixtures/BusinessCommercial -p test_mysql_runner.py -v
```

Su resultado es `self_test_passed`, con `mysql_verified=false` y `concurrent=false`; **no acredita migraciones ni carreras MySQL**. Los runtimes son privados, con datos y claves ficticios. La conexión MySQL exige manifiesto y opt-in; un cambio de modo no puede reutilizar el archivo SQLite.

Cada ejecución conserva un `result.json` en su directorio `/tmp/lauda-business-…` y un informe único `f6-commercial-concurrency-….json` en `.lauda-reports/business-20261106/`. El informe incluye la ruta del runtime para recuperar una limpieza interrumpida. Con `BUSINESS_QA_RUNTIME` definido con esa ruta exacta:

```bash
python3 tests/Fixtures/BusinessCommercial/mysql_check.py --cleanup="$BUSINESS_QA_RUNTIME"
```

La limpieza exige marcador F5 privado, valida el servidor antes de abrir su lock, confirma pertenencia de los esquemas y rechaza conexiones activas. No limpia ejecuciones F2/BYS ni datos ajenos. Un fallo de preparación, ejecución o limpieza conserva estado no verificado.

Esta batería no cierra capacidades locales, concurrencia financiera F3/F4, adaptadores locales sobre MySQL, aceptación visual ni activación/despliegue. Cantina y autoservicio siguen excluidos. El estado y la evidencia revisada se registran en `avance.md`.
