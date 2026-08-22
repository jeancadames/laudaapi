export type Score = 1 | 2 | 3 | 4 | 5

export interface DiagnosisChoice {
  score: Score
  label: string
}

export interface DiagnosisQuestion {
  id: string
  text: string
  help?: string
  evidence?: string
  choices: DiagnosisChoice[]
}

export interface DiagnosisDimension {
  id: string
  title: string
  shortTitle: string
  weight: number
  objective: string
  questions: DiagnosisQuestion[]
}

const c = (...labels: [string, string, string, string, string]): DiagnosisChoice[] =>
  labels.map((label, index) => ({ score: (index + 1) as Score, label }))

export const diagnosisMeta = {
  code: 'LAUDA360-DIAG-V1',
  version: '1.0.0',
  maturityQuestionCount: 40,
  maturityScale: '1-5',
  scoring: 'Cada respuesta 1-5 se normaliza a 0-100. El resultado global usa el peso de cada dimensión.',
}

export const maturityDimensions: DiagnosisDimension[] = [
  {
    id: 'strategy',
    title: 'Estrategia y Liderazgo',
    shortTitle: 'Estrategia',
    weight: 15,
    objective: 'Determinar si la transformación digital tiene dirección, patrocinio, prioridades y medición.',
    questions: [
      {
        id: 'STR-01',
        text: '¿Qué tan definidos están los objetivos de transformación digital de la empresa?',
        evidence: 'Plan estratégico, objetivos anuales, iniciativas digitales, actas o presentaciones de dirección.',
        choices: c(
          'No existen objetivos digitales definidos.',
          'Existen ideas o iniciativas aisladas, sin objetivos formales.',
          'Hay objetivos digitales documentados para algunas áreas.',
          'Existen objetivos empresariales priorizados, responsables e indicadores.',
          'Los objetivos digitales forman parte de la estrategia y se revisan periódicamente según resultados.'
        ),
      },
      {
        id: 'STR-02',
        text: '¿Existe liderazgo ejecutivo responsable de impulsar la transformación?',
        evidence: 'Patrocinador ejecutivo, comité, responsables y frecuencia de seguimiento.',
        choices: c(
          'No existe un responsable.',
          'Alguien impulsa iniciativas de manera informal y reactiva.',
          'Existe un responsable, pero con autoridad o dedicación limitada.',
          'Existe patrocinio ejecutivo y responsables claros por iniciativa.',
          'Existe gobierno formal de transformación con patrocinio, responsables y seguimiento sistemático.'
        ),
      },
      {
        id: 'STR-03',
        text: '¿Cómo se planifica la inversión en tecnología y transformación?',
        evidence: 'Presupuesto, cartera de proyectos, criterios de inversión y aprobaciones.',
        choices: c(
          'Solo se invierte cuando surge un problema urgente.',
          'Se realizan compras ocasionales sin un plan consolidado.',
          'Existe presupuesto para algunas iniciativas digitales.',
          'Existe presupuesto anual priorizado según objetivos y roadmap.',
          'La inversión se gestiona como portafolio y se evalúa por valor, riesgo, adopción y retorno.'
        ),
      },
      {
        id: 'STR-04',
        text: '¿Cómo se priorizan las iniciativas de transformación?',
        evidence: 'Roadmap, criterios de priorización, matriz impacto/esfuerzo o portafolio.',
        choices: c(
          'No existe priorización; se actúa según urgencias.',
          'La prioridad depende principalmente de solicitudes individuales o proveedores.',
          'Existe una lista de proyectos con prioridades básicas.',
          'Se utiliza un roadmap con criterios de impacto, esfuerzo, riesgo y dependencia.',
          'El portafolio se revisa continuamente según resultados, capacidad y cambios del negocio.'
        ),
      },
      {
        id: 'STR-05',
        text: '¿Cómo se mide el resultado de las iniciativas digitales?',
        evidence: 'KPIs, beneficios esperados, adopción, ROI, tiempos, calidad o experiencia.',
        choices: c(
          'No se miden resultados.',
          'Se mide principalmente si la herramienta fue instalada o entregada.',
          'Algunas iniciativas tienen indicadores de uso o actividad.',
          'Se miden resultados empresariales, adopción y cumplimiento de objetivos.',
          'Los resultados se usan sistemáticamente para optimizar, priorizar o detener iniciativas.'
        ),
      },
    ],
  },
  {
    id: 'people',
    title: 'Personas y Cultura Digital',
    shortTitle: 'Personas',
    weight: 10,
    objective: 'Evaluar capacidades, adopción, colaboración y preparación de las personas para cambiar la forma de trabajar.',
    questions: [
      {
        id: 'PEO-01',
        text: '¿Qué nivel de habilidades digitales tiene el personal para ejecutar sus funciones?',
        evidence: 'Matriz de competencias, evaluaciones, incidencias de uso y dependencia de soporte.',
        choices: c(
          'La mayoría depende de procesos manuales y tiene habilidades digitales limitadas.',
          'Existen habilidades básicas, con alta dependencia de personas específicas.',
          'La mayoría utiliza herramientas digitales necesarias para su función.',
          'Las competencias digitales se definen por rol y se desarrollan de forma planificada.',
          'La empresa desarrolla continuamente nuevas capacidades digitales y comparte conocimiento entre equipos.'
        ),
      },
      {
        id: 'PEO-02',
        text: '¿Cómo se gestiona la capacitación relacionada con nuevas herramientas y procesos?',
        evidence: 'Planes, materiales, registros de capacitación, evaluaciones y onboarding.',
        choices: c(
          'No existe capacitación estructurada.',
          'La capacitación ocurre informalmente cuando aparecen problemas.',
          'Se capacita al implementar algunas herramientas.',
          'Existe un plan de capacitación y validación de competencias.',
          'La capacitación es continua, medible y vinculada con adopción y desempeño.'
        ),
      },
      {
        id: 'PEO-03',
        text: '¿Qué tan clara es la responsabilidad de cada persona en los procesos digitales?',
        evidence: 'Roles, responsables de proceso, RACI, manuales o descripciones de puesto.',
        choices: c(
          'Las responsabilidades son ambiguas o dependen de conocimiento informal.',
          'Algunas responsabilidades están claras, pero existen vacíos y duplicidades.',
          'Los responsables principales están definidos.',
          'Roles y responsabilidades digitales están documentados y comunicados.',
          'La responsabilidad está integrada a objetivos, métricas y mejora continua.'
        ),
      },
      {
        id: 'PEO-04',
        text: '¿Cómo reacciona la organización ante cambios de procesos o herramientas?',
        evidence: 'Planes de cambio, comunicaciones, feedback, resistencias e incidencias posteriores al go-live.',
        choices: c(
          'Existe fuerte resistencia y los cambios suelen abandonarse.',
          'Los cambios dependen de presión de la dirección y generan fricción frecuente.',
          'La organización adopta cambios con apoyo y seguimiento básico.',
          'Existe gestión del cambio con comunicación, responsables y soporte.',
          'La mejora continua forma parte de la cultura y los equipos participan activamente en el rediseño.'
        ),
      },
      {
        id: 'PEO-05',
        text: '¿Qué tan digital es la colaboración interna entre áreas?',
        evidence: 'Herramientas colaborativas, trazabilidad de solicitudes, documentos compartidos y handoffs.',
        choices: c(
          'Predominan papel, llamadas, mensajes personales y archivos locales.',
          'Se usan herramientas digitales, pero sin estándares ni trazabilidad.',
          'Existen herramientas comunes para comunicación y documentos.',
          'Los equipos colaboran mediante flujos, tareas y repositorios compartidos.',
          'La colaboración está integrada a procesos medibles con automatización y trazabilidad de extremo a extremo.'
        ),
      },
    ],
  },
  {
    id: 'presence',
    title: 'Presencia y Experiencia Digital',
    shortTitle: 'Presencia',
    weight: 10,
    objective: 'Medir cómo la empresa se presenta, interactúa y genera oportunidades a través de canales digitales.',
    questions: [
      {
        id: 'PRE-01',
        text: '¿Qué tan consistente es la identidad de la empresa en sus canales digitales?',
        evidence: 'Web, redes, perfiles, logos, descripciones, lineamientos y datos de contacto.',
        choices: c(
          'La presencia es inexistente o inconsistente.',
          'Existen perfiles, pero con información, imagen o mensajes desactualizados.',
          'La identidad es razonablemente consistente en los principales canales.',
          'Existe identidad digital definida y aplicada de forma coordinada.',
          'La identidad digital se gobierna, mide y adapta por canal manteniendo coherencia de marca.'
        ),
      },
      {
        id: 'PRE-02',
        text: '¿Cómo se administran las redes y canales digitales de la empresa?',
        evidence: 'Responsables, calendario, accesos, herramientas, procesos y protocolos.',
        choices: c(
          'No existe gestión formal.',
          'La gestión depende de una persona y se realiza de forma reactiva.',
          'Existen responsables y una operación básica por canal.',
          'La gestión está planificada, coordinada y documentada.',
          'Los canales se gestionan de forma integrada con objetivos, automatización y análisis continuo.'
        ),
      },
      {
        id: 'PRE-03',
        text: '¿Cómo se gestionan las conversaciones y solicitudes que llegan por canales digitales?',
        evidence: 'Inbox, WhatsApp, tiempos de respuesta, asignación y trazabilidad.',
        choices: c(
          'Las conversaciones quedan en cuentas o teléfonos individuales sin control.',
          'Se responden manualmente, con poca trazabilidad y seguimiento.',
          'Existe un proceso básico de atención y asignación.',
          'Las conversaciones se centralizan y se conectan con contactos, responsables o casos.',
          'La atención es omnicanal, medible y conectada con CRM, automatizaciones y contexto del cliente.'
        ),
      },
      {
        id: 'PRE-04',
        text: '¿Puede la empresa identificar qué canales digitales generan oportunidades o clientes?',
        evidence: 'UTM, fuente del lead, atribución, campañas, conversiones y reportes.',
        choices: c(
          'No se conoce el origen de las oportunidades.',
          'Se estima manualmente o por percepción.',
          'Se registra el canal de origen en parte de los casos.',
          'Existe atribución consistente desde canal hasta oportunidad o venta.',
          'Se optimiza inversión y experiencia con atribución multicanal y valor generado.'
        ),
      },
      {
        id: 'PRE-05',
        text: '¿Qué nivel de autoservicio digital ofrece la empresa a clientes o prospectos?',
        evidence: 'Formularios, ecommerce, reservas, consultas, estado de pedidos, pagos o portales.',
        choices: c(
          'La mayoría de gestiones requiere contacto manual.',
          'Existen formularios o canales básicos sin integración.',
          'Algunas gestiones pueden completarse digitalmente.',
          'Los principales recorridos ofrecen autoservicio conectado a procesos internos.',
          'El autoservicio es amplio, personalizado, medido y optimizado continuamente.'
        ),
      },
    ],
  },
  {
    id: 'commercial',
    title: 'Gestión Comercial y Clientes',
    shortTitle: 'Comercial',
    weight: 15,
    objective: 'Evaluar cómo se capturan, gestionan, convierten y conocen las relaciones comerciales.',
    questions: [
      {
        id: 'COM-01',
        text: '¿Dónde se mantiene la información de contactos, prospectos y clientes?',
        evidence: 'CRM, hojas de cálculo, agendas, teléfonos y bases duplicadas.',
        choices: c(
          'Está dispersa en personas, teléfonos, papel o archivos individuales.',
          'Existen bases compartidas, pero con duplicados y control limitado.',
          'Existe una base central para la mayoría de contactos y clientes.',
          'Existe una fuente central con reglas de calidad, propiedad y actualización.',
          'La identidad del cliente es transversal, enriquecida y compartida por los procesos autorizados.'
        ),
      },
      {
        id: 'COM-02',
        text: '¿Cómo se registran y asignan los leads u oportunidades nuevas?',
        evidence: 'Formularios, redes, WhatsApp, CRM, reglas de asignación y SLA.',
        choices: c(
          'No existe registro estructurado.',
          'Se registran manualmente y pueden perderse o duplicarse.',
          'Existe un registro central y asignación básica.',
          'Las fuentes están conectadas y existen reglas claras de asignación y seguimiento.',
          'La captura y asignación son automatizadas, medibles y optimizadas según capacidad y conversión.'
        ),
      },
      {
        id: 'COM-03',
        text: '¿Qué tan controlado está el seguimiento comercial?',
        evidence: 'Actividades, tareas, recordatorios, pipeline, razones de pérdida y aging.',
        choices: c(
          'Depende de la memoria o iniciativa de cada vendedor.',
          'Se usan agendas o listas sin una visión consolidada.',
          'Existe pipeline y registro básico de actividades.',
          'El seguimiento es estandarizado, medido y supervisado.',
          'El sistema prioriza acciones, detecta riesgos y habilita automatizaciones basadas en comportamiento.'
        ),
      },
      {
        id: 'COM-04',
        text: '¿Puede la gerencia medir con precisión el embudo comercial y la conversión?',
        evidence: 'Leads, oportunidades, etapas, forecast, conversión, ciclo y resultados por vendedor/canal.',
        choices: c(
          'No existe una medición confiable.',
          'Se preparan reportes manuales y tardíos.',
          'Se miden algunos indicadores básicos.',
          'Existe visibilidad continua de pipeline, conversión y forecast.',
          'La información se utiliza para predecir resultados, redistribuir esfuerzo y optimizar la estrategia comercial.'
        ),
      },
      {
        id: 'COM-05',
        text: '¿Existe una vista integral del historial y valor de cada cliente?',
        evidence: 'Interacciones, oportunidades, compras, servicio, crédito, cobranzas y rentabilidad.',
        choices: c(
          'La información del cliente está fragmentada y requiere búsquedas manuales.',
          'Puede reconstruirse parcialmente consultando varias fuentes.',
          'Existe historial comercial centralizado, aunque incompleto.',
          'Existe una vista 360 que integra los principales puntos de relación y transacción.',
          'La vista 360 incorpora valor, comportamiento, riesgo y recomendaciones para actuar.'
        ),
      },
    ],
  },
  {
    id: 'operations',
    title: 'Procesos y Operación',
    shortTitle: 'Operación',
    weight: 20,
    objective: 'Medir la digitalización, estandarización, integración y control de los procesos operativos.',
    questions: [
      {
        id: 'OPE-01',
        text: '¿Qué tan documentados y estandarizados están los procesos críticos?',
        evidence: 'Mapas de proceso, SOP, responsables, controles y versiones.',
        choices: c(
          'Los procesos dependen del conocimiento de las personas.',
          'Existen prácticas comunes, pero poca documentación.',
          'Los principales procesos están documentados de forma básica.',
          'Procesos críticos tienen estándares, responsables, controles y versiones.',
          'Los procesos se revisan continuamente con métricas, evidencia y mejora estructurada.'
        ),
      },
      {
        id: 'OPE-02',
        text: '¿Qué proporción de la operación diaria depende todavía de tareas manuales, papel o Excel?',
        evidence: 'Pedidos, inventario, servicios, aprobaciones, entregas, facturación y conciliaciones.',
        choices: c(
          'La mayor parte de la operación es manual.',
          'Existen herramientas digitales, pero gran parte del flujo requiere transcripción manual.',
          'Los procesos principales están parcialmente digitalizados.',
          'La mayoría de procesos críticos se ejecuta digitalmente con poca duplicación manual.',
          'Los procesos son digitales de extremo a extremo y las tareas manuales se reservan para excepciones justificadas.'
        ),
      },
      {
        id: 'OPE-03',
        text: '¿Qué tan conectadas están las áreas que participan en un mismo proceso de punta a punta?',
        evidence: 'Venta a cobro, compra a pago, pedido a entrega, servicio a facturación.',
        choices: c(
          'Cada área trabaja por separado y transfiere información manualmente.',
          'Existen algunos intercambios digitales, pero con reprocesos frecuentes.',
          'Los principales handoffs están definidos y parcialmente integrados.',
          'Los procesos críticos fluyen entre áreas con información compartida y trazabilidad.',
          'Los procesos de punta a punta están orquestados, medidos y optimizados como una sola cadena de valor.'
        ),
      },
      {
        id: 'OPE-04',
        text: '¿Cómo se gestionan aprobaciones, excepciones y controles operativos?',
        evidence: 'Autorizaciones, límites, bitácoras, alertas y segregación de funciones.',
        choices: c(
          'Se gestionan verbalmente o por mensajes sin trazabilidad.',
          'Existen controles informales dependientes de personas específicas.',
          'Las aprobaciones principales están definidas y registradas.',
          'Existen flujos digitales con reglas, límites, responsables y auditoría.',
          'Los controles son dinámicos, basados en riesgo y generan alertas o escalaciones automáticas.'
        ),
      },
      {
        id: 'OPE-05',
        text: '¿Qué tan medido y optimizado está el desempeño operativo?',
        evidence: 'Tiempos de ciclo, productividad, errores, retrabajo, SLA, capacidad y costos.',
        choices: c(
          'No existen métricas operativas confiables.',
          'Se miden resultados finales de forma manual y ocasional.',
          'Existen KPIs para algunos procesos.',
          'Los procesos críticos tienen KPIs, metas y seguimiento frecuente.',
          'La empresa usa métricas en tiempo casi real para detectar cuellos de botella, automatizar y mejorar continuamente.'
        ),
      },
    ],
  },
  {
    id: 'technology',
    title: 'Tecnología e Integración',
    shortTitle: 'Tecnología',
    weight: 10,
    objective: 'Evaluar si el entorno tecnológico es confiable, gobernable e integrado para soportar la transformación.',
    questions: [
      {
        id: 'TEC-01',
        text: '¿La empresa conoce y gobierna las aplicaciones y herramientas que utiliza?',
        evidence: 'Inventario de sistemas, propietarios, contratos, criticidad y dependencias.',
        choices: c(
          'No existe inventario ni control central.',
          'Se conocen algunas herramientas, pero cada área decide de forma independiente.',
          'Existe inventario básico y responsables para sistemas principales.',
          'Aplicaciones, propietarios, costos, criticidad y dependencias están documentados.',
          'El portafolio tecnológico se gobierna activamente según arquitectura, riesgo, valor y ciclo de vida.'
        ),
      },
      {
        id: 'TEC-02',
        text: '¿Qué tan confiable y disponible es la infraestructura tecnológica que soporta la operación?',
        evidence: 'Disponibilidad, nube, monitoreo, incidencias, redundancia y soporte.',
        choices: c(
          'Las fallas son frecuentes y afectan significativamente la operación.',
          'La infraestructura funciona, pero existe alta dependencia de equipos o personas específicas.',
          'Los sistemas principales tienen niveles aceptables de disponibilidad y soporte.',
          'Existe arquitectura confiable, monitoreo, soporte y capacidad planificada.',
          'La infraestructura es resiliente, observable, escalable y gestionada mediante objetivos de servicio.'
        ),
      },
      {
        id: 'TEC-03',
        text: '¿Cómo intercambian información los sistemas de la empresa?',
        evidence: 'APIs, archivos, integraciones, importaciones, exportaciones y doble digitación.',
        choices: c(
          'La información se transcribe manualmente entre sistemas.',
          'Se intercambian archivos o procesos manuales recurrentes.',
          'Existen algunas integraciones entre sistemas críticos.',
          'Las aplicaciones principales usan APIs o integraciones gestionadas y trazables.',
          'Existe una arquitectura de integración gobernada por APIs/eventos, desacoplada y observable.'
        ),
      },
      {
        id: 'TEC-04',
        text: '¿Qué tan frecuente es la duplicación de información entre aplicaciones?',
        evidence: 'Clientes, productos, proveedores, usuarios y configuración repetida.',
        choices: c(
          'La misma información se crea y mantiene manualmente en múltiples lugares.',
          'Hay duplicación frecuente y conciliaciones periódicas.',
          'Existen fuentes principales para algunos datos, pero persisten duplicidades.',
          'Los datos maestros tienen propietarios claros y se comparten entre sistemas.',
          'La arquitectura usa identidades globales y contratos de datos que minimizan duplicación y conflictos.'
        ),
      },
      {
        id: 'TEC-05',
        text: '¿Qué tan preparada está la arquitectura tecnológica para incorporar nuevas capacidades?',
        evidence: 'APIs, modularidad, documentación, ambientes, pruebas y despliegues.',
        choices: c(
          'Cada cambio requiere intervención manual de alto riesgo.',
          'Se pueden agregar herramientas, pero con integraciones frágiles y retrabajo.',
          'Existe una base razonablemente modular para incorporar nuevas soluciones.',
          'Hay APIs, ambientes, documentación y prácticas de despliegue que facilitan evolución.',
          'La arquitectura es modular, automatizada, observable y permite evolucionar sin interrumpir la operación.'
        ),
      },
    ],
  },
  {
    id: 'data',
    title: 'Datos e Inteligencia',
    shortTitle: 'Datos',
    weight: 15,
    objective: 'Determinar la calidad, disponibilidad y uso de datos para controlar, comprender y anticipar el negocio.',
    questions: [
      {
        id: 'DAT-01',
        text: '¿Qué tan confiables y completos son los datos críticos de la empresa?',
        evidence: 'Duplicados, campos faltantes, errores, conciliaciones y reglas de calidad.',
        choices: c(
          'Los datos presentan errores frecuentes y no existe control de calidad.',
          'La calidad depende de revisiones manuales y conocimiento de personas específicas.',
          'Existen reglas básicas y procesos periódicos de limpieza.',
          'La calidad se mide, tiene responsables y controles en los principales datos.',
          'La calidad se monitorea continuamente con reglas, alertas, ownership y mejora sistemática.'
        ),
      },
      {
        id: 'DAT-02',
        text: '¿Existe una fuente confiable para los datos maestros de clientes, productos, proveedores u otras entidades?',
        evidence: 'Master data, catálogos, identificadores globales y ownership.',
        choices: c(
          'Existen múltiples versiones sin una fuente oficial.',
          'Se realizan conciliaciones manuales para determinar cuál dato usar.',
          'Algunas entidades tienen una fuente principal definida.',
          'Los principales datos maestros tienen fuente, propietario e identificador común.',
          'Existe gobierno de datos maestros con distribución controlada, calidad y trazabilidad transversal.'
        ),
      },
      {
        id: 'DAT-03',
        text: '¿Qué tan rápido obtiene la gerencia los indicadores necesarios para dirigir el negocio?',
        evidence: 'Frecuencia de reportes, cierres, preparación manual y latencia.',
        choices: c(
          'La información tarda días o semanas y requiere preparación manual extensa.',
          'Se generan reportes periódicos, pero con esfuerzo considerable.',
          'Los KPIs principales están disponibles con frecuencia establecida.',
          'La dirección dispone de dashboards actualizados y consistentes.',
          'Los indicadores son oportunos, contextualizados y generan alertas cuando requieren acción.'
        ),
      },
      {
        id: 'DAT-04',
        text: '¿Qué capacidad tiene la empresa para analizar causas y relaciones entre áreas?',
        evidence: 'BI, drill-down, segmentación, cohortes, análisis cruzado y modelos.',
        choices: c(
          'El análisis se limita a totales o reportes estáticos.',
          'Se realizan análisis manuales puntuales en hojas de cálculo.',
          'Existe BI descriptivo para algunas áreas.',
          'La empresa cruza datos de diferentes áreas para explicar resultados y causas.',
          'El análisis es transversal, autoservicio gobernado y permite descubrir patrones y oportunidades de forma continua.'
        ),
      },
      {
        id: 'DAT-05',
        text: '¿Cómo utiliza la empresa los datos para anticipar y tomar acciones?',
        evidence: 'Forecast, alertas, propensión, riesgo, recomendaciones y automatizaciones.',
        choices: c(
          'Las decisiones son principalmente reactivas.',
          'Se usan experiencia e información histórica sin modelos consistentes.',
          'Existen proyecciones básicas para algunas decisiones.',
          'Se utilizan pronósticos, alertas y segmentaciones para anticipar acciones.',
          'Los datos alimentan recomendaciones, predicciones y automatizaciones gobernadas que se miden por resultado.'
        ),
      },
    ],
  },
  {
    id: 'governance',
    title: 'Gobierno, Seguridad y Control',
    shortTitle: 'Gobierno',
    weight: 5,
    objective: 'Detectar si existen controles mínimos para proteger la operación, los datos y la continuidad del negocio.',
    questions: [
      {
        id: 'GOV-01',
        text: '¿Cómo se gestionan usuarios, accesos y permisos a los sistemas?',
        evidence: 'Altas/bajas, roles, MFA, cuentas compartidas y revisiones de acceso.',
        choices: c(
          'Se usan cuentas compartidas o accesos sin control formal.',
          'Cada sistema maneja accesos de forma independiente y reactiva.',
          'Existen usuarios individuales y controles básicos de roles.',
          'Altas, bajas, permisos y revisiones siguen políticas definidas; MFA se usa donde corresponde.',
          'La identidad y acceso están centralizados, auditados y gobernados por riesgo y mínimo privilegio.'
        ),
      },
      {
        id: 'GOV-02',
        text: '¿Qué nivel de respaldo y recuperación existe para información y sistemas críticos?',
        evidence: 'Backups, pruebas de restauración, RPO/RTO y continuidad.',
        choices: c(
          'No existen respaldos confiables o verificables.',
          'Hay respaldos, pero no se prueban regularmente.',
          'Los sistemas principales tienen backups programados.',
          'Existen políticas, monitoreo y pruebas periódicas de restauración.',
          'Existe continuidad formal con objetivos RPO/RTO, redundancia y ejercicios de recuperación.'
        ),
      },
      {
        id: 'GOV-03',
        text: '¿Puede la empresa auditar quién realizó cambios o transacciones críticas?',
        evidence: 'Logs, bitácoras, historial, aprobaciones y evidencia.',
        choices: c(
          'No existe trazabilidad confiable.',
          'La trazabilidad depende de mensajes, documentos o memoria del equipo.',
          'Algunos sistemas registran usuarios y cambios.',
          'Los procesos críticos tienen auditoría, historial y evidencia consultable.',
          'La trazabilidad es transversal, protegida, monitoreada y utilizada para control y análisis de riesgo.'
        ),
      },
      {
        id: 'GOV-04',
        text: '¿Qué tan formalizados están los controles de seguridad, privacidad y cumplimiento aplicables?',
        evidence: 'Políticas, contratos, privacidad, retención, cumplimiento fiscal/regulatorio y responsables.',
        choices: c(
          'No existen políticas ni responsables claros.',
          'Se atienden requisitos cuando surge una necesidad o auditoría.',
          'Existen políticas básicas y responsables para temas principales.',
          'Los controles aplicables están documentados, asignados y revisados periódicamente.',
          'Seguridad, privacidad y cumplimiento se gestionan de forma continua mediante riesgo, evidencia y mejora.'
        ),
      },
      {
        id: 'GOV-05',
        text: '¿Cómo se gestionan incidentes tecnológicos u operativos relevantes?',
        evidence: 'Registro de incidentes, responsables, escalación, tiempos y análisis de causa raíz.',
        choices: c(
          'Los incidentes se resuelven de forma improvisada y no se registran.',
          'Se registran algunos incidentes, sin proceso consistente.',
          'Existe un proceso básico de reporte y resolución.',
          'Los incidentes tienen responsables, severidad, escalación y análisis de causa.',
          'Los incidentes alimentan métricas, prevención, automatización y mejora continua de controles.'
        ),
      },
    ],
  },
]

