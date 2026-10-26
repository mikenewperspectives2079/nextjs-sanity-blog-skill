# Site Identity Questionnaire

The question bank behind every Site Identity Profile. Ask in rounds (SKILL.md, Step 3): pre-fill from sources first, skip what is known, at most 8 questions per message. "Skip" and "ask them" are always valid answers; nothing is ever guessed.

**Branch tags**

- `[P]` every person · `[LE]` lived experience of addiction or recovery · `[CL]` clinician (licensed or certified) · `[AD]` mental-health or recovery advocate
- `[O]` every organization · `[NP]` nonprofit or community group · `[PR]` practice or business (group practice, coaching, program)

A person can carry several tags (a counselor in long-term recovery is `[LE]` + `[CL]`). Each question names the profile section it fills (§ = section of `identity-profile-template.md`).

---

## Round 0 — Setup (always; one message)

0.1 **Who and where.** Person's name and/or organization's name, the domain, and the WordPress URL if different. → front matter

0.2 **Site type.** Person (lived experience / clinician / advocate / a mix) or organization (nonprofit / practice / other). → front matter, decides the branches

0.3 **Consent and approver.** Has the person or organization agreed to the site, to sharing the details you'll give me, and to reviewing the profile? Who approves it (name and role)? → front matter `approved_by`; without consent the profile stays a draft (SKILL.md Step 1)

0.4 **Source material.** What can I read first? A recorded interview or transcript (best: see `interview-guide.md`), an existing website, LinkedIn, socials, a bio or CV, a Psychology Today or directory profile, talks, podcasts, books, press, an annual report or Form 990. → §14 sources

0.5 **Public-record lookups.** OK to check public registries (NPI Registry and state licensing boards for clinicians; ProPublica Nonprofit Explorer and IRS records for nonprofits) and their own public pages? Results are marked "confirm with the person". → §4, §10

---

## Round 1 — Identity and presence

### People `[P]`

1.1 **Byline.** Exactly how their name appears on the site, and the anonymity level:
- full name + photo · first name + last initial + photo · first name only, no photo · pen name.

Many people in recovery protect their anonymity; any level is fine. A pen name must belong to this real person. → front matter `byline`, §2

1.2 **Name behind a pen name** (only if 1.1 = pen name). → `private-notes.md` only, never the profile

1.3 **Role line.** One line under their name, e.g. "Recovery coach and writer", "LCSW · trauma and addiction therapist", "Mental-health advocate and speaker". → byline, author box, schema `jobTitle`

1.4 **Location to show.** City, region, country, or none. Is the work local, statewide, national or online? → §2, local SEO

1.5 **Pronouns; age range or life stage** (optional: "dad of three", "in my sixties"). Only what they want public. → §2, voice

1.6 **Photos.** Headshot available? Other photos they're happy to use (with anyone else in them consenting)? → §11, author box, schema `image`

1.7 **Existing presence.** Website, socials to link from the site, podcast, YouTube, book, newsletter. → §2, schema `sameAs`

1.8 **Languages** they speak and write; site language(s). → front matter

1.9 **Contact options on the site.** Form, email, phone, booking link, none; anything that must never appear. → §6, contact page

### Organizations `[O]`

1.10 **Names.** Legal name, public or brand name, any DBA. → §10

