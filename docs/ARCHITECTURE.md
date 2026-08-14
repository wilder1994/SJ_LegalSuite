# SJ_LegalSuite — Arquitectura

> Núcleo: módulo **Disciplinario**. Diseñado para alto volumen, trazabilidad legal
> y un workflow de estados estricto.

---

## 1. Stack

- **Laravel 12** (PHP 8.2+)
- **MySQL** (`sj_legalsuite`, charset `utf8mb4_unicode_ci`)
- **Apache** sobre Laragon, puerto **8082**
- **Spatie Permission v6** para roles & permisos
- **Frontend** (siguiente fase): se recomienda **Livewire 3** para los dashboards
  dinámicos y filtros combinables. La capa actual es backend puro (JSON).

---

## 2. Estructura de carpetas

```
app/
  Enums/
    UserArea.php
    Disciplinary/
      CaseStatus.php          ← estado del caso (denormalizado)
      CaseBucket.php          ← agrupación KPI (pendiente/en_proceso/finalizado)
      StageType.php           ← etapa del workflow (informe, citacion, ...)
      StageStatus.php         ← estado de la etapa
      DocumentType.php
      FaultSeverity.php
      ActionType.php          ← tipos de evento del audit log
      Decision.php
  Exceptions/
    Disciplinary/
      InvalidStateTransitionException.php
  Workflow/
    Disciplinary/
      TransitionMap.php       ← single source of truth de transiciones permitidas
  Models/
    User.php                  ← extendido con HasRoles, area, scopes
    Personnel.php             ← personal disciplinable (desacoplado de User)
    Disciplinary/
      DisciplinaryCase.php    ← raíz del agregado
      DisciplinaryStage.php
      DisciplinaryAction.php  ← audit log inmutable
      DisciplinaryDocument.php
      Fault.php
  Services/
    Disciplinary/
      DisciplinaryCaseService.php       ← orquesta creación + asignación + faltas
      DisciplinaryWorkflowService.php   ← ÚNICA puerta para mover estado
      DisciplinaryDashboardService.php  ← KPIs y agregaciones
      DisciplinaryDocumentService.php   ← upload/delete con auditoría
  Policies/
    DisciplinaryCasePolicy.php
  Http/
    Controllers/Disciplinary/
      DisciplinaryCaseController.php
      DisciplinaryDashboardController.php
    Requests/Disciplinary/
      StoreDisciplinaryCaseRequest.php
      TransitionStageRequest.php
  Providers/
    AppServiceProvider.php    ← registra policies

database/
  migrations/
    2026_04_30_154730_create_permission_tables.php
    2026_04_30_160000_extend_users_for_legalsuite.php
    2026_04_30_160100_create_employees_table.php
    2026_04_30_160200_create_faults_table.php
    2026_04_30_160300_create_disciplinary_cases_table.php
    2026_04_30_160400_create_disciplinary_case_fault_table.php
    2026_04_30_160500_create_disciplinary_stages_table.php
    2026_04_30_160600_create_disciplinary_actions_table.php
    2026_04_30_160700_create_disciplinary_documents_table.php
  seeders/
    DatabaseSeeder.php
    RolesAndPermissionsSeeder.php
    FaultsCatalogSeeder.php
    DemoUsersSeeder.php
    WorkflowSmokeTest.php     ← prueba E2E manual del workflow
```

### Decisiones de capa

| Capa | Responsabilidad | Cómo se respeta |
| --- | --- | --- |
| **Models** | Estructura, relaciones, scopes, casts | Sin lógica de negocio mutativa |
| **Services** | Lógica de negocio compleja, transacciones, side-effects | Inyectados vía constructor |
| **Workflow** | Máquina de estados (transiciones permitidas) | `TransitionMap` único |
| **Policies** | Autorización por rol/permiso/ownership | Registradas en `AppServiceProvider` |
| **FormRequests** | Validación + autorización HTTP | Llaman a Policy via `$user->can(...)` |
| **Controllers** | HTTP thin layer, sin lógica de dominio | Sólo orquestan Services |

> **NO se usa Repository pattern.** Eloquent ya cumple ese rol en Laravel.
> Los scopes (`scopeBucket`, `scopeAssignedTo`, `scopeWithFault`, etc.) son la
> capa de query reutilizable. Esto evita duplicar abstracciones.

---

## 3. Modelo de datos

