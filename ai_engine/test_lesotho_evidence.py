"""Offline regression tests for documented Lesotho admission/pathway evidence.

Run from ai_engine: python -m unittest -v test_lesotho_evidence.py
"""
import unittest
from datetime import date

from programmes import (
    PROGRAMMES, check_eligibility, programme_verified, programmes_for_career,
    local_availability_score,
)
from labour_evidence import load_evidence, local_opportunity_evidence, local_opportunity_score


def record(record_id):
    return next(p for p in PROGRAMMES if p.get("id") == record_id)


class ProgrammeTests(unittest.TestCase):
    def test_current_che_programmes_are_reviewed(self):
        self.assertTrue(programme_verified(record("LP-ARCH-BARCH-2025"), date(2026, 11, 8)))
        self.assertTrue(programme_verified(record("LP-MECH-BENGTECH-2025"), date(2026, 11, 8)))
        self.assertTrue(programme_verified(record("LP-CIVIL-DIP-2025"), date(2026, 11, 8)))
        self.assertTrue(programme_verified(record("LP-CONSTRUCTION-DIP-2025"), date(2026, 11, 8)))

    def test_expired_accreditation_is_not_verified(self):
        self.assertFalse(programme_verified(record("LP-MECH-BENGTECH-2025"), date(2029, 1, 1)))

    def test_mechanical_lgcse_pass_and_fail(self):
        p = record("LP-MECH-BENGTECH-2025")
        passed = check_eligibility(p, "LGCSE", {"Maths": "B", "Physical Sciences": "C", "English": "D"})
        failed = check_eligibility(p, "LGCSE", {"Maths": "D", "Physical Sciences": "C", "English": "D"})
        self.assertEqual(passed["status"], "meets_published_minimum")
        self.assertEqual(passed["readiness_score"], 100)
        self.assertEqual(failed["status"], "does_not_currently_meet")

    def test_unsupported_numeric_grade_needs_manual_review(self):
        p = record("LP-MECH-BENGTECH-2025")
        r = check_eligibility(p, "LGCSE", {"mathematics": 85, "physical science": "C", "english": "D"})
        self.assertEqual(r["status"], "insufficient_information")

    def test_architecture_portfolio_interview_are_retained(self):
        p = record("LP-ARCH-BARCH-2025")
        r = check_eligibility(p, "LGCSE", {"mathematics": "C", "physical science": "B", "english": "D"})
        self.assertEqual(r["status"], "additional_review_required")
        self.assertEqual(len(r["manual_requirements"]), 2)

    def test_construction_six_subjects(self):
        p = record("LP-CONSTRUCTION-DIP-2025")
        grades = {"mathematics": "C", "physical science": "C", "english": "D",
                  "biology": "E", "geography": "D", "accounting": "C"}
        r = check_eligibility(p, "LGCSE", grades)
        self.assertEqual(r["status"], "meets_published_minimum")
        self.assertEqual(r["readiness_score"], 100)
        del grades["accounting"]
        self.assertEqual(check_eligibility(p, "LGCSE", grades)["status"], "insufficient_information")

    def test_civil_admission_ambiguity_not_guessed(self):
        p = record("LP-CIVIL-DIP-2025")
        self.assertEqual(check_eligibility(p, "LGCSE", {"mathematics": "A", "english": "C"})["status"],
                         "insufficient_information")

    def test_related_engineering_bridge_available(self):
        matches = programmes_for_career("Environmental Engineers", ["Mathematics"], limit=10)
        civil = next(p for p in matches if p["id"] == "LP-CIVIL-DIP-2025")
        self.assertTrue(civil["verified"])
        self.assertEqual(civil["pathway_score"], 50.0)
        self.assertEqual(local_availability_score([civil]), 50.0)

    def test_legacy_positional_career_api_is_preserved(self):
        matches = programmes_for_career(
            "Architects, Except Landscape and Naval", [], 4,
            "17-1011.00", "LGCSE",
            {"mathematics": "C", "physical science": "B", "english": "D"},
        )
        self.assertEqual(matches[0]["id"], "LP-ARCH-BARCH-2025")
        self.assertEqual(matches[0]["eligibility_status"], "additional_review_required")


class LabourTests(unittest.TestCase):
    def test_national_unemployment_never_becomes_career_demand(self):
        data = load_evidence()
        self.assertTrue(data["national_context"])
        self.assertIsNone(local_opportunity_score("17-2081.00"))

    def test_source_mapped_occupation_record_is_required(self):
        fixture = {"occupation_specific_entries": [
            {"soc_code": "17-2081.00", "occupation_classification": "SOC2018",
             "verified": True, "reviewed_opportunity_index": 68.0,
             "source_url": "https://example.org/audit", "methodology": "Survey-to-index audit v1",
             "source_occupation_mapping": "Explicit SOC mapping", "reviewed_at": "2026-11-01"}
        ]}
        self.assertEqual(local_opportunity_evidence("17-2081.00", today=date(2026, 11, 8), catalogue=fixture)["score"], 68.0)
        self.assertIsNone(local_opportunity_score("17-2081.00", today=date(2029, 1, 1), catalogue=fixture))
        self.assertIsNone(local_opportunity_score("17-9999.00", today=date(2026, 11, 8), catalogue=fixture))


if __name__ == "__main__":
    unittest.main()
