# Validación MySQL de Business F2

Desde una terminal con acceso al socket de la instancia descartable ya existente:

```bash
cd /home/laudaerp/laravel/laudaapi
python3 tests/Fixtures/BusinessIdentity/mysql_check.py --runtime=/tmp/bys-mysql-check-b7d4053ce7234bb2
```

No arranca ni reinicializa MySQL. Reutiliza la comprobación de instancia de BYS: MySQL 8.0, datadir temporal exacto, red deshabilitada y marcador privado. Toma el lock del verificador existente. Crea cinco esquemas `business_test_*` con usuarios limitados a su esquema y datos ficticios. La aplicación real se arranca con rutas, proveedores y modelos fuente, después de redirigir configuración, cachés, almacenamiento y claves a `/tmp`. No carga `.env` ni las cachés operativas. El modo MySQL no permite sustitución por SQLite.

La batería comprende:

- En las cinco aplicaciones: instalación de `000002`, actualización `000003` con dos solicitudes históricas, generaciones nulas, columnas e índice único, repetición con registro de migraciones, negativa a borrar historial y rollback/reaplicación con tablas vacías.
- Los seis casos originales de `BusinessIdentityLifecycleTest`, usando el mismo bootstrap aislado y rollback por caso; JUnit y salida conservados.
- Doce carreras, cada una con dos procesos, barrera y dos `CONNECTION_ID()` diferentes: inicio duplicado, confirmación duplicada, reinicio contra confirmación, identidades que reclaman la misma empresa local y, en cada receptor, doble consumo de nonce y revocación contra activación.
- Flujo bilateral por los servicios reales de los cuatro receptores, recuperación del recibo de activación, acceso comercial/ready y rechazo de repeticiones después de revocar. Las carreras usan el adaptador real de conflictos de los controladores. La evidencia HTTP/CSRF/navegador se conserva en el reporte previo.
- Huellas de fuentes antes y después; retirada comprobada de los cinco esquemas y sus usuarios al finalizar, incluso si una prueba falla. No limpia esquemas de ejecuciones previas de BYS.

El resultado queda en `.lauda-reports/business-20261106/mysql-result.{json,txt}` y en el directorio privado de artefactos indicado en pantalla. **Solo** `status: passed`, `mysql_verified: true` y salida `0` acreditan la batería completa. El resumen debe indicar 5 aplicaciones, 6 casos centrales, 12 carreras, 4 flujos y 5 esquemas/usuarios retirados. Un fallo de socket anterior a la preparación deja cero pruebas MySQL ejecutadas.

Si se interrumpe violentamente el proceso, el informe o el directorio `/tmp/lauda-business-<16 hex>` conserva un manifiesto privado. La limpieza explícita valida la pertenencia de cada esquema y rechaza conexiones activas:

```bash
python3 tests/Fixtures/BusinessIdentity/mysql_check.py --cleanup=/tmp/lauda-business-<16 hex de esa ejecución>
```

`--sqlite-self-test` sirve únicamente para verificar el propio ejecutor, migraciones y operaciones secuenciales. No ejecuta la suite central exclusiva de MySQL, no acredita concurrencia y escribe evidencia separada.

## Después del resultado aprobado

Esta prueba valida F2 en MySQL descartable con los esquemas nativos necesarios para identidad; BYS usa sus migraciones completas. No acredita el esquema operativo completo de los otros proyectos. Para cerrar el paso de despliegue en **DEV autorizado**, aplicar `000002` y `000003` por proyecto con su procedimiento normal de despliegue, comprobar que figuran ejecutadas y revisar las migraciones ya aplicadas antes de repetir comandos. Mantener el flag apagado hasta completar la aceptación DEV: launcher anterior con flag apagado, vinculación bilateral con sesiones reales y destino final de cada dashboard con Business configurado. Conservar evidencia de `migrate:status` y de esos recorridos en el reporte F2.

No hay rollback destructivo con historial. Un fallo después de comenzar DDL debe inspeccionarse en su ambiente: estas pruebas no autorizan borrar registros ni sustituyen la copia de seguridad y el procedimiento de despliegue existente.
