# Career Compass — Lesotho scoring and evidence update (8 November 2026)

This is an incremental update for the existing Career Compass project. It
includes the revised `model.py` and `app.py` supplied later in the conversation.
**Do not replace** your database or frontend files with the older deployment
archive. Back up your currently modified Python files before copying.

## Install

Copy these files into your running project at `C:\xampp\htdocs\Career_Compass\ai_engine\`:

| Provided file | Destination |
| --- | --- |
| `programmes.py` | `ai_engine/programmes.py` (back up your existing file first) |
| `model.py` | `ai_engine/model.py` (back up your existing file first) |
| `app.py` | `ai_engine/app.py` (back up your existing file first) |
| `labour_evidence.py` | `ai_engine/labour_evidence.py` |
| `data/programmes/verified_lesotho.json` | `ai_engine/data/programmes/verified_lesotho.json` |
| `data/labour/lesotho_labour_evidence.json` | `ai_engine/data/labour/lesotho_labour_evidence.json` |
| `test_lesotho_evidence.py` | `ai_engine/test_lesotho_evidence.py` (for local tests) |
| `test_model_integration.py` | `ai_engine/test_model_integration.py` (for local tests) |

The update retains the legacy `PROGRAMMES`, `INSTITUTIONS`, and `SCHOLARSHIPS`
imports relied on by `chatbot.py`, preserves the existing recommendation JSON
fields, and extends the existing `programmes_for_career` function signature.
The model now uses `local_opportunity_evidence` as its single labour-data gate.

## Run tests first

From your PowerShell terminal:

```powershell
cd C:\xampp\htdocs\Career_Compass\ai_engine
python -m unittest -v test_lesotho_evidence.py test_model_integration.py
python -c "from programmes import PROGRAMMES, INSTITUTIONS, SCHOLARSHIPS; print('Catalogue:', len(PROGRAMMES)); print('Institutions:', len(INSTITUTIONS)); print('Scholarships:', len(SCHOLARSHIPS))"
python -c "from model import CAREERS, SCORE_VERSION; print('Careers:', len(CAREERS), 'Version:', SCORE_VERSION)"
```

You should see **17 tests pass** with the supplied current catalogue, and
`SCORE_VERSION` should be `ls-v1.1`. Then restart the Flask API and open its
`/health` endpoint: `verified_programmes` should be nonzero and
`occupation_labour_records` may correctly be zero. The endpoint may require
your configured `X-Internal-Token` header. Submit a fresh assessment to
generate new recommendation snapshots. Historic results remain unchanged.

**Important:** Extract the ZIP into the `ai_engine` directory, not into
`ai_engine/data`. The intended path is `ai_engine/test_model_integration.py`,
not `ai_engine/data/test_model_integration.py`, and the curated programme data
must be at `ai_engine/data/programmes/verified_lesotho.json`.

## What is verified

The scoped data includes these four Lerotholi Polytechnic programmes:

- Bachelor of Architecture — CHE accreditation period to June 2028.
- Bachelor of Engineering Technology in Mechanical Engineering — to June 2028.
- Diploma in Civil Engineering — to June 2030.
- Diploma in Construction Management — to June 2030.

Programme offerings and available admission requirements were checked against
LP's programme pages. Accreditation periods were checked separately against
CHE. Dates in this dataset are the last day of the published expiry month.

**Do not equate programme accreditation with individual admission eligibility.**
The architecture route requires a creative portfolio and interview. The
civil-diploma entry wording differs between LP's live page and its 2025
prospectus; its eligibility remains unknown until clarified by LP. COSC grade
equivalence is not inferred from LGCSE letter grades.

LP also publishes separate, stricter NMDS sponsorship requirements for its
engineering-technology programmes. These are funding conditions, **not** the
same as basic programme entry, and are not automatically scored here.

## Five-factor scoring and remaining evidence

The weights remain: RIASEC interest 40%, academic compatibility 25%, documented
subject readiness 15%, verified local study pathways 10%, and Lesotho local
occupation demand 10%. A score is displayed only at 75%+ evidence coverage;
incomplete scores are labelled provisional. A civil diploma is an indirect
route for environmental-engineering careers, not proof of professional
registration, and ambiguous admission rules are not converted to grade scores.

The official 2024 Labour Force Survey provides national context, not comparable
engineer-by-engineer demand. The production occupation evidence catalogue is
deliberately empty. The labour adapter therefore returns `None` until an
audited occupation-specific data row with an explicit SOC2018 crosswalk is added.

The current `model.py` now imports this audited labour evidence helper:

```python
from labour_evidence import local_opportunity_score
local_score = local_opportunity_score(career.get('soc_code', ''))
```

The production model uses `local_opportunity_evidence` directly so that score
and metadata are obtained together. Do not set missing labour scores to zero
or replace preliminary alignment with a purported complete match.

## Official sources reviewed on 8 November 2026

- [LP School of Engineering and Technology](https://www.lp.ac.ls/schools-and-programmes/set/)
- [LP School of the Built Environment](https://www.lp.ac.ls/schools-and-programmes/sobe/)
- [CHE accredited Lerotholi Polytechnic programmes](https://www.che.ac.ls/lerotholi/)
- [Bureau of Statistics publications (2024 Labour Force Survey indicators)](https://www.bos.gov.ls/Publications.htm)

CHE's published accreditation dates for several other LP engineering degrees
ended in June 2026; this package **does not** treat them as currently verified.
Institution and accreditation records can change, so re-review before each
new academic-year admission cycle.

The BOS publication directory is the preferred live source because its old
direct PDF URL may return an error. The catalogue retains the original PDF URL
as an archival reference, not as an active endpoint.