1.11 **Legal status.** 501(c)(3) (EIN), fiscally sponsored (sponsor's name), other nonprofit, for-profit practice, unincorporated community group. → §10, donation pages

1.12 **Founded** (year) and **where** (address public or not; service area). → §10, local SEO

1.13 **People to show.** Leaders, staff and board members to name on the site, with titles, and who will write articles (each writer gets a short author entry in §10). → §10, author boxes

1.14 **Size.** Staff, volunteers, people served per year (with the source of the number). → §10

1.15 **Logo, socials, newsletter, languages.** → §10, §11

---

## Round 2 — Story

### Lived experience `[LE]` and advocates `[AD]`

Only what they want public. Every item is optional.

2.1 **The short version, in their words.** Two or three paragraphs from an interview, bio or the operator's notes. Verbatim where possible. → §3 summary

2.2 **Disclosure choices.** For each, "openly", "in general terms" or "never":
- substance(s) or behavior(s);
- mental-health diagnoses;
- treatment history (detox, residential, outpatient, medication);
- return to use / relapses;
- legal history;
- family impact.

→ §3 disclosure table; "never" items aren't written down anywhere

2.3 **Recovery date.** Publish the exact date, the year, "X years", or nothing? → §3

2.4 **Turning points.** Three to five specific moments (what happened, who helped, what changed), with what may be told. → §3 story bank, one entry each

2.5 **Pathway(s).** 12-step (say whether they want the fellowship named or just "a 12-step program": many members don't name it publicly, in keeping with its anonymity traditions), SMART Recovery, Recovery Dharma, Celebrate Recovery or another faith-based path, Women for Sobriety, LifeRing, medication for addiction treatment (MOUD/MAT), therapy, harm reduction, moderation, "my own way". → §3, §8 stances

2.6 **Life now.** Work, family (what's shareable), faith or spirituality (only if they want it on the site), community roles, interests. → §3, voice

2.7 **Their "why".** Why this site, and who they most want to reach. → §1 brief, §5

2.8 **Other people in the story.** Anyone who must never be named or must be disguised (family, partners, sponsors, friends). Default: everyone else is anonymized unless they consented. → §9 rules

2.9 **Off-limits.** Topics or memories that must never appear. → §9 rules (as a category, without the details)

### Clinicians `[CL]`

2.10 **Why this work.** What drew them to it; their approach in a sentence or two. → §3

2.11 **Own lived experience.** Do they disclose any? (Many addiction clinicians are in recovery; it's entirely their choice.) If yes, branch to 2.2–2.5. → §3

2.12 **Career moments** that don't involve clients: training, mentors, programs they built, research, teaching. → §3 story bank

2.13 **Client stories.** Default: none. Only vignettes the clinician writes or approves, de-identified and labeled as composites. → §9 rules

### Organizations `[O]`

2.14 **Origin story.** Who started it, when, why; the moment it began. → §3

2.15 **Milestones** with dates (first program, first building, growth, awards). → §3, §10

2.16 **Stories they can tell** about the people they serve: only with written consent, anonymized unless the person chose otherwise. → §3 story bank (consent noted per entry)

---

## Round 3 — Expertise and credentials

### Lived experience and advocates `[LE]` `[AD]`

3.1 **Peer and recovery credentials.** E.g. CPRS, CRPS, PRS, NCPRSS, CARC, recovery-coach training (CCAR), Mental Health First Aid, family-support certifications. Issuing body and state. → §4

3.2 **Experience.** Years of advocacy, roles (peer support, volunteering, speaking, writing), organizations they belong to. → §4

3.3 **Media and recognition.** Talks, podcasts, articles, books, awards, with links. → §4, About page

3.4 **Scope.** What they can speak to (their experience, peer support, navigating systems) and what they leave to professionals (diagnosis, treatment, medication). → §4 scope

### Clinicians `[CL]`

3.5 **Licenses.** Type, number, state(s), status, expiry, and the board's lookup URL (verified in Step 2). Associates and interns: the supervisor's name and credential, if state rules require showing it. → §4, front matter `credentials`

3.6 **NPI number** (from the registry, if they have one). → §4

3.7 **Degrees and certifications.** School, degree, year; certifications such as CADC, LCADC, MAC, CCS, EMDR, CCTP, DBT. → §4

3.8 **Modalities and specialties.** CBT, DBT, ACT, EMDR, IFS, motivational interviewing, CRAFT for families, contingency management, trauma, co-occurring disorders, adolescents, couples. → §4, §8

3.9 **Practice details.** Telehealth and which states (they can only see clients where licensed), in person, insurance or self-pay or sliding scale, booking platform (SimplePractice, TherapyNotes…), directory profiles. → §6

3.10 **Scope.** What they diagnose and treat; prescribing (only physicians and nurse practitioners); what they refer out. → §4 scope

### Organizations `[O]`

3.11 **Licenses and accreditations.** State licensure, CARF, The Joint Commission, NARR certification for recovery residences, LegitScript (needed to advertise addiction treatment on Google and Meta). → §10

3.12 **Programs and services.** Name, who it's for, what happens, cost, eligibility, schedule, how to start. → §6, §10

3.13 **Outcomes and impact.** Numbers with their source and year. Only real, sourced figures. → §10

3.14 **Partners and funders** that may be named. → §10

---

## Round 4 — Audience and goals `[P]` `[O]`

4.1 **Primary readers** (up to 3): people questioning their use, people in early recovery, people in long-term recovery, families and loved ones, people living with a specific mental-health condition, clinicians and peers, employers, donors and volunteers, policymakers. → §5

4.2 **A typical reader.** Their situation, what they feel, what they're searching for, where they live. → §5

4.3 **The questions they get asked most** (5–10, in the asker's words). The best source of articles. → §5, §8 FAQ bank

4.4 **What a reader should do next.** Book a call or session, call, join a group or meeting, subscribe, donate, volunteer, buy the book, book a talk, download a resource. One primary, up to two secondary. → §6, front matter `primary_cta`

4.5 **Offerings.** Services, programs, products: what, who for, price or "free", link. → §6

4.6 **What success looks like** for the site in 6–12 months (bookings, donations, subscribers, speaking requests, reach). → §6

4.7 **Local reach.** Do they serve a city or region? Do they have a Google Business Profile? → §6, local SEO

---

## Round 5 — Voice and language `[P]` `[O]`

5.1 **Voice in 3–5 words**, each with "this, not that" (warm, not syrupy; direct, not harsh; hopeful, not preachy). → §7

5.2 **Samples.** Two or three things they wrote, or transcripts of them talking. Excerpts are kept verbatim. → §7 samples

5.3 **Point of view.** "I" (person) or "we" (organization); talking to the reader as "you". → §7

5.4 **Tone settings.** Humor (none, light, a lot), profanity (never, rare, fine), faith language (none, gentle, central), storytelling versus how-to balance. → §7

5.5 **Reading level** (default grade 7–8) and spelling (US or UK). → §7

5.6 **Words they use and words they avoid.** Common choices:
- self-description: "addict", "alcoholic", "person in recovery", "person with a substance use disorder" (their choice for themselves; for others, default to person-first language);
- avoid by default: "abuser", "junkie", "clean/dirty" for drug tests, "committed suicide" (say "died by suicide");
- "relapse" or "return to use"; "MAT" or "MOUD"; "sober" or "in recovery".

→ §7 terms, front matter `terms_prefer` / `terms_avoid`

5.7 **Signature phrases** or ideas they come back to. → §7

---

## Round 6 — Topics and stances `[P]` `[O]`

6.1 **Content pillars.** Three to six themes; they become the blog categories. → §8, front matter `pillars`

6.2 **Passions and angles.** What they could talk about for an hour; what they see differently from others. → §8

6.3 **Topics to avoid.** → §8, §9

6.4 **Stances on contested questions.** One or two sentences each, or "present the evidence, take no side":
- medication for addiction treatment;
- harm reduction (naloxone, test strips, supervised consumption);
- 12-step and its alternatives; abstinence and moderation;
- cannabis; psychedelic-assisted therapy;
- faith in recovery; involuntary treatment; drug policy and criminal justice.

→ §8 stances; content must never contradict them

6.5 **Trusted sources**, and any to avoid. Defaults: SAMHSA, NIDA, NIAAA, NIMH, CDC, WHO, peer-reviewed journals. → §9

6.6 **Series and recurring formats** they'd enjoy (Q&A, "letters to my early-recovery self", monthly resource roundups). → §8

---

## Round 7 — Boundaries, privacy and compliance `[P]` `[O]`

7.1 **Crisis resources.** Default for US sites: 988 Suicide & Crisis Lifeline (call or text 988), the SAMHSA National Helpline (1-800-662-4357) and the Crisis Text Line (text HOME to 741741). Add local or international lines for non-US audiences. → §9, footer, crisis-adjacent posts

7.2 **Disclaimer.** The default "not medical advice" wording, or theirs. → §9, disclaimer page

7.3 **Who reviews content** before it's published (the person, a clinician, a staff member), and how quickly. → §9

7.4 **Clinicians `[CL]`: testimonials and privacy.** Confirm no client testimonials or reviews on the site: the APA, ACA and NASW ethics codes bar soliciting them from current clients and others vulnerable to undue influence, and state boards may go further. Contact forms must not ask for health information; intake happens in the practice's HIPAA-compliant system. → §9

7.5 **Referrals and money.** Will the site refer readers to treatment providers, recovery residences or labs? Any financial relationship (affiliate links, sponsorships, paid listings, lead fees)? Paying or receiving money for referrals to recovery homes, treatment facilities or labs is a federal crime under EKRA (18 U.S.C. § 220), and many states have patient-brokering laws. Sponsorships and affiliate links need clear disclosure (FTC). → §9

7.6 **Tracking.** Confirm no advertising pixels (Meta, TikTok, Google Ads remarketing) on health-related pages. The FTC has acted against health companies whose pixels shared visitors' health information. Privacy-friendly analytics only, per the fleet defaults. → §9

7.7 **Nonprofits `[NP]`: donations.** Donation platform, the tax-deductibility statement with EIN, and any state charitable-solicitation disclosures their accountant requires. → §9, §10

7.8 **Photos and names of others.** Written consent on file for anyone shown or named. → §9

---

## Round 8 — Brand and imagery `[P]` `[O]`

8.1 **Logo, colors, fonts**, or an existing brand guide. → §11

8.2 **Feel.** Three words for how the site should feel (calm, hopeful, grounded, bold, warm). → §11

8.3 **Imagery.** Photography or illustration; real photos of them or their community; things to avoid. Default avoid list: needles, pills, bottles and other triggering imagery; despair clichés (head in hands, dark rooms); stock "recovery" handshakes. Show connection, daily life, nature, hope. → §11, feeds `blog-image-style-guide.md`

8.4 **Tagline or mission statement.** → §1 brief, §11

---

## Round 9 — Site build inputs (only what differs from the fleet defaults)

9.1 **Pages** beyond the defaults (Home, About, Blog, Resources, Contact, Privacy, Disclaimer, Accessibility, Crisis resources): services, programs, donate, speaking, book, events, press. → §12

9.2 **Blog cadence and launch.** Posts per month, launch date, first topics they're excited about. → §12

9.3 **Anything in the fleet defaults that should change for this site** (another language, a different contact flow, a booking widget, donations). → §12, then `building-wordpress-blog` §1

---

## After the rounds

- Questions answered "ask them" go to §13 Open Questions, grouped so the operator can send them to the person in one message.
- Then write the profile (SKILL.md Step 4).