```
                        ┌─────────────┐
                        │    users    │ (Spatie roles)
                        └──────┬──────┘
                               │ assigned_lawyer_id / reporter_id
                               ▼
       ┌─────────────┐  ┌─────────────────────┐  ┌────────────────────┐
       │  employees  │◄─┤  disciplinary_cases │─►│ disciplinary_stages │
       └─────────────┘  └─────────┬───────────┘  └────────────────────┘
                                  │ 1                       ▲
                                  │                         │
                       ┌──────────┴───────────┐             │
                       ▼                      ▼             │
       ┌──────────────────────────┐  ┌────────────────────┐ │
       │ disciplinary_documents   │  │ disciplinary_actions│ │
       └──────────────────────────┘  └────────────────────┘ │
                                                            │
                       ┌──────────────────────┐             │
                       │ disciplinary_case_   │  ┌──────────┴──┐
                       │       fault          │  │   faults    │
                       └──────────────────────┘  └─────────────┘
                              (pivote N:N)         (catálogo)
```

### Optimizaciones para alto volumen

1. **Estado denormalizado**: `disciplinary_cases.current_status` y
   `current_stage_type` están indexados → todas las consultas del listado y
   dashboard van directo a la tabla raíz, sin joins contra `disciplinary_stages`.
2. **Índices compuestos**:
   - `(current_status, assigned_lawyer_id)` → carga por abogado
   - `(current_status, city)` → mapa geográfico
   - `(assigned_lawyer_id, opened_at)` → pipeline temporal por abogado
   - `(disciplinary_case_id, sequence)` en stages → línea de tiempo del caso
3. **Audit log particionable**: `disciplinary_actions` está indexado por
   `(disciplinary_case_id, performed_at)`. Cuando crezca a millones de filas, se
   puede particionar por `YEAR(performed_at)` sin tocar la lógica.
4. **Soft deletes** en `users`, `employees`, `disciplinary_cases` y
   `disciplinary_documents` para conservar historial legal.
5. **Faltas vía pivote** (`disciplinary_case_fault`) con `unique(case_id, fault_id)`
   y `extra_info` para el caso especial "Otros".

---

## 4. Workflow de estados

### Diagrama

```
       BORRADOR
          │
          ▼
       INFORME ────────────────────────► ARCHIVADO
          │
          ▼
   CITACION_PROGRAMADA ──┐
       │   │   │         │
       │   │   └─► CITACION_NO_ASISTIO
       │   │              │
       │   │              ▼
       │   │       JUSTIFICACION_PENDIENTE
       │   │           │            │
       │   │           ▼            ▼
       │   └──► REPROGRAMADO   COMITE_DISCIPLINARIO
       │            │                │
       │            └─► (volver a CITACION_PROGRAMADA)
       │                             │
       ▼                             ▼
    DILIGENCIA ◄─────────────────────┤
          │                          │
          ▼                          │
       DECISION ◄────────────────────┘
        │     │
        │     └──► APELACION ──► SEGUNDA_INSTANCIA
        │                              │
        ▼                              ▼
       FINALIZADO ──────────► ARCHIVADO
```

La fuente única de verdad: `App\Workflow\Disciplinary\TransitionMap`.

### Ejecución de una transición

Toda transición debe pasar por `DisciplinaryWorkflowService::transition()`,
que de forma **transaccional** garantiza:

1. La transición está permitida (`TransitionMap::canTransition`).
2. Se crea automáticamente la `DisciplinaryStage` correspondiente al nuevo estado.
3. Se actualiza `current_status` y `current_stage_type` en el caso.
4. Se registra una entrada en `disciplinary_actions` (audit log).
5. Si el estado es terminal, se cierra `closed_at`.

Métodos de alto nivel listos para usar:

```php
$wf->scheduleCitation($case, $actor, $when, $location);
$wf->markCitationNoShow($case, $actor);          // abre ventana de 2 días automáticamente
$wf->acceptJustification($case, $actor, $newCitationAt);
$wf->rejectJustification($case, $actor);
$wf->recordDecision($case, $actor, Decision::AMONESTACION_ESCRITA, $notes);
$wf->fileAppeal($case, $actor);
$wf->finalize($case, $actor);
$wf->archive($case, $actor);
```

### Plazos legales (deadlines)

Cuando se llama `markCitationNoShow`, el WorkflowService crea automáticamente
una stage de tipo `JUSTIFICACION` con `deadline_at = now()->addDays(2)` (**2 días calendario**).

El scope `DisciplinaryStage::scopeOverdue()` permite identificar etapas vencidas
para enviar recordatorios o pasar al comité.

---

## 5. Roles y permisos

### Roles (Spatie)

| Rol | Capacidades | Usuario demo |
| --- | --- | --- |
| `admin` | Todos los permisos (si `read_only` en BD = false) | `admin@sjlegalsuite.local` |
| `admin` + **solo lectura** | Consulta disciplinarios, dashboard y usuarios; sin mutaciones | `admin.consulta@sjlegalsuite.local` |
| `abogado` | Casos asignados + bandeja INFORME sin titular (`claim` / `inInformePool`) | `abogado@sjlegalsuite.local` |
| `planeacion` | Ver + programar fechas en etapas | `planeacion@sjlegalsuite.local` |
| `administrativa` | Informes + evidencias | `administrativa@sjlegalsuite.local` |
| `auditor` | Consulta + export disciplinario | `auditor@sjlegalsuite.local` |
| `operaciones` | Crear casos + evidencias | `operaciones@sjlegalsuite.local` |

