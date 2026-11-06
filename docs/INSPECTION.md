# Initial inspection and change rationale

The original archive was located at `Career_Compass_Modern_Deployment_Package.zip` in Downloads; the workspace already contained its extracted application. Its SHA-256 is `aa329996a01159f9efb0f7e977efa7a16076621191c17eb218735ac9ef50ffd0`. The entry inventory is in `original-archive-inventory.txt`. The original archive was not modified. The workspace had no Git repository, so the original ZIP and generated change manifest provide the baseline.

Inspection covered README, PHP pages/shared bootstrap/layout, JavaScript/CSS, MySQL schema/migration, Flask endpoints, matcher/scorer/chatbot, reference JSON, source-workbook conversion script, supplied tests, Dockerfiles/Compose and environment examples. Raw occupational spreadsheets and processed reference datasets were retained without regeneration; no provenance/version or labelled learner outcomes were inferred from file names.

## Findings addressed

- Assessment submission deleted all recommendations for the learner. It now appends a run linked to an assessment/profile snapshot and retains existing rows.
- MySQL DECIMAL marks could be passed to strict Flask number validation as strings. The profile adapter emits numbers, and empty named maps encode as JSON objects.
- A fixed neutral academic value was used when marks were absent; unavailable components are now excluded and weights renormalised.
- The aspiration bonus was outside the weighted explanation. All components now appear in the score/weight breakdown.
- Programme records were samples without verified admission rules or dates. Official-source records and conservative partial rules are separate from retained samples.
- Profile changes required retaking interests. The recalculation action now reuses the latest assessment.
- Teacher role existed without a scoped portal; an admin aggregate page did not support content management. New interfaces have explicit school/consent controls and independent publication review.
- Recovery/verification and persistent throttling were missing. Tokens, session invalidation, email dependency controls, database throttles and consent/audit records were added.
- Original chat could use external generation and repeat unverified sample assertions. Learner chat now uses approved local retrieval and linked sources; external learner generation is unavailable.
- The recommendation education field was limited to 150 characters. An executed integration test found longer real reference labels; migration 003 preserves them as TEXT.
- Browser checking found narrow-width header overflow. The 320-pixel layout was corrected and checked again.

## Preservation decisions

The PHP/MySQL/Flask architecture, primary learner tables, original reference files and branded visual design are retained. New tables/fields are additive. Seed import never overwrites existing operator edits. Historical legacy recommendations have no invented assessment/profile linkage. No user database was started, reset or migrated for QA: separate databases ran under a temporary MariaDB data directory on port 3307. Synthetic test accounts are not packaged.

The 24-item activity remains explicitly exploratory while the research protocol proposes comprehension, translation and instrument-validation work. Suitable outcome-labelled data was not present, so no trained predictor, measured model accuracy or employment-demand claim was introduced. Current source gaps remain visible rather than fabricated.
