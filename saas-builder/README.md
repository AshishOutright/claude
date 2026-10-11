# SaaS Builder

Start a new SaaS app in days instead of months. Pick the base constitutions and laws, write the Core Vision, define
the CRM modules, lay out the phases, stages and statuses, tick the ACFCM bar, and download the app in its three parts:

| Part | What the builder generates |
|---|---|
| **CRM copy** | Real SuiteCRM modules (vardefs, bean classes, list / edit / detail views, dropdowns). No studio custom fields. |
| **Smart Gateway (SG)** | `index.php?entryPoint=smart_gateway`: the one endpoint for all data, running on [Smart Core](../smart-core/). |
| **React app** | Login, lists, forms and the journey panel, built from the gateway's `meta` action. It talks only to SG. |

The builder itself is PHP 8.3, Laravel and MySQL. Everything is in classes; security is middleware in the app, and
`.htaccess` only routes.

## The wizard

| Step | What you do | Where it ends up |
|---|---|---|
| 1. App basics | Name, one-line summary, starting template (Blank, HR CRM, Task CRM, Social CRM) | `README.md`, `CLAUDE.md` |
| 2. Constitution | Tick the base constitutions (core ones come ticked) or add one to the library | `docs/CONSTITUTION.md`, `CLAUDE.md` |
| 3. Laws | Tick the base laws or add one; laws marked *checked* fail the build when broken | SG `config.php`, `docs/LAWS.md`, `bin/laws-check.php` in CI |
| 4. Core Vision | The full Core Vision, the simplified team version, and before / after | `docs/VISION.md`, `CLAUDE.md` |
| 5. CRM modules | One module per line: `Employees*: email:email!, department:relate(Departments)!` | `crm/modules/*`, SG `config.php`, React screens |
| 6. Phases, stages, statuses | The journey of the `*` module, with a live preview; `*` marks a final status | One class per phase and stage in the SG, CRM dropdowns |
| 7. ACFCM | Automation, Controlling features, 100% flexible, Complete features, Machine learning | `CLAUDE.md`, SG `config.php` |
| 8. Review and generate | Check the summary and file list, then download the `.zip` | |

The base constitutions and laws are seeded from the playbook sheet ("SAAS builder- Build Core Template") through
`config/playbook.php`. Anything added in the wizard is saved to the library for the next app.

## What the Smart Gateway does

`login` (CRM users, signed 12-hour tokens), `meta`, `list`, `get`, `create`, `update`, `delete`, and `move` /
`advance` for the journey. It rejects unknown fields, wrong types, missing required fields and missing related
records (law L-CODE-001), only lets a record leave a stage from a final status, limits requests per IP, and allows
CORS only for the configured origins. Full contract: `docs/SMART_GATEWAY.md` in each generated app.

Each generated app also has `php bin/laws-check.php`, `php tests/gateway-smoke.php`, a local gateway with no CRM
(`php -S localhost:8080 tests/dev-gateway.php`) and a CI workflow that runs the laws, the smoke test and the React build.

## Run the builder

```bash
cd saas-builder
composer setup            # installs, creates .env, migrates and seeds the playbook
php artisan serve         # http://localhost:8000
```

Set the MySQL details and `BUILDER_PASSWORD` in `.env` first. Without a password the builder only opens with
`APP_ENV=local`.

### On Render

The repo's `render.yaml` includes a `saas-builder` service (Docker, free plan). In Render, open the Blueprint for
this repo and click **Sync** (or use the Deploy to Render button in the root README), then set `BUILDER_PASSWORD`.
On the free plan the builder sleeps after 15 minutes idle and its SQLite data (apps you started, library additions)
is wiped on each redeploy. For data that lasts, add a disk at `/app/saas-builder/database` on a paid plan, or point
`DB_CONNECTION=mysql` and the `DB_*` settings at your MySQL.

```bash
php artisan test          # wizard, generator, generated-app laws and Smart Core gateway tests
php artisan laws:check    # the builder keeps the laws it hands out
```
