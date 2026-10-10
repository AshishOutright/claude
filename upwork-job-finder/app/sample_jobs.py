"""Sample jobs used in DEMO_MODE, so the app works before your Upwork key is approved."""
from datetime import datetime, timedelta, timezone

_SAMPLES = [
    ("SuiteCRM customization and workflow automation", "FIXED", 800, None, None, "INTERMEDIATE",
     ["SuiteCRM", "PHP", "MySQL"], "United States", 4.9, 23, 42000, True,
     "We run SuiteCRM 7 and need custom modules for project tracking, plus workflows that email account managers when deals stall. Must have done SuiteCRM logic hooks before."),
    ("Zoho CRM to HubSpot migration", "FIXED", 1500, None, None, "EXPERT",
     ["Zoho CRM", "HubSpot", "Data Migration"], "United Kingdom", 4.7, 11, 18000, True,
     "Migrate ~40k contacts, deals and notes from Zoho CRM to HubSpot, preserving relationships and owners. Need a clean mapping document first."),
    ("Python developer for FastAPI backend", "HOURLY", None, 25, 45, "INTERMEDIATE",
     ["Python", "FastAPI", "PostgreSQL"], "Germany", 5.0, 7, 9000, True,
     "Ongoing work on a FastAPI service: new endpoints, background jobs and tests. Around 20 hrs/week for 3 months."),
    ("Build Claude-powered support chatbot", "FIXED", 2500, None, None, "EXPERT",
     ["Python", "Anthropic API", "LLM", "RAG"], "Canada", 4.8, 15, 65000, True,
     "Chatbot answering customer questions from our docs using Claude. Needs retrieval over ~500 pages, a handoff to human agents, and an admin page."),
    ("Quick WordPress fix", "FIXED", 30, None, None, "ENTRY_LEVEL",
     ["WordPress"], "India", None, 0, 0, False,
     "Contact form not sending emails. Need it fixed today."),
    ("Salesforce Apex developer - integrations", "HOURLY", None, 40, 70, "EXPERT",
     ["Salesforce", "Apex", "REST API"], "Australia", 4.6, 31, 120000, True,
     "Integrate Salesforce with our ERP via REST. Apex triggers, batch jobs, error logging."),
    ("SugarCRM upgrade and custom dashlets", "FIXED", 1200, None, None, "INTERMEDIATE",
     ["SugarCRM", "PHP", "JavaScript"], "Netherlands", 4.9, 9, 22000, True,
     "Upgrade SugarCRM to the latest version and rebuild 4 custom dashlets that broke."),
    ("Data scraping script needed", "FIXED", 50, None, None, "ENTRY_LEVEL",
     ["Python", "Web Scraping"], "Pakistan", None, 1, 50, False,
     "Need a script to scrape product prices. Budget is low but long-term work possible."),
    ("Vtiger CRM consultant for sales pipeline setup", "HOURLY", None, 20, 35, "INTERMEDIATE",
     ["Vtiger", "CRM", "Sales Automation"], "United Arab Emirates", 4.5, 5, 6000, True,
     "Set up Vtiger pipelines, custom fields and reports for a 15-person sales team."),
    ("Next.js dashboard with charts", "FIXED", 900, None, None, "INTERMEDIATE",
     ["Next.js", "React", "TypeScript"], "United States", 4.8, 12, 30000, True,
     "Analytics dashboard pulling from our REST API, with filters and CSV export."),
    ("Automate lead capture into CRM with n8n", "FIXED", 400, None, None, "INTERMEDIATE",
     ["n8n", "Automation", "CRM", "API Integration"], "France", 4.9, 6, 8000, True,
     "Leads from Facebook Ads, website forms and Calendly should land in our CRM with dedupe and owner assignment."),
    ("Long-term CRM developer (SuiteCRM / Laravel)", "HOURLY", None, 30, 50, "EXPERT",
     ["SuiteCRM", "Laravel", "PHP", "Vue.js"], "United States", 5.0, 40, 250000, True,
     "We maintain several client CRMs. Looking for a long-term developer, 30+ hrs/week."),
]


def sample_jobs(query: str = "") -> list[dict]:
    now = datetime.now(timezone.utc)
    jobs = []
    for i, s in enumerate(_SAMPLES):
        (title, jtype, budget, hmin, hmax, exp, skills, country,
         rating, hires, spent, verified, desc) = s
        jobs.append({
            "id": f"demo-{i + 1}",
            "title": title, "description": desc,
            "url": "https://www.upwork.com/nx/find-work/",
            "job_type": jtype, "budget": budget, "hourly_min": hmin, "hourly_max": hmax,
            "experience": exp, "skills": skills, "client_country": country,
            "client_rating": rating, "client_hires": hires, "client_spent": spent,
            "payment_verified": verified,
            "posted_at": (now - timedelta(minutes=17 * i + 3)).isoformat(),
        })
    words = [w.lower() for w in query.replace(",", " ").split() if len(w) > 2]
    if not words:
        return jobs
    hits = [j for j in jobs
            if any(w in (j["title"] + " " + j["description"] + " " + " ".join(j["skills"])).lower()
                   for w in words)]
    return hits or jobs
