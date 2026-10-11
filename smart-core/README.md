# Smart Core (seed)

The shared base every app from the [SaaS Builder](../saas-builder/) runs on. A fix here reaches every product
instead of being copied into each one.

| Part | What it does |
|---|---|
| `SmartCore\Gateway` | The Smart Gateway engine: one JSON endpoint for all data (`login`, `meta`, `list`, `get`, `create`, `update`, `delete`, `move`, `advance`), with validation, signed tokens, rate limits and CORS. `SuiteCrm\` runs it on a SuiteCRM copy; `Store\InMemoryStore` and `Store\JsonFileStore` run it without one. |
| `SmartCore\Workflow` | The 3-class framework: `Phase`, `Stage`, `Status`, plus `SelectPhase` and `SelectStage` (the playbook's `select_phase.php` / `select_stage.php`). |
| `SmartCore\Laws` | Laws as code. `LawRunner` checks a project and fails when a checked law is broken: L-UI-001 (CRM list views, React and Blade lists put Date Created first), L-CODE-002, L-CODE-003, L-SEC-001, and L-CODE-001 enforced by the gateway at runtime. Other laws are listed for review. |
| `SmartCore\Laravel` | Laravel glue for the builder: `php artisan laws:check` and `<x-smartcore::list-view>`. |

`Gateway`, `Workflow` and `Laws` are framework-free PHP 8.3, so the builder copies them into each generated app's
`crm/custom/smart_gateway/core/` and the gateway runs inside the CRM without Composer.

Planned here next, so generated apps get them by recopying the core: organizations and roles, billing and plan
limits, notifications, misuse alerts, and the health and signup dashboards from the playbook review.

Tests: `smart-core/tests`, run from the builder with `php artisan test`.
