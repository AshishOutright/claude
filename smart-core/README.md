# Smart Core (seed)

The shared base every app from the [SaaS Builder](../saas-builder/) is generated on. Apps install it as the
`outright/smart-core` Composer package, so a fix here reaches every product instead of being copied into each one.

What is in it today:

| Part | What it does |
|---|---|
| `SmartCore\Workflow` | The 3-class framework: `Phase`, `Stage`, `Status`, plus `SelectPhase` and `SelectStage` (the playbook's `select_phase.php` / `select_stage.php`). |
| `SmartCore\Laws` | Laws as code. `php artisan laws:check` reads the app's `config/laws.php` and fails when a checked law is broken. |
| `<x-smartcore::list-view>` | The list component that puts Date Created first as "3 days ago" (law L-UI-001). |
| `RequiresDependencies` | Model trait that blocks saving a record without the records it depends on (law L-CODE-001, constitution S-001). |

Laws with an automatic check: L-UI-001, L-CODE-001 (at runtime), L-CODE-002, L-CODE-003, L-SEC-001. Every other law
an app enables is listed as `MANUAL` and reviewed on each pull request.

This is a seed. Auth, organizations, roles, billing, plan limits, notifications, misuse limits and the health and
signup dashboards from the playbook review are planned here, so generated apps get them by updating this package.
