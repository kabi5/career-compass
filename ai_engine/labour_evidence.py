"""Conservative Lesotho labour-market evidence adapter.

This module NEVER treats country-level unemployment as career-specific demand.
The production evidence list deliberately contains no occupation-score rows.
Only explicit, current, methodology-backed SOC2018 mappings may become scores.
"""
from datetime import date
import json
from pathlib import Path

DEFAULT_PATH = Path(__file__).parent / "data" / "labour" / "lesotho_labour_evidence.json"


def load_evidence(path=DEFAULT_PATH):
    try:
        with open(path, encoding="utf-8") as stream:
            data = json.load(stream)
        return data if isinstance(data, dict) else {}
    except (OSError, ValueError):
        return {}


def local_opportunity_evidence(soc_code, *, today=None, catalogue=None):
    """Return a score only for an audited, occupation-specific, usable row.

    A score here is an editorial index defined by the source's methodology;
    it must not be invented from broad GDP or national unemployment figures.
    """
    data = load_evidence() if catalogue is None else catalogue
    records = data.get("occupation_specific_entries", []) if isinstance(data, dict) else []
    current = today or date.today()
    result = {"score": None, "demand_level": "unknown",
              "status": "no_verified_occupation_evidence", "evidence": [],
              "national_context": data.get("national_context", []) if isinstance(data, dict) else []}
    for row in records if isinstance(records, list) else []:
        if not isinstance(row, dict) or row.get("soc_code") != soc_code:
            continue
        if row.get("occupation_classification") != "SOC2018" or row.get("verified") is not True:
            continue
        url = row.get("source_url", "")
        if not isinstance(url, str) or not url.startswith("https://"):
            continue
        score = row.get("reviewed_opportunity_index")
        if isinstance(score, bool) or not isinstance(score, (int, float)) or not 0 <= score <= 100:
            continue
        if not row.get("methodology") or not row.get("source_occupation_mapping"):
            continue
        try:
            checked = date.fromisoformat(row["reviewed_at"])
            if checked > current or (current - checked).days > 730:
                continue
            valid_until = row.get("valid_until")
            if valid_until and date.fromisoformat(valid_until) < current:
                continue
        except (ValueError, KeyError, TypeError):
            continue
        result.update({"score": float(score), "demand_level": row.get("demand_level", "unknown"),
                       "status": "verified_occupation_evidence", "evidence": [row]})
        return result
    return result


def local_opportunity_score(soc_code, *, today=None, catalogue=None):
    """Compatibility helper for the five-factor scoring model."""
    return local_opportunity_evidence(soc_code, today=today, catalogue=catalogue)["score"]
