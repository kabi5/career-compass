# Career Compass — Lesotho evidence expansion (8 November 2026)

This is an incremental update for the existing Career Compass project. **Do not replace**
your existing `model.py`, `app.py`, database or frontend files with the older deployment archive.

## Install

Copy these files into your running project at `C:\xampp\htdocs\Career_Compass\ai_engine\`:

| Provided file | Destination |
| --- | --- |
| `programmes.py` | `ai_engine/programmes.py` (back up your existing file first) |
| `labour_evidence.py` | `ai_engine/labour_evidence.py` |
| `data/programmes/verified_lesotho.json` | `ai_engine/data/programmes/verified_lesotho.json` |
| `data/labour/lesotho_labour_evidence.json` | `ai_engine/data/labour/lesotho_labour_evidence.json` |
| `test_lesotho_evidence.py` | `ai_engine/test_lesotho_evidence.py` (for local tests) |

The `programmes.py` update retains the legacy `PROGRAMMES`, `INSTITUTIONS`, and
`SCHOLARSHIPS` imports relied on by `chatbot.py` and preserves the existing
`programmes_for_career` function signature.

## Run tests first

From your PowerShell terminal:

```powershell
cd C:\xampp\htdocs\Career_Compass\ai_engine
python -m unittest -v test_lesotho_evidence.py
python -c "from programmes import PROGRAMMES, INSTITUTIONS, SCHOLARSHIPS; print('Catalogue:', len(PROGRAMMES)); print('Institutions:', len(INSTITUTIONS)); print('Scholarships:', len(SCHOLARSHIPS))"
```

Then restart the Flask API (if running) and submit a fresh assessment to
generate new recommendation snapshots. Historic results remain unchanged.

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

## Labour evidence and remaining work

The official 2024 Labour Force Survey provides national context, not comparable
engineer-by-engineer demand. The production occupation evidence catalogue is
deliberately empty. The labour adapter therefore returns `None` until an
audited occupation-specific data row with an explicit SOC2018 crosswalk is added.

The **latest `model.py` was not provided** with this request. This package
introduces the future integration helper:

```python
from labour_evidence import local_opportunity_score
local_score = local_opportunity_score(career.get('soc_code', ''))
```

That function should be wired into the current five-factor model only after
reviewing its live code and tests. Do not set missing labour scores to zero or
replace preliminary alignment with a purported complete match.

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
