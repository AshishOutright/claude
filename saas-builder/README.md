# SaaS Builder

Start a new SaaS app in minutes instead of months. Pick the base constitutions and laws, write the Core Vision,
lay out the phases, stages and statuses, tick the ACFCM bar, and download a starter app that already runs on
[Smart Core](../smart-core/) with all of it in code.

PHP 8.3, Laravel, MySQL. Everything is in classes; security is middleware in the app, and `.htaccess` only routes.

## The wizard

| Step | What you do | Where it ends up in the starter app |
|---|---|---|
| 1. App basics | Name, one-line summary, starting journey (Blank, HR CRM, Task CRM, Social CRM) | `README.md`, `CLAUDE.md` |
| 2. Constitution | Tick the base constitutions (core ones come ticked) or add a new one to the library | `docs/CONSTITUTION.md`, `CLAUDE.md` |
| 3. Laws | Tick the base laws or add one; laws marked *checked* fail the build when broken | `config/laws.php`, `docs/LAWS.md`, CI |
| 4. Core Vision | The full Core Vision, the simplified version for the team, and before / after | `docs/VISION.md`, `CLAUDE.md` |
| 5. Phases, stages, statuses | Type the journey as an outline with a live preview; `*` marks a final status | One class per phase and stage in `app/Workflow/`, `config/workflow.php` |
| 6. ACFCM | Automation, Controlling features, 100% flexible, Complete features, Machine learning | `config/acfcm.php`, plus the code each item adds |
| 7. Review and generate | Check the summary and the file list, then download the `.zip` | |

The base constitutions and laws are seeded from the playbook sheet ("SAAS builder- Build Core Template") through
`config/playbook.php`. Anything added in the wizard is saved to the library, so the next app can pick it too.

## What a generated app gets

- `app/Workflow/Phases/*Phase.php` and `app/Workflow/Stages/<Phase>/*Stage.php` on Smart Core's `Phase`, `Stage` and `Status`, with `SelectPhase` and `SelectStage` skeleton classes.
- `App\Services\Journey`: start a record on the journey, change its status, and move it to the next stage only once a final status is reached. Every move is logged in `journey_moves`.
- `config/laws.php` read by `php artisan laws:check`, a `LawsTest`, and a GitHub Actions workflow that blocks a pull request when a law breaks.
- Per ACFCM item: a `JourneyMoved` event (automation), a `Features` plan and limit controller (controlling), an aisuites.ai client and an AI edit log (machine learning).
- `CLAUDE.md` with the vision, constitution, laws, journey and ACFCM, so Claude Code agents build to them from the first commit.

Install a generated app with `./install.sh <folder> /path/to/claude/smart-core`. It creates the Laravel app, copies
the files over it, installs Smart Core and runs `laws:check`.

## Run the builder

```bash
cd saas-builder
composer setup            # installs, creates .env, migrates and seeds the playbook
php artisan serve         # http://localhost:8000
```

Set the MySQL details and `BUILDER_PASSWORD` in `.env` first. Without a password the builder only opens with
`APP_ENV=local`.

```bash
php artisan test          # wizard, generator and laws tests
php artisan laws:check    # the builder keeps the laws it hands out
```