El campo **`users.read_only`** (boolean) fuerza modo consulta en policies aunque el rol sea `admin` u otros con permisos de escritura.

Los roles legacy `juridico` y `gerencia` se eliminan del seeder al ejecutar `RolesAndPermissionsSeeder`.

> Contraseña por defecto en local: **`SJseguridad2026`**. Cambiar antes de producción.

### Permisos disponibles

```
disciplinary.view
disciplinary.view-dashboard
disciplinary.create
disciplinary.update
disciplinary.delete
disciplinary.transition
disciplinary.assign
disciplinary.assign-date
disciplinary.upload-document
disciplinary.export
employees.view
employees.manage
users.view
users.manage
```

### Áreas del usuario (atributo, no rol)

`UserArea` enum: `juridica`, `operaciones`, `planeacion`, `administrativa`, `gerencia`.

Se usa para:
- Filtrar listados ("mis casos del área")
- Reglas adicionales en policies (ej: planeación puede agendar)
- Reportes ejecutivos

---

## 6. Endpoints (API JSON, base `web` + `auth`)

| Método | Ruta | Acción |
| --- | --- | --- |
| `GET` | `/disciplinary/dashboard` | KPIs + por-falta + por-ciudad + carga-por-abogado |
| `GET` | `/disciplinary/cases` | Listado paginado con filtros (`q`, `status`, `bucket`, `lawyer_id`, `city`, `fault_id`, `from`, `to`) |
| `POST` | `/disciplinary/cases` | Crear caso + faltas |
| `GET` | `/disciplinary/cases/{case}` | Detalle con stages, documentos, actuaciones |
| `GET` | `/disciplinary/cases/{case}/transitions` | Transiciones permitidas desde el estado actual |
| `POST` | `/disciplinary/cases/{case}/transition` | Mover el caso al estado `to` |

> Hoy todas requieren `auth` (Laravel default). Cuando se monte el frontend,
> se decide si se mantiene en `web` (Blade/Livewire) o se expone también con
> Sanctum para clientes externos.

---

## 7. Audit log y trazabilidad legal

Cada operación relevante deja registro **inmutable** en `disciplinary_actions`:

| Tipo de actuación | Cuándo se genera |
| --- | --- |
| `caso_creado` | `DisciplinaryCaseService::create` |
| `caso_asignado` | `DisciplinaryCaseService::assignLawyer` |
| `estado_transicionado` | Toda llamada a `WorkflowService::transition` |
| `fecha_etapa_actualizada` | `WorkflowService::updateStageSchedule` (programación por Planeación / Jurídico) |
| `documento_cargado` / `documento_eliminado` | `DocumentService::upload/delete` |
| `justificacion_aceptada` / `_rechazada` | Métodos del WorkflowService |
| `decision_tomada` | `WorkflowService::recordDecision` |
| `apelacion_interpuesta` | `WorkflowService::fileAppeal` |
| `caso_finalizado` / `caso_archivado` | Cierre del proceso |

Cada registro guarda: `user_id`, `from_status`, `to_status`, `description`,
`metadata` (JSON), `performed_at`.

> **Política:** las actuaciones nunca se editan ni se borran (no exponen `update`
> en la API). Si hay un error, se registra otra actuación correctiva.

---

## 8. Cómo agregar una nueva etapa al workflow

1. Agregar el caso al enum `App\Enums\Disciplinary\CaseStatus`.
2. Agregar el caso (si aplica) a `App\Enums\Disciplinary\StageType` con su
   `formCode()`.
3. Actualizar `App\Workflow\Disciplinary\TransitionMap::map()` agregando las
   transiciones origen → destino permitidas.
4. (Opcional) Mapear el nuevo `CaseStatus` a un `StageType` en
   `DisciplinaryWorkflowService::stageTypeForStatus()` si quieres que la stage
   se cree automáticamente.
5. (Opcional) Agregar un helper público al WorkflowService si requiere
   side-effects custom (deadlines, validaciones extra).
6. Si requiere permisos nuevos, agregarlos en `RolesAndPermissionsSeeder`.

---

## 9. Cómo correr el sistema (local)

```bash
cd c:\laragon\www\SJ_LegalSuite

# Migrar y sembrar todo
php artisan migrate:fresh --seed

# (Opcional) prueba E2E del workflow
php artisan db:seed --class="Database\Seeders\WorkflowSmokeTest"
```

URLs:
- http://172.16.16.90:8082 (LAN)
- http://localhost:8082

