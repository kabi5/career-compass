# Features and remaining limitations

The requested core workflows are implemented in the source code, with the following boundaries.

| Area | Implemented | Remaining limitation |
|---|---|---|
| Recommendations | Separate components, configurable weights, missing-data exclusion, explanations, alternatives, profile reruns, versioned history and feedback | RIASEC/subject/skill links are exploratory heuristics. No labelled outcome dataset, trained predictor or measured relevance study |
| Assessment | 24 complete-response items, labels, keyboard interaction, progress, history, interest chart/table and draft Sesotho | Existing locally written instrument retained; no reliability/validity claim. Cross-language equivalence needs review |
| Institutions/programmes | 14 institution references, 13 source-backed programme records, 13 preserved legacy samples, source dates/scope and reviewer workflow | Not an exhaustive national directory. No current accreditation certification. Duration/modes/publication years left unknown where unsupported |
| Eligibility | Machine-readable subject/grade/count/manual checks, satisfied/failed/unknown explanations and source links | No bundled complete verified admission rule set. Published minima are checkable; overall eligibility may remain undetermined |
| Simulator | Baseline/scenario comparison and changed subjects without writes | Does not predict aptitude, labour-market change, admission or qualification equivalence |
| Plans/progress | Save options, edit roadmaps, personal milestones, completion tracking, PDF and personal JSON | PDF is a simple text report using standard fonts, not a tagged accessible document or advanced designer template |
| Career comparison | Up to three mapped careers, component breakdown, subjects, education reference, skills/activity prompts, pathways | Detailed local occupational tasks and verified current labour-market statistics are unavailable; reference skills are labelled heuristic |
| Funding | NMDS process, Botho enquiry and closed Capernaum school support reference; filters and saved options | No fabricated calls/deadlines/amounts. Two are enquiries with current availability unconfirmed; one provider explicitly says closed |
| Assistant | Local source-linked retrieval, approved DB catalogue per request, explicit unknowns | English retrieval; no external generative chat enabled. Arbitrary open-ended questions may receive an honest fallback |
| Teachers | Confirmed-school boundary, explicit revocable sharing, notes/actions, banded/suppressed trends, aggregate CSV | Guardian/school consent and professional safeguarding procedures must be established by the operator; no independent identity verification service |
| Administration | Statistics, schools/users, JSON drafts, independent review/publication, audit/configuration health, role CLI | JSON editor is intentionally structured, not a rich CMS. Career supplements do not silently modify occupational interest vectors. No destructive bulk delete |
| Localization | Navigation, profile labels, registration labels, assessment instructions/answers/questions, interpretation notice, maintained dictionaries | Draft Sesotho needs professional review. Detailed explanations, most directory text, some errors and educator/admin copy use English fallback |
| Accessibility/offline | Responsive grids, labels, keyboard focus, score text, reduced motion, public-only offline guide | WCAG 2.2 AA is a target, not certified. Screen-reader/user testing, broader contrast auditing and actual low-bandwidth devices still need evaluation |
| Security | Password hashes, CSRF, prepared statements, fresh roles, auth version, idle/absolute expiry, DB throttles, hashed single-use recovery tokens, no-store, CSP, deletion/export | Requires TLS/proxy, secret management, operator access controls and external security review for production |
| Email | Configurable verification and password-reset workflows | No mail relay/credentials were provided. Delivery was not tested; disabled by default. Never enable mandatory verification before mail works |
| Deployment | Additive migrations, Docker and XAMPP instructions, packaging script, private services | Docker daemon was unavailable during testing; image build/container startup unexecuted. Local functional tests used MariaDB, not the MySQL 8.4 container |
| Research | Chapter 4 implementation, source register, needs/usability/relevance protocol and empty reporting templates | No recruited participants, fabricated findings, model accuracy or claimed research completion |

Before real school deployment: complete the local content, linguistic and safeguarding review; configure and verify mail/TLS/backups/retention; test the exact target infrastructure; conduct consented usability/relevance evaluation and an independent security/accessibility review. These are operational and evidence gaps, not hidden claims of completed validation.