export const internalCapacityQuestions: DiagnosisQuestion[] = [
  {
    id: 'CAP-01',
    text: '¿Qué disponibilidad real tiene un patrocinador ejecutivo para tomar decisiones y remover bloqueos?',
    choices: c('Prácticamente ninguna.', 'Muy limitada y reactiva.', 'Disponible en hitos importantes.', 'Disponible de forma periódica y comprometida.', 'Altamente involucrado con cadencia de seguimiento y autoridad clara.'),
  },
  {
    id: 'CAP-02',
    text: '¿Existe un responsable interno con tiempo asignado para coordinar la transformación?',
    choices: c('No existe.', 'Existe informalmente sin tiempo asignado.', 'Existe responsable con disponibilidad parcial.', 'Existe responsable con tiempo y mandato definidos.', 'Existe equipo interno con liderazgo, responsables por área y capacidad de coordinación.'),
  },
  {
    id: 'CAP-03',
    text: '¿Qué capacidad tiene la empresa para gestionar proyectos, tareas, dependencias y fechas?',
    choices: c('No existe disciplina de gestión.', 'Se coordina por mensajes y reuniones informales.', 'Se usan listas o herramientas básicas con seguimiento irregular.', 'Existe metodología, responsables y seguimiento periódico.', 'Existe capacidad madura de PMO/proyectos con riesgos, dependencias y gobernanza.'),
  },
  {
    id: 'CAP-04',
    text: '¿Qué capacidad interna existe para preparar, limpiar y validar datos para una implementación?',
    choices: c('No existe capacidad disponible.', 'Depende de una persona y requiere mucha asistencia.', 'Puede preparar datos con plantillas y orientación.', 'Existe equipo capaz de depurar, validar y entregar datos según especificaciones.', 'Existe gobierno/ownership de datos y capacidad para ejecutar migraciones con mínima asistencia.'),
  },
  {
    id: 'CAP-05',
    text: '¿Qué capacidad técnica interna existe para configurar herramientas e integraciones?',
    choices: c('No existe capacidad técnica.', 'Solo soporte básico de equipos/aplicaciones.', 'Existe personal que puede configurar herramientas con guía.', 'Existe equipo capaz de administrar sistemas, APIs o integraciones estándar.', 'Existe equipo técnico con arquitectura, desarrollo/integración, pruebas y operación.'),
  },
  {
    id: 'CAP-06',
    text: '¿Cuánto tiempo pueden dedicar los responsables internos al programa de transformación?',
    choices: c('No pueden dedicar tiempo de forma consistente.', 'Solo atienden cuando surge una urgencia.', 'Pueden participar algunas horas en hitos clave.', 'Tienen bloques de tiempo semanales y responsables definidos.', 'Existe capacidad planificada y protegida para ejecutar el roadmap de forma sostenida.'),
  },
]

