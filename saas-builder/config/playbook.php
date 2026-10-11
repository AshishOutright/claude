<?php

/*
| The base library every new app starts from. Seeded into the database by
| PlaybookSeeder; add more from the Library screen. Source column says where
| each entry came from in "SAAS builder- Build Core Template" (Google Sheet).
*/

return [

    'constitutions' => [
        ['code' => 'S-001', 'title' => 'Laws are bound to code', 'reason' => 'Detect code mistakes at law level', 'example' => 'A reminder cannot exist without its non-payment link entry', 'source' => 'saas-constitution', 'is_core' => true],
        ['code' => 'S-002', 'title' => 'Laws are bound to UI', 'reason' => 'Detect UI mistakes at law level', 'example' => 'In every list view, Date Created is the first column, shown as "3 days ago"', 'source' => 'saas-constitution', 'is_core' => true],
        ['code' => 'S-003', 'title' => 'Build once, at the highest standard', 'reason' => 'Code was rebuilt 4 times going from low to high standard', 'example' => 'All code in classes and methods; skeleton classes where needed', 'source' => 'code-Learnings', 'is_core' => true],
        ['code' => 'S-004', 'title' => 'Every app meets ACFCM', 'reason' => 'One quality bar for every product', 'example' => 'Automation, Controlling features, 100% flexible, Complete features, Machine learning', 'source' => 'Ashish, project chat', 'is_core' => true],
        ['code' => 'S-005', 'title' => 'The journey is phases, stages and statuses', 'reason' => 'Late phase and stage classification caused confusion', 'example' => 'HRC: Onboarding, Employment, Exit', 'source' => 'code-Learnings', 'is_core' => true],
        ['code' => 'S-006', 'title' => 'aisuites.ai is the central LLM platform', 'reason' => 'One AI layer for every product', 'example' => 'AI summary and AI reply go through aisuites.ai', 'source' => 'code-Learnings', 'is_core' => true],
        ['code' => 'S-007', 'title' => 'Ops modules ship on day one', 'reason' => 'Misuse, health, signup, growth, emergencies and notifications are never "later"', 'example' => 'Service-health and signup dashboards exist before launch', 'source' => 'Rules, Misuse-detection, Growth-Analytics, emergencies, notifications', 'is_core' => false],
        ['code' => 'S-008', 'title' => 'Community and developer portals', 'reason' => 'Users report issues and get API access without support tickets', 'example' => 'community.<app>.com and developer.<app>.com', 'source' => 'Community-Developer', 'is_core' => false],
    ],

    'laws' => [
        ['code' => 'L-UI-001', 'category' => 'ui', 'title' => 'List views show Date Created as the first column, as relative time ("3 days ago")', 'example' => 'Use <x-smartcore::list-view>', 'source' => 'saas-constitution S-002', 'is_core' => true, 'checked_by_core' => true],
        ['code' => 'L-CODE-001', 'category' => 'code', 'title' => 'A record cannot be saved without the records it depends on', 'example' => 'Reminder requires payment_link_id', 'source' => 'saas-constitution S-001', 'is_core' => true, 'checked_by_core' => true],
        ['code' => 'L-CODE-002', 'category' => 'code', 'title' => 'All code lives in classes and methods; routes point at controllers', 'example' => 'No closures in routes/web.php', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => true],
        ['code' => 'L-CODE-003', 'category' => 'code', 'title' => 'Phase, stage and status are separate classes, and every stage can be finished', 'example' => 'SelectPhase and SelectStage (select_phase.php, select_stage.php)', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => true],
        ['code' => 'L-SEC-001', 'category' => 'security', 'title' => 'Security lives in the app; .htaccess only routes requests', 'example' => 'Access rules are middleware, never Deny/Require in .htaccess', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => true],
        ['code' => 'L-DATA-001', 'category' => 'data', 'title' => 'No studio custom fields; real columns with clean names', 'example' => 'reminders.due_at, not reminders_cstm.due_at_c', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => false],
        ['code' => 'L-DATA-002', 'category' => 'data', 'title' => 'Relationships are normalised with foreign keys', 'example' => 'One table per entity, pivot tables for many-to-many', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => false],
        ['code' => 'L-OPS-001', 'category' => 'ops', 'title' => 'Git from day one, with branch-based development', 'example' => 'Every change is a pull request', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => false],
        ['code' => 'L-AI-001', 'category' => 'ai', 'title' => 'AI calls go through the aisuites.ai layer', 'example' => 'App\Services\Ai\AiSuites, never a provider SDK in a controller', 'source' => 'code-Learnings', 'is_core' => true, 'checked_by_core' => false],
        ['code' => 'L-OPS-002', 'category' => 'ops', 'title' => 'Misuse limits on credits and endpoints', 'example' => 'Rate limit per user and API key; alert on credit spikes', 'source' => 'Misuse-detection', 'is_core' => false, 'checked_by_core' => false],
        ['code' => 'L-OPS-003', 'category' => 'ops', 'title' => 'Every emergency has a fix process, an owner and an automatic fallback', 'example' => 'AI summary down: switch to a cached summary and alert the owner', 'source' => 'emergencies', 'is_core' => false, 'checked_by_core' => false],
    ],

    'law_categories' => [
        'ui' => 'UI', 'code' => 'Code', 'data' => 'Data', 'security' => 'Security', 'ops' => 'Operations', 'ai' => 'AI',
    ],

    // ACFCM: the quality bar every app is built to. Each item maps to something the generator adds.
    'acfcm' => [
        'automation' => [
            'label' => 'Automation',
            'help' => 'Work runs on its own, without manual steps.',
            'items' => [
                'journey_events' => 'Fire an event on every status change, so notifications and jobs react automatically',
                'scheduled_jobs' => 'Scheduled jobs for reminders and follow-ups',
                'self_tests' => 'Self-tests in CI: laws:check and the test suite on every pull request',
            ],
        ],
        'controlling' => [
            'label' => 'Controlling features',
            'help' => 'Feature and limit controllers per plan, user and team.',
            'items' => [
                'feature_toggles' => 'Feature toggles per plan, user and team',
                'plan_limits' => 'Plan limits (records, users, AI credits)',
                'misuse_limits' => 'Misuse limits on credits and endpoints',
            ],
        ],
        'flexible' => [
            'label' => '100% flexible',
            'help' => 'Behaviour is changed by settings, not by code.',
            'items' => [
                'settings_config' => 'Settings live in config files, not in code',
                'journey_in_config' => 'The phase / stage / status journey is listed in config/workflow.php',
            ],
        ],
        'complete' => [
            'label' => 'Complete features',
            'help' => 'No half-built modules.',
            'items' => [
                'acceptance_checks' => 'Acceptance checks per phase define "done"',
                'ops_dashboards' => 'Service-health and signup dashboards from the core',
            ],
        ],
        'ml' => [
            'label' => 'Machine learning',
            'help' => 'AI built into the core flows.',
            'items' => [
                'aisuites_client' => 'aisuites.ai client for AI summaries, replies and drafts',
                'ai_feedback_log' => 'Log every AI output a user edits, as eval data',
            ],
        ],
    ],

    // Starting points. Modules: one CRM module per line, * marks the journey module, ! a required field.
    // Journey: one line per phase, "- Stage: Status, Status*" under it; * marks a final status.
    'templates' => [
        'blank' => [
            'label' => 'Blank',
            'modules' => "Records*: notes:text\n",
            'journey' => "Start\n- New: Open, Done*\n",
        ],
        'hr-crm' => [
            'label' => 'HR CRM (HRC)',
            'modules' => <<<'TXT'
Employees*: email:email!, phone:phone, joining_date:date, source:enum(Referral|Job Board|Website|Agency), department:relate(Departments)!
Departments: code
TXT,
            'journey' => <<<'TXT'
Onboarding
- Application: New, Screening, Shortlisted, Rejected*
- Interview: Scheduled, Completed, Passed*, Failed*
- Offer: Drafted, Sent, Accepted*, Declined*
- Joining: Documents Pending, Verified, Joined*
Employment
- Probation: In Probation, Extended, Confirmed*
- Performance Review: Scheduled, In Review, Completed*
- Leave: Requested, Approved*, Rejected*
Exit
- Resignation: Submitted, Accepted*, Withdrawn*
- Handover: Pending, In Progress, Done*
- Full and Final: Calculating, Paid*
TXT,
        ],
        'task-crm' => [
            'label' => 'Task CRM (TRC)',
            'modules' => <<<'TXT'
Tasks*: due_date:date, priority:enum(Low|Medium|High), estimate_hours:decimal, client:relate(Clients)!
Clients: email:email, phone:phone
TXT,
            'journey' => <<<'TXT'
Plan
- Intake: New, Needs Info, Accepted*, Rejected*
- Planning: Estimating, Scheduled*
Execute
- Work: To Do, In Progress, Blocked, Done*
- Review: In Review, Changes Requested, Approved*
Close
- Delivery: Ready, Delivered*
- Feedback: Requested, Received*
TXT,
        ],
        'social-crm' => [
            'label' => 'Social CRM (SRC)',
            'modules' => <<<'TXT'
Posts*: body:text!, publish_at:datetime, social_account:relate(Social Accounts)!
Social Accounts: network:enum(Facebook|Instagram|LinkedIn|X|TikTok)!, handle
TXT,
            'journey' => <<<'TXT'
Connect
- Accounts: Not Connected, Pending Review, Connected*
Publish
- Draft: Idea, Drafting, Ready*
- Schedule: Scheduled, Publishing, Published*, Failed*
Engage
- Inbox: New, Replied*, Ignored*
Grow
- Analytics: Collecting, Reported*
TXT,
        ],
    ],

];