Usuarios demo (password `SJseguridad2026`):
- admin@sjlegalsuite.local
- admin.consulta@sjlegalsuite.local (admin solo lectura)
- abogado@sjlegalsuite.local
- planeacion@sjlegalsuite.local
- administrativa@sjlegalsuite.local
- auditor@sjlegalsuite.local
- operaciones@sjlegalsuite.local

---

## 9.1 Portal supervisor — Historial (solo texto)

Ruta: `GET /disciplinary/historial` · Livewire `App\Livewire\Disciplinary\Supervisor\HistoryIndex` · servicio `App\Support\Disciplinary\SupervisorActivityHistoryService`.

| Regla | Detalle |
|-------|---------|
| Quién | Solo rol Spatie **`nivel7`** (cargo supervisor). Otros roles → 403. |
| Qué ve | Feed textual de **su** actividad: `InformeSubmission` con `submitted_by` = él; `DisciplinaryDocument` con `uploaded_by` = él y notas `NOTE_CITATION_EVIDENCE_PREFIX` / `NOTE_DECISION_EVIDENCE_PREFIX`. |
| Qué no ve | Número de expediente, rutas/disco, preview PDF, descarga, enlace a `cases.show`. Coherente con `DisciplinaryCasePolicy::view` / `viewAny` (denegados a nivel7). |
| UI | Agrupación por día, chips de tipo, búsqueda por nombre/cédula. Sin modales de documento. |
| Tests | `tests/Feature/Disciplinary/SupervisorHistoryTest.php` |

La cola **Mi trabajo** (`evidences-pending`) sigue siendo el hub operativo (pendientes). El **Historial** es la bitácora de lo ya cargado/enviado.

---

## 9.2 Dashboard disciplinario (cockpit)

Ruta: `GET /disciplinary/dashboard` · Livewire `App\Livewire\Disciplinary\Dashboard` · `App\Services\Disciplinary\DisciplinaryDashboardService::build`.

| Pieza | Detalle |
|-------|---------|
| Alcance | Abogado (`nivel6` sin `nivel1`): solo asignados (`usesAssignedOnlyScope`). Admin / resto con `viewDashboard`: cartera global. |
| Chips | `actionChips()` → listado filtrado (cerrados, vencidos, por vencer, notif. pendiente, pool/pre-informe…). |
| Donas A–F | Apex en `resources/js/disciplinary-dashboard.js`. Grid Blade `2 / sm:4 / xl:7` (no `lg:7`: con sidebar el main a ~1024px aplastaba columnas). Ancho de chart = celda real (sin piso 96px); `.apexcharts-canvas` centrado tras `render`/`resize`. |
| Layout | Cabecera compacta (sin banner «Cartera vacía» ni subtítulo KPI). Mapa \| Top + Faltas con `min-h`. Franja **Mi carga** / **Carga por abogado**. Shell: `layouts/app` → `main` con `overflow-y-auto` + `min-h-0`. |
| Tests | `tests/Feature/Disciplinary/DisciplinaryDashboardScopeTest.php` |

Detalle de producto y rutas: `README.md` (tabla del módulo Disciplinarios).

---

## 10. Documentación adicional

| Documento | Contenido |
|-----------|-----------|
| [`docs/PDF.md`](PDF.md) | Construcción de PDF: motor Dompdf/Browsershot, paginadores FO-GJ-03/04, plantillas Blade, rutas, pruebas y calibración |
| [`docs/GAP_DISCIPLINARIO_ETAPAS_A_B.md`](GAP_DISCIPLINARIO_ETAPAS_A_B.md) | Brechas etapas A–B (incluye B10–B12: orden notificación→fechas, plantillas de artículos, redacción por género) |
| [`README.md`](../README.md) | Visión de producto: portal supervisor, Historial, dashboard disciplinario (cockpit), FO-GJ-51, zonas |

---

## 11. Próximos pasos sugeridos

1. **Autenticación**: instalar Laravel Breeze o Fortify (login + UI mínima).
2. **Frontend del módulo** con **Livewire 3 + Alpine + Tailwind**:
   - Página dashboard con KPIs y gráficas (Chart.js o ApexCharts).
   - Listado de casos con filtros combinables (3 vistas rápidas en tarjetas).
   - Vista de detalle del caso con timeline de stages + actuaciones.
   - Wizard de transición (botón "Gestionar" → modal con transiciones permitidas).
3. **Notificaciones**: alertas a abogados cuando deadline_at se acerque.
4. **Exportar** PDF de actuaciones (compatible con FO-GJ-XX) usando `barryvdh/laravel-dompdf`.
5. **Tests Feature** del workflow (reemplazar `WorkflowSmokeTest` por tests Pest reales).
6. **Indexes adicionales** según patrones reales de uso (medir con `EXPLAIN`).
7. **Integración con SJ_Armory** vía `employees.external_id` cuando se decida.