export const urgencyQuestions: DiagnosisQuestion[] = [
  {
    id: 'URG-01',
    text: '¿Qué impacto tienen hoy los problemas de procesos o tecnología sobre la operación?',
    choices: c('Bajo; son molestias menores.', 'Moderado; generan retrabajo ocasional.', 'Relevante; afectan productividad o servicio regularmente.', 'Alto; generan pérdidas, retrasos o fallas frecuentes.', 'Crítico; comprometen continuidad, clientes o resultados importantes.'),
  },
  {
    id: 'URG-02',
    text: '¿Qué presión de crecimiento está enfrentando la empresa?',
    choices: c('No existe presión significativa.', 'El crecimiento es manejable con la operación actual.', 'El crecimiento comienza a tensionar algunos procesos.', 'La capacidad actual limita ventas, servicio u operación.', 'La empresa no puede sostener el crecimiento sin transformar procesos de forma inmediata.'),
  },
  {
    id: 'URG-03',
    text: '¿Existen fechas regulatorias, contractuales o de cumplimiento que exijan cambios?',
    choices: c('No existen fechas relevantes.', 'Existen requisitos futuros sin presión inmediata.', 'Hay compromisos dentro de los próximos 12 meses.', 'Hay compromisos importantes dentro de los próximos 6 meses.', 'Existe un vencimiento, riesgo o incumplimiento crítico en el corto plazo.'),
  },
  {
    id: 'URG-04',
    text: '¿Qué presión ejercen clientes y competidores sobre la experiencia digital de la empresa?',
    choices: c('Muy baja.', 'Existen expectativas crecientes, sin impacto claro.', 'La empresa comienza a perder eficiencia o percepción frente a alternativas digitales.', 'Clientes o competidores ya presionan ventas, servicio o retención.', 'La brecha digital amenaza directamente competitividad, clientes o mercado.'),
  },
  {
    id: 'URG-05',
    text: '¿Qué tan dependiente es la empresa de personas, archivos o sistemas que representan un punto único de falla?',
    choices: c('Dependencia baja y controlada.', 'Existen algunas dependencias manejables.', 'Hay procesos importantes concentrados en personas o archivos específicos.', 'Varias operaciones críticas dependen de puntos únicos de falla.', 'La continuidad está en riesgo si faltan personas, archivos o sistemas clave.'),
  },
]

