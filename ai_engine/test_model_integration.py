"""Regression tests for score v1.1 using reviewed Lesotho pathway records."""
import unittest
from unittest.mock import patch

import model

SCORES = {"R": 81, "I": 81, "A": 38, "S": 31, "E": 56, "C": 63}
LGCSE_GRADES = [
    {"subject": "Mathematics", "grade": "C"},
    {"subject": "Physical Sciences", "grade": "C"},
    {"subject": "English", "grade": "D"},
]


def career_by_title(title):
    return next(c for c in model.CAREERS if c["title"] == title)


class ModelIntegrationTests(unittest.TestCase):
    def one_career(self, title, **kwargs):
        with patch.object(model, "CAREERS", [career_by_title(title)]):
            matches = model.recommend(SCORES, top_n=1, **kwargs)
        self.assertEqual(len(matches), 1)
        return matches[0]

    def test_bachelor_architecture_has_manual_review_and_90_percent_coverage(self):
        found = self.one_career(
            "Architects, Except Landscape and Naval",
            qualification_type="LGCSE", subject_results=LGCSE_GRADES,
        )
        self.assertEqual(found["eligibility_status"], "additional_review_required")
        self.assertEqual(found["components"]["subject_readiness"], 100.0)
        self.assertEqual(found["components"]["pathway"], 100.0)
        self.assertIsNone(found["components"]["local_opportunity"])
        self.assertEqual(found["data_coverage"], 0.90)
        self.assertEqual(found["score_status"], "provisional")
        self.assertIsNotNone(found["match_score"])
        self.assertIn("portfolio or interview", found["explanation"])

    def test_ambiguous_civil_diploma_has_pathway_but_no_readiness(self):
        found = self.one_career(
            "Environmental Engineers",
            qualification_type="LGCSE", subject_results=LGCSE_GRADES,
        )
        self.assertEqual(found["components"]["pathway"], 50.0)
        self.assertIsNone(found["components"]["subject_readiness"])
        self.assertEqual(found["eligibility_status"], "insufficient_information")
        self.assertEqual(found["data_coverage"], 0.75)
        self.assertIsNotNone(found["match_score"])
        self.assertIn("entry conditions are missing", found["explanation"])

    def test_no_verified_route_keeps_score_exploratory(self):
        with patch("model.programmes_for_career", return_value=[]):
            found = self.one_career(
                "Environmental Engineers",
                qualification_type="LGCSE", subject_results=LGCSE_GRADES,
            )
        self.assertEqual(found["data_coverage"], 0.65)
        self.assertIsNone(found["match_score"])
        self.assertEqual(found["score_status"], "exploratory")

    def test_national_labour_context_does_not_raise_score(self):
        found = self.one_career(
            "Environmental Engineers", qualification_type="LGCSE",
            subject_results=LGCSE_GRADES,
        )
        self.assertEqual(found["demand_level"], "unknown")
        self.assertIsNone(found["components"]["local_opportunity"])
        self.assertEqual(found["evidence"], [])

    def test_scoring_threshold_and_normalization_unchanged(self):
        visible, coverage, rank, status = model._score_breakdown({
            "interest": 100, "academic": 90,
            "subject_readiness": None, "pathway": None,
            "local_opportunity": None,
        })
        self.assertEqual(coverage, 0.65)
        self.assertEqual(rank, 96.15)
        self.assertIsNone(visible)
        self.assertEqual(status, "exploratory")


if __name__ == "__main__":
    unittest.main()
