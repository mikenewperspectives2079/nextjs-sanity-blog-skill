---
name: building-site-identity
description: Use when starting a new website for a person or organization (people in addiction recovery, recovery and mental-health advocates, therapists and other clinicians, nonprofits, practices) and you need their identity profile, background, voice and content rules written down before any content is produced. Triggers on "new site for <name>", "build the profile / background / identity for <person or organization>", "author profile", "brand brief for Content Orchestrator", "onboard a new client site", or any request to interview someone so an AI writer can write as or about them.
---

# Building a Site Identity Profile

## Overview

Every site in the fleet gets one **Site Identity Profile**: who runs the site, their story, expertise, audience, voice, topics, and the rules any writer (human or AI) must follow. It is the single reference for the Content Orchestrator and for the site build (`building-wordpress-blog`).

The profile is built from **real information only**: what the person or organization tells you, their own published material, and public records. The workflow collects, organizes and checks; it never invents. In recovery and mental health that is a hard rule, not a style choice:

- invented stories, credentials or testimonials mislead vulnerable readers;
- they break professional ethics codes and state licensing rules;
- they put the site at risk under Google's policies for health ("Your Money or Your Life") content.

A pen name is fine (many people in recovery protect their anonymity); an invented person or invented experience is not. If the operator asks for a fabricated persona, say so plainly and offer the honest alternatives: an organization byline, a pen name for a real contributor, or a site without a personal story.

## Files

- `identity-questionnaire.md`: the question bank. Round 0 (setup) plus rounds 1–9, each with branches for people (lived experience, clinician, advocate) and organizations (nonprofit, practice). Every question says why it matters and what it feeds.
- `interview-guide.md`: a 45-minute, trauma-informed interview script the operator can use on a recorded call. One good transcript answers most of the questionnaire in the person's own voice.
- `identity-profile-template.md`: the output. YAML front matter (machine-readable) + the profile body (for the Content Orchestrator and humans).
- `private-notes-template.md`: what must never reach a writer (legal name behind a pen name, contact details, off-the-record context).

## Where files live

Per site, in the operator's **private** fleet workspace, never in this skill's repository (it may be public, and these profiles hold health information):

```
<fleet-workspace>/sites/<domain>/
  identity-profile.md     # the profile (source of truth)
  private-notes.md        # never given to the Content Orchestrator or any writer
  sources/                # transcripts, bios, screenshots of public records
```

Google Docs mirror each profile in a Drive folder named **Site Identity Profiles**, one Doc per site: `<domain> — <public name> — Identity Profile`. Ask the operator for the fleet workspace path and the Drive folder the first time; record both in the fleet defaults so later sites don't ask again.

## Workflow

### Step 1 — Round 0: set up (one message)

Ask the Round 0 questions from `identity-questionnaire.md`: name(s) and domain, site type, **consent and approver**, available source material, and whether public-record lookups are OK.

- **Consent.** The profile can only be marked approved when the person (or an authorized representative of the organization) has agreed to the site and has reviewed the profile. Without that, you can still draft questions for them, but nothing is marked approved and nothing goes to the Content Orchestrator for publishing.
- **Source material** is the biggest time saver. Ask for a recorded interview (see `interview-guide.md`), an existing bio, website, LinkedIn, a Psychology Today profile, talks, podcasts, books, annual reports.

### Step 2 — Gather before asking

Pre-fill everything you can, and mark each fact with its source:

- **Their own material**: read the links and files the operator provides (WebFetch for pages; Google Drive tools for Docs; transcripts from the meeting recorder if one is connected).
- **Public records** (with the operator's OK from Round 0):
  - Clinicians: the NPI Registry (`https://npiregistry.cms.hhs.gov/api/?version=2.1&first_name=…&last_name=…&state=…`, free, no key) returns name, credential, taxonomy (specialty), practice address and NPI number. Its license numbers are self-reported, so then check the state licensing board's lookup for license type, number, status and expiry, and link that page in the profile as the verification URL. There is no free multi-state license API.
  - Nonprofits: ProPublica Nonprofit Explorer (`https://projects.propublica.org/nonprofits/api/v2/search.json?q=…`, then `/organizations/<EIN>.json`, free, no key) for legal name, EIN, address, NTEE code, ruling date and filed 990s. Cross-check tax-exempt status with the IRS Tax Exempt Organization Search.
  - If the session can't reach these sites (some sandboxes block them), give the operator the lookup links and ask them to paste the result.
- Public-record facts are marked "public record — confirm with the person" until the person confirms them.

Then show the operator a short "what I already know / what's missing" summary before Round 1.

### Step 3 — Ask only the gaps, one round per message

Work through rounds 1–9 in order, skipping questions already answered and branches that don't apply.

- **At most 8 questions per message.** Use AskUserQuestion for multiple-choice items (site type, anonymity level, recovery pathways, point of view); put open questions in a numbered list the operator can answer in one reply, in any order, by voice if they like.
- **Accept "skip" and "ask them".** "Ask them" questions go to the Open Questions list in the profile, which becomes a short follow-up for the person. Never fill a gap with a guess.
- **Use the person's own words.** Quote verbatim when the operator pastes something the person said or wrote; mark paraphrases as paraphrases.
- **Trauma-informed wording.** Questions about someone's history are optional by design. Ask what they are comfortable sharing publicly, not for details for their own sake.

### Step 4 — Write the profile

Fill `identity-profile-template.md` into `sites/<domain>/identity-profile.md`, and `private-notes-template.md` into `private-notes.md`.

- Every factual line carries a source tag: `[interview 2026-11-02]`, `[bio]`, `[public record: NPI]`, `[operator]`.
- Anything not approved for publication goes to `private-notes.md`, never into the profile.
- The **story bank** holds the only anecdotes writers may use, each with what may and may not be changed.
- Set `status: draft`.

### Step 5 — Create the Google Doc

Upload the profile **body** (everything after the front matter) with the Google Drive connector: `create_file` with `contentMimeType: "text/markdown"`, the title above, and the Site Identity Profiles folder as `parentId`. Drive converts it to a Google Doc; headings, tables, lists, quotes and checkboxes survive (verified). Write the Doc's URL into the front matter (`google_doc`). Don't share the Doc with anyone unless the operator asks.

### Step 6 — Review and approval

The person reviews the Google Doc (comments or suggested edits are fine).

- Pull their changes back with `download_file_content` (`exportMimeType: "text/markdown"`), merge them into `identity-profile.md`, and resolve their comments with the operator.
- When they approve: set `status: approved`, `approved_by` and `approved_on`, bump `profile_version`, and add a change-log line.
- The Drive connector can't edit a Doc's text in place. For each new version, upload a fresh Doc and move the previous one into an `Archive` subfolder (`update_file` with the archive folder as `parentId`).

### Step 7 — Hand off

- **Content Orchestrator**: give it `identity-profile.md` (the whole file, front matter included). Never give it `private-notes.md`.
- **Site build**: when running `building-wordpress-blog`, pre-fill its §1 Project Profile from the profile (author model, categories from the content pillars, audience, image direction) and from the fleet defaults; ask the intake only about what differs.
- **Hero images**: the Brand & imagery section seeds `blog-image-style-guide.md`.

### Step 8 — Keep it current

Revisit each profile every quarter, and after milestones (a recovery anniversary the person wants marked, new credentials, new programs, a book). New facts follow the same rules: sourced, approved, versioned.

## Rules for any writer using a profile

These are written into every profile (section 9) so the Content Orchestrator sees them:

1. Use only facts in the profile. If something isn't there, leave it out or add it to Open Questions.
2. Personal anecdotes come only from the story bank. Never invent stories, quotes, dialogue, dates, statistics, credentials, awards, clients, outcomes or testimonials.
3. Quote the person verbatim only from passages marked verbatim.
4. No diagnosis, treatment instructions or medication advice for readers; point to a professional. Clinicians' content follows their scope of practice.
5. Suicide and self-harm topics follow safe-messaging guidelines (reportingonsuicide.org), and every post on crisis-adjacent topics shows the crisis resources in the profile.
6. Health facts cite current primary sources (SAMHSA, NIDA, NIAAA, NIMH, CDC, WHO, peer-reviewed research), with the date checked.
7. Respect the profile's language choices ("in recovery", "substance use disorder", the person's own self-description) and its stances on contested topics.
8. Nothing from `private-notes.md`, ever.

## Common mistakes

- Asking all 85 questions in one go. Pre-fill, skip, and ask in rounds.
- Treating public records as confirmed. They are leads until the person confirms.
- Using the legal name of someone who writes under a pen name anywhere in the profile or the Doc.
- Client testimonials on a clinician's site (see Round 7).
- Marking a profile approved because the operator approved it. The person (or organization) approves their own profile.