export const recommendationRules = {
  maturityLevels: [
    { min: 0, max: 20, label: 'Empresa Tradicional' },
    { min: 21, max: 40, label: 'Digitalización Inicial' },
    { min: 41, max: 60, label: 'Empresa Digital' },
    { min: 61, max: 80, label: 'Empresa Conectada' },
    { min: 81, max: 100, label: 'Empresa Inteligente' },
  ],
  capacityRecommendation: [
    { min: 70, max: 100, modality: 'guided', label: 'LAUDA 360 Guiado', note: 'Autoservicio con metodología, plantillas y soporte principalmente por email.' },
    { min: 40, max: 69, modality: 'assisted', label: 'LAUDA 360 Asistido', note: 'Ejecución compartida entre LAUDA y el equipo del cliente.' },
    { min: 0, max: 39, modality: 'managed', label: 'LAUDA 360 Gestionado', note: 'LAUDA lidera y coordina la transformación con participación ejecutiva del cliente.' },
  ],
  urgencyLevels: [
    { min: 0, max: 24, label: 'Baja' },
    { min: 25, max: 49, label: 'Media' },
    { min: 50, max: 74, label: 'Alta' },
    { min: 75, max: 100, label: 'Crítica' },
  ],
  overrides: [
    'Si la urgencia es Crítica, no recomendar Guiado como modalidad inicial sin revisión de un consultor LAUDA.',
    'Si existen riesgos graves de seguridad, continuidad o cumplimiento, el resultado debe marcar revisión obligatoria antes de emitir roadmap definitivo.',
    'La recomendación automática es orientativa; un consultor puede ajustarla con justificación registrada.',
  ],
}

export function normalizedScore(scores: number[]): number {
  if (!scores.length) return 0
  const avg = scores.reduce((sum, value) => sum + value, 0) / scores.length
  return Math.round(((avg - 1) / 4) * 100)
}

export function dimensionScore(dimension: DiagnosisDimension, answers: Record<string, number>): number {
  const scores = dimension.questions
    .map((q) => answers[q.id])
    .filter((value): value is number => Number.isFinite(value))
  return normalizedScore(scores)
}

export function maturityScore(answers: Record<string, number>): number {
  const weighted = maturityDimensions.reduce((sum, dimension) => {
    return sum + dimensionScore(dimension, answers) * (dimension.weight / 100)
  }, 0)
  return Math.round(weighted)
}

export function capacityScore(answers: Record<string, number>): number {
  return normalizedScore(internalCapacityQuestions.map((q) => answers[q.id]).filter((v): v is number => Number.isFinite(v)))
}

export function urgencyScore(answers: Record<string, number>): number {
  return normalizedScore(urgencyQuestions.map((q) => answers[q.id]).filter((v): v is number => Number.isFinite(v)))
}
