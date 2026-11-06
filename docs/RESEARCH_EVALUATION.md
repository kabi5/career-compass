# Research implementation and evaluation protocol

**Status: protocol, not completed research.** No participant recruitment, interviews, psychometric study, school trial or outcome-prediction study is claimed by this document. Software test evidence belongs in the executed test report and must include the actual command, environment, date and result.

## Alignment with the five research objectives

| Objective | Implemented support / study material | Evidence to collect |
| --- | --- | --- |
| 1. Identify learners' guidance needs | Profile aspirations and preferences; pre-study questions below | Consented needs questionnaire, interview themes and missing-information patterns |
| 2. Analyse influences on career choice | Separate interest, marks, aspirations, subject and preference inputs | Learner explanations, counsellor review and controlled sensitivity comparisons; no causal claim from correlations |
| 3. Design and develop the system | PHP/MySQL web application and private Flask decision-support service; versioned assessment/recommendation records | Architecture and schema diagrams, requirements traceability, source snapshots and executed tests |
| 4. Integrate educational, career and funding information | Curated official-source directory plus retained unverified legacy records | Source provenance, field completeness, rule coverage, update dates and source-linked answer correctness |
| 5. Evaluate usability, functionality and recommendation relevance | Feedback, histories, comparison and what-if workflows | Task results, comprehension, usefulness ratings, qualitative concerns and defect severity |

The final Chapter 4 should cite the source files and deployment version actually tested. Use the implementation documentation for current module/table names. Do not describe proposed features as implemented, administrative screen views as an independent security audit, or prototype participants as the Lesotho school population.

## Chapter 4 writing outline and evidence

1. **Development context:** high-school career exploration in Lesotho; user needs remain hypotheses until the needs study runs.
2. **Actors/use cases:** a learner maintains a profile, completes/retakes the assessment, recalculates from existing interests, investigates a programme, simulates subject changes, compares careers, saves a plan and tracks milestones. An educator sees permitted school aggregates and explicitly authorised individual guidance records. An administrator manages sources, verification and roles.
3. **Architecture:** browser HTML/CSS/JavaScript communicates with PHP; PHP owns sessions, access checks, MySQL persistence and calls the private Flask service. Flask reads the occupational reference catalogue and performs scoring, rule checks and retrieval. Docker and XAMPP are two deployment paths for this architecture.
4. **Data design:** distinguish current profile from profile snapshots, assessment attempts from recommendation runs, and saved references from copied official facts. Describe foreign keys, ownership restrictions, non-destructive migration and content-verification history from the final schema.
5. **Methodology:** describe the actual configured weights, missing-feature renormalisation, similarity method, tie handling and any lexical signals. Include an illustrative calculation clearly labelled as a demonstration, not a participant result. Record engine version/configuration with every evaluated run.
6. **Interfaces:** capture the final learner dashboard, accessible interest chart/table, source-labelled programme, simulation, comparison, roadmap, educator scope checks and content editor. Screenshots must use test accounts and synthetic data; crop identifying information.
7. **Security/privacy:** describe implemented mechanisms and separately list operational dependencies such as HTTPS, mail delivery, trusted school assignment, backups and retention policy.
8. **Constraints:** incomplete programme rules, unvalidated assessment and Sesotho translation, non-local occupational reference data, absent reliable local employment forecasting data and no outcome-labelled training set.

### Recommendation interpretation

The system is a deterministic, configurable decision-support matcher. A displayed match score is an index combining available signals, not a probability of admission, employment, future satisfaction or career success. Academic preparation is a heuristic from supplied information; eligibility is a separate check against a specific source-backed programme rule set. Unknown data is not a failing mark.

The existing repository supplies reference interest vectors and educational categories. It does not supply a representative, consented Lesotho dataset with known learner-to-career outcomes. Supervised training and model “accuracy” reporting are therefore unjustified. Synthetic cases are appropriate for software tests, but must never be relabelled as training evidence or real respondents. Any later ML study needs a defined prediction target, lawful longitudinal collection, representative sampling, leakage control, held-out evaluation and fairness analysis.

## Assessment instrument decision

Retain the existing 24-item form as an explicitly exploratory prototype during this implementation. Its six four-item groups are inexpensive to complete, but there is no evidence here for local reliability, construct validity, test–retest stability, measurement invariance or counselling effectiveness. Do not call it the O*NET Interest Profiler or a validated Holland test.

