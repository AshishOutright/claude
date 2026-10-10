"""Draft Upwork proposals with Claude."""
import anthropic

from . import config

SYSTEM = """You write Upwork proposals for a freelancer. Rules:
- Open with one sentence that shows you understood the client's specific problem (never "Dear Sir/Madam" or "I am excited").
- Then 2-4 short paragraphs or bullets: how you'd approach it, relevant experience, a concrete first step.
- Ask 1-2 sharp clarifying questions.
- Keep it under 180 words unless told otherwise. Plain text, no markdown headers.
- Do not invent specific past clients, numbers or certifications that aren't in the freelancer profile."""


class ProposalError(Exception):
    pass


def draft(job: dict, tone: str = "professional", extra: str = "") -> str:
    if not config.ANTHROPIC_API_KEY:
        raise ProposalError("ANTHROPIC_API_KEY is not set in .env")
    budget = (f"Fixed ${job['budget']:.0f}" if job.get("budget")
              else f"Hourly ${job.get('hourly_min') or '?'}-{job.get('hourly_max') or '?'}")
    prompt = f"""Freelancer profile:
{config.FREELANCER_PROFILE}

Job title: {job['title']}
Budget: {budget}
Experience level: {job.get('experience') or 'n/a'}
Skills: {', '.join(job.get('skills') or [])}
Client country: {job.get('client_country') or 'n/a'}

Job description:
{job['description']}

Tone: {tone}
{('Extra instructions: ' + extra) if extra else ''}

Write the proposal now."""
    client = anthropic.Anthropic(api_key=config.ANTHROPIC_API_KEY)
    try:
        msg = client.messages.create(
            model=config.CLAUDE_MODEL,
            max_tokens=1024,
            system=SYSTEM,
            messages=[{"role": "user", "content": prompt}],
        )
    except anthropic.APIError as e:
        raise ProposalError(f"Claude API error: {e}") from e
    return "".join(b.text for b in msg.content if getattr(b, "type", "") == "text").strip()
