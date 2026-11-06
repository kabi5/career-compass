# Verified source register

Reference date: **27 October 2026 (Africa/Johannesburg)**. This is a curated source snapshot, not a live institutional feed. Public pages may change. Publication years are null when a source does not state one; the date of retrieval must never be substituted for publication year.

## Meaning of verification

The three JSON files in `ai_engine/data/directory/` contain 14 institutions, 13 programme records and three funding records. `verification_status: verified` means that the cited official source supports the limited facts identified by `verification_scope`. It does **not** establish current programme accreditation, complete entry requirements, an open intake, scholarship availability or admission. All programme accreditation fields remain `not_assessed`: a named programme on a CHE profile is not a dated accreditation decision.

Unverified legacy samples in `ai_engine/data/programmes/programmes.json` remain identifiable as unverified; none was silently promoted. Current curated records use new stable IDs. Do not merge them solely on similar titles: a broad sample such as “Engineering (Civil / Electrical / Mechanical)” is not a single verified qualification.

## Institutions and programmes

| Official source | Supported directory content | Limits |
| --- | --- | --- |
| [CHE institution directory](https://www.che.ac.ls/institutions/) | Names of 14 public/private institutions | No assertion of programme accreditation validity or admission availability |
| [NUL Computer Science programme](https://nul.ls/course/bachelor-of-science-in-computer-science-single-major/) | Programme identity, four-year direct-entry duration, named grade prerequisites | Subject-total wording is inconsistent. Only named requirements are machine checked; APS, complete counts and selection remain unresolved |
| [Lerotholi programmes](https://www.lp.ac.ls/schools-and-programmes/) | Four selected engineering/craft qualifications and their published durations | Current study modes and full rules are not supplied for every programme |
| [Lerotholi SET](https://www.lp.ac.ls/schools-and-programmes/set/) | Computer Engineering first-year Mathematics C, Physical Science C, English D | Completed LQF Level 4 certificate/equivalence requires manual verification. Sponsorship conditions are separate |
| [Botho Lesotho Computing](https://www.bothouniversity.com/course/lesotho/BachelorofScienceinComputing) | Current computing title, campus and four-year duration | Complete entry thresholds not encoded |
| [CHE LCE profile](https://www.che.ac.ls/lesotho-college-of-education-profile/) | Secondary Education diploma and Early Childhood Education certificate identities | Profile is undated; current intake, duration and rules need confirmation |
| [CHE Limkokwing profile](https://www.che.ac.ls/limkokwing-university-of-creative-technology-profile/) | IT degree and Graphic Design associate degree identities | Current accreditation term, intake and requirements not established |
| [CHE LAC profile](https://www.che.ac.ls/lesotho-agricultural-college-profile/) | Forestry/Resource Management and Agricultural Engineering/Land/Water Management diploma identities | Confirm current accreditation directly with CHE; profile alone is insufficient |
| [CAS programmes](https://cas.ac.ls/programs/) | Financial Services bachelor's programme; catalogue item dated April 2025 | Duration and full admission rules not established |

For accreditation research use [CHE's qualifications portal](https://lqmis.che.ac.ls/), exact qualification title, qualification level, campus, decision date and expiry. Record these separately from the institution's existence. Do not equate “institution listed”, “programme advertised”, “recognised historically” and “currently accredited”.

## Funding

| Official source | What was verified | Unknowns retained |
| --- | --- | --- |
| [Government NMDS service](https://www.gov.ls/eservice/application-for-student-loan-bursaries/) | Loan-bursary application process and route-specific documentation | Current intake dates, awards, full individual eligibility and funding amount |
| [Botho FAQ](https://www.bothouniversity.com/faq) | University describes financial-aid categories | Lesotho-specific current awards and conditions; record is an enquiry route |
| [Higherlife Capernaum](https://moshoeshoe1.higherlifefoundation.com/capernaum) | Lesotho school support and document categories | No dated call; provider explicitly said applications closed when checked |

The application does not collect scholarship identity documents, bank details or evidence of bereavement. The directory links to providers. School guidance staff should help learners understand the provider's own application and privacy terms.

No tuition figures, scholarship amounts or application deadlines were added. Null dates must display “Not published / confirm with provider”, never “open”. Provider-declared closure takes precedence over absent dates.

## Incomplete and unavailable material

- NUL's [2026–2027 prospectus landing page](https://nul.ls/download/prospectus-2026-2027-final-version/) was found but requests encountered rate limiting. A third-party mirror was not used to assert authoritative rules.
- The indexed official [Botho 2026 Lesotho prospectus](https://lesotho.bothouniversity.com/wp-content/uploads/sites/4/2026/02/Botho-University-2026-Lesotho-Campus-Prospectus.pdf) redirected to an unavailable route. Its indexed “Credit/Pass” wording was not converted into guessed letter thresholds.
- LCE's [general application information](https://www.lce.ac.ls/online_app/images/Docs/General%20Information%20for%20Application%20in%20to%20LCE%20programmes.pdf) contains a 2023 application deadline. That historical deadline was not reused for 2026.
- CHE pages contain older enrolment figures; these were not treated as current totals.

## Admission-rule provenance

`admission_rules` describes one explicitly identified route and grading system. Supported facts and unresolved conditions remain separate. LGCSE letters are not percentages and COSC, AS, A-level, IB or foreign certificates require explicit equivalence rules.

The curated starting catalogue intentionally has **no programme with a fully verified, automatically decidable rule set**. Learners can see satisfied/unsatisfied published subject prerequisites and simulate improvements, but overall eligibility remains undetermined where manual or missing conditions remain. This is a real data-coverage limitation, not a failed calculation.

Before setting `complete: true`, a content reviewer must check all relevant general and programme-specific conditions, accepted subject groups, duplicate-subject rules, grade scale, effective intake, qualification completion, alternative entry routes and selection rules. If any non-grade condition cannot be checked, keep a `manual` rule. “Published requirements met” still cannot mean accepted.

## Assessment and career-reference licensing

The current 24 questions are the project's own exploratory instrument; their resemblance to RIASEC does not establish reliability or validity. They are retained for the demonstration with explicit limitations and versioned history. No O*NET Interest Profiler items were copied during this upgrade.

The [O*NET Interest Profiler overview](https://www.onetcenter.org/IP.html) describes established 30-item and 60-item versions. The [tools licence](https://www.onetcenter.org/license_tools.html) distinguishes verbatim redistribution under CC BY-ND 4.0 from modifications under its Developer License, which requires validation. A Sesotho translation is a modification: do not assume the unmodified instrument's evidence transfers.

The existing processing code identifies its raw occupational interest and education files as O*NET-derived. The original release number is not documented in this repository and must be recovered before a provenance-complete public research release. The [O*NET database licence](https://www.onetcenter.org/license_db.html) permits adaptation with attribution, a licence link and identification of changes, and asks for the correct dataset version. Do not label the legacy files “O*NET 31.0” merely because that is the current licence page.

Suggested attribution once the original version is confirmed: “Includes adapted occupational reference data from the O*NET [verified version] Database, U.S. Department of Labor, Employment and Training Administration, under CC BY 4.0. Career Compass converts source workbooks into JSON and uses interest vectors for exploratory similarity matching. These modifications have not been approved, endorsed or tested by USDOL/ETA.” Replace the bracketed version only with evidence.

## Content maintenance procedure

1. Open the institution/provider/CHE source; verify the campus and qualification.
2. Record source title, exact URL, stated publication year, retrieval date, reviewer and verification scope.
3. Store unpublished fields as null or empty arrays. Keep uncertainties visible.
4. Have a second qualified reviewer compare machine rules with the full source before enabling definitive rule coverage.
5. Recheck before each intake; immediately withdraw a contradicted rule. Preserve the audit/history record.
6. Never change a historical recommendation or assessment when source content changes. Generate a new version.