An established replacement candidate is the [O*NET Interest Profiler](https://www.onetcenter.org/IP.html). Licensing must follow the [official tools licence](https://www.onetcenter.org/license_tools.html): modifications, including translation, have different conditions from verbatim redistribution. No replacement instrument was silently copied into this release.

Before deployment in a research study:

1. Ask bilingual educators and learners to review each item for comprehension, cultural relevance, ambiguous activity exposure and translation meaning.
2. Conduct cognitive interviews: ask learners to explain what each question means and how they chose a response. Track wording revisions and new instrument versions.
3. Pilot administration without using scores for placement or exclusion.
4. Predefine psychometric analyses with a qualified supervisor. Report dimension-level internal consistency with uncertainty if sample size and item structure support it; four items per scale and ordinal responses limit interpretation.
5. If using a repeated assessment, define a justified interval and account for learning, mood and genuine changing interests. Comparing old/new scores alone does not prove reliability.
6. Assess language equivalence and subgroup behaviour only with adequate, ethically collected samples. A successful English study cannot validate a Sesotho translation automatically.
7. Freeze the instrument version for the main evaluation. Historical comparisons must show version differences.

## Chapter 5 study design

Use staged evaluation: deterministic software verification, expert review, formative learner usability, then a limited school pilot approved by the institution. Choose the sample size and school recruitment plan with the academic supervisor before collection; do not invent an achieved sample size or imply statistical representativeness.

Obtain institutional ethics clearance, school permission, learner assent and any required guardian consent before involving minors. Participation must be voluntary, unrelated to grades or access to guidance, with a plain-language withdrawal process. Use pseudonymous study IDs, restrict the identity key separately and collect only data needed for the research questions. Never publish individual grades, counselling notes, email addresses or small-school identifiable combinations.

A teacher's product access consent is not consent to participate in research. Record the two separately. Learners may decline or withdraw research participation without losing ordinary service access.

### Pre-study needs instrument (original draft; not validated)

Ask in English or reviewed Sesotho; provide “prefer not to answer” and allow skipping.

1. What career or study decision are you currently trying to make?
2. Where do you currently obtain career guidance?
3. Which information is hardest to find: subjects, programmes, entry requirements, funding, practical preparation, or another area?
4. How confident are you in finding an official source? Rate 1–5.
5. How do interests, school results, family advice, costs, location and available subjects influence your choices? Rate each influence 1–5; do not force a single reason.
6. Which device and connection would you normally use? Avoid collecting device identifiers.
7. What language would help you understand guidance best?
8. What would make a guidance system useful or untrustworthy to you?

Analyse response distributions and qualitatively coded themes. Explain sampling limits. Do not infer that a self-reported influence caused a career choice.

### Moderated learner tasks

Use a separate evaluation database and a common test profile for the technical task sequence; collect real profile information only where specifically consented and necessary.

| Task | Success criterion | Observe |
| --- | --- | --- |
| Register and sign in | Account created; clear recoverable validation; learner understands privacy notice | Completion, errors, time, assistance |
| Enter profile and assessment | Inputs persist; unanswered items identified; all six dimensions interpreted | Confusing wording and mobile interaction |
| Explain a recommendation | Learner distinguishes interest, preparation and admission | Teach-back response |
| Recalculate after a profile change | Existing assessment reused; earlier recommendation remains accessible | Task success and change explanation |
| Check a programme | Learner locates official source and identifies missing or manual conditions | False confidence / misunderstanding |
| What-if Mathematics change | Scenario result changes appropriately; actual profile unchanged | Persistence check and user comprehension |
| Compare careers | Up to three alternatives compared with unknowns visible | Decision support and information overload |
| Save and revise a plan | Milestone added/completed; export contains chosen plan | Completion and output readability |
| Check funding | Closed/unknown opportunities are understood correctly | Missed status / misleading deadline |
| Change language and use keyboard | Navigation and key workflows remain usable | Translation defects and focus issues |

Counterbalance task order where learning effects could bias a comparison. Record assistance consistently: unassisted success, assisted success, partial success or failure. Stop tasks if a learner becomes uncomfortable.

### Post-task feedback (original draft; not a standardised scale)

Use 1 (strongly disagree) to 5 (strongly agree), plus “not applicable”:

- I could find the next action without help.
- The wording was easy to understand.
- I understand why the suggested careers appeared.
- I understand which information is missing or unverified.
- I can explain why a career match is different from admission eligibility.
- The suggestions included options worth exploring.
- The study and funding links helped me identify a next step.
- I felt able to disagree with or change the suggestions.
- I understood who could access my information.
- I would use this tool together with a teacher or counsellor.

Open prompts: “What was confusing?”, “Which suggestion was useful or unsuitable, and why?”, “What important option was missing?”, and “What would you change?”. Report item distributions and comments; do not present an unvalidated combined “usability score” as a recognised metric.

### Recommendation relevance review

Have consenting learners and qualified counsellors independently rate each of the top five suggestions on a predefined ordinal relevance scale, with “insufficient information” available. Counsellors must see the same permitted profile snapshot and explanation. Include a few alternative careers to reduce anchoring to rank one.

Report coverage (runs producing suggestions), usefulness per rank, judged relevant proportion at k, diversity of suggested interest categories, explanation comprehension, and disagreement between raters. If nDCG or agreement coefficients are calculated, define relevance labels, ties, denominator, exclusions and uncertainty. These are perceived relevance metrics, not predictive career-success accuracy.

Compare the configured multi-factor matcher with an interest-only baseline on the same profiles. Freeze data, candidate catalogue and assessment version. Analyse whether adding academic/aspiration inputs improves usefulness or unfairly narrows exploration; do not tune and evaluate on the same small sample without disclosure.

### Functionality and security verification matrix

| Area | Required cases | Evidence |
| --- | --- | --- |
| Assessment | Missing answer, invalid value, repeated dimension, tied/flat profile, retake | API tests and browser workflow |
| Recommendations | No marks, partial marks, empty interests where permitted, aspiration overlap, changed profile | Deterministic tests and preserved run IDs |
| Admissions | Met/unmet named subject, missing subject, unknown grade system, duplicate subject aliases, partial/manual source | Rule assertions and source comparison |
| Simulation | Current vs changed grade; added subject; unchanged saved profile | API response plus before/after database check |
| Funding | Unknown dates, future opening, expired closing, explicit closure, filters | Date-controlled tests |
| Ownership | Learner B accesses learner A object; teacher wrong school; revoked consent; stale role session | HTTP/DB tests, denied status and no leaked content |
| Recovery | Existing/non-existing email, expired/reused token, rate limit, mail unavailable | Equivalent public responses; secret-free logs |
| Content | Draft/unverified content, invalid links, contradictory rule, publication audit | Moderation workflow and DB evidence |
| Migration | Existing rows preserved, repeated migration, fresh installation | Before/after counts and SQL command output |
| Deletion/export | Own data only, dependent records handled, audit/privacy expectations | Separate synthetic account exercise |
| Offline | Public reference available; learner/auth/API data absent from cache | Browser cache inspection and logout test |

Use [OWASP ASVS](https://asvs.owasp.org/) as a review framework; record the version and the controls actually assessed. This protocol is not a certification or completed penetration test.

### Accessibility and low-data protocol

Target [WCAG 2.2 AA](https://www.w3.org/TR/WCAG22/) with manual and automated checks: keyboard-only completion, visible/unobscured focus, meaningful labels/errors, heading structure, text alternatives for charts, screen-reader announcements, reflow at narrow width/zoom, colour contrast and sufficient pointer targets. Test with a screen reader and at least one physical mobile device; automated checks alone do not establish conformance.

Record browser/device versions, viewport and zoom, language, network throttling profile, first/repeat page bytes, request counts, and median/p95 response times from repeated measurements. Test slow and interrupted connections, disabled JavaScript, absent Flask and expired session. Do not report measurements without saving the raw test outputs.

## Analysis and reporting template

For each research question report: method, consented sample actually achieved, missing responses, metric definition, observed result with uncertainty, representative de-identified qualitative evidence, limitations and interpretation. Maintain a defect log with reproduction steps, severity, fix and retest.

Empty results table to fill after the study:

| Measure | Planned method | Actual n | Observed result | Evidence location |
| --- | --- | --- | --- | --- |
| Task success | Unassisted/assisted/failure by task | Not collected | Not measured | Pending |
| Recommendation usefulness | Per-rank ordinal ratings | Not collected | Not measured | Pending |
| Source comprehension | Teach-back coding | Not collected | Not measured | Pending |
| Accessibility | Manual + automated checks | Not completed | Not established | Pending |
| Psychometric properties | Approved pilot analysis | Not collected | Not established | Pending |
| School adoption outcomes | Later longitudinal study | Not collected | Not measured | Out of current scope |

Research exports should be pseudonymised, aggregated where possible and protected outside the web root. Predefine retention/deletion dates in the approved protocol, including backups. Publish only sufficiently large groups and redact free-text identifiers. The final thesis must clearly separate executable software evidence, expert judgement, learner feedback and untested future work.

