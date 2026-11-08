"""Transparent recommendation tests; runnable with unittest or pytest."""
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ai_engine'))

from model import CAREERS, recommend, _score_breakdown
from riasec import DIMENSIONS, score_responses, top_three_code, validate_responses
from programmes import (PROGRAMMES, grade_to_percent, check_eligibility,
                        programme_verified, local_availability_score)


def answers(values):
    return [{'dimension': dim, 'answer': values[dim], 'question_id': index * 4 + k + 1}
            for index, dim in enumerate(DIMENSIONS) for k in range(4)]


class RecommendationTests(unittest.TestCase):
    def test_reference_data_loads(self):
        self.assertGreater(len(CAREERS), 100)
        self.assertGreater(len(PROGRAMMES), 0)

    def test_24_questions_zero_based_normalisation(self):
        values = dict(zip(DIMENSIONS, [2, 5, 3, 4, 1, 2]))
        scores = score_responses(answers(values))
        self.assertEqual(scores['I'], 100)
        self.assertEqual(scores['E'], 0)
        self.assertEqual(scores['R'], 25)
        self.assertEqual(top_three_code(scores), 'ISA')
        self.assertEqual(score_responses(answers(dict.fromkeys(DIMENSIONS, 1)))['R'], 0)

    def test_incomplete_or_duplicate_answers_rejected(self):
        response = answers(dict.fromkeys(DIMENSIONS, 3))
        with self.assertRaises(ValueError):
            validate_responses(response[:-1])
        response[1]['question_id'] = response[0]['question_id']
        with self.assertRaises(ValueError):
            validate_responses(response)

    def test_missing_local_data_is_not_claimed(self):
        scores = {'R': 40, 'I': 95, 'A': 30, 'S': 65, 'E': 35, 'C': 55}
        matches = recommend(scores, marks={'maths': 83, 'science': 79, 'english': 72},
                            subjects=['Mathematics', 'English'], top_n=5)
        self.assertEqual(len(matches), 5)
        for result in matches:
            self.assertEqual(result['demand_level'], 'unknown')
            self.assertIsNone(result['components']['local_opportunity'])
            self.assertEqual(result['score_version'], 'ls-v1.1')
            self.assertIn(result['score_status'], ('exploratory', 'provisional'))
            self.assertTrue(result['explanation'])
        self.assertEqual(matches, sorted(matches,
            key=lambda value: (value['data_coverage'], value['ranking_score']), reverse=True))

    def test_missing_marks_are_not_zero_or_imputed(self):
        scores = {'R': 20, 'I': 90, 'A': 30, 'S': 40, 'E': 20, 'C': 60}
        # Isolate the missing-marks behaviour from a separately verified
        # education pathway that could legitimately contribute 10% coverage.
        with patch('model.programmes_for_career', return_value=[]):
            match = recommend(scores, marks={}, subjects=[], top_n=1)[0]
        self.assertIsNone(match['components']['academic'])
        self.assertIsNone(match['match_score'])
        self.assertEqual(match['data_coverage'], 0.40)

    def test_complete_formula_and_partial_coverage(self):
        complete = {'interest': 90, 'academic': 80, 'subject_readiness': 100,
                    'pathway': 75, 'local_opportunity': 70}
        self.assertEqual(_score_breakdown(complete), (85.5, 1.0, 85.5, 'complete'))
        complete['local_opportunity'] = None
        final, coverage, ranking, state = _score_breakdown(complete)
        self.assertEqual(coverage, 0.9)
        self.assertEqual(state, 'provisional')
        self.assertEqual(final, ranking)

    def test_grade_midpoints_are_not_entry_eligibility(self):
        self.assertEqual(grade_to_percent('B', 'LGCSE'), 75)
        self.assertIsNone(grade_to_percent('Credit', 'COSC'))

    def test_verified_rule_with_lgcse_aps(self):
        fixture = {
            'verified': True, 'source_url': 'https://example.edu.ls/prospectus',
            'verified_at': '2026-08-01', 'accreditation_status': 'accredited',
            'accreditation_expires': '2028-12-31', 'route_type': 'direct_local',
            'entry_rules': {'LGCSE': {
                'required_grades': {'Mathematics': 'B', 'English': 'C'},
                'aps_max': 26,
                'aps_subjects': ['Mathematics', 'English', 'Biology',
                                 'Sesotho', 'History', 'Geography'],
            }}
        }
        self.assertTrue(programme_verified(fixture))
        learner = {'mathematics': 'B', 'english': 'C', 'biology': 'C',
                   'sesotho': 'B', 'history': 'C', 'geography': 'C'}
        result = check_eligibility(fixture, 'LGCSE', learner)
        self.assertEqual(result['status'], 'meets_published_minimum')
        self.assertEqual(result['aps'], 22)
        learner['mathematics'] = 'D'
        result = check_eligibility(fixture, 'LGCSE', learner)
        self.assertEqual(result['status'], 'does_not_currently_meet')
        self.assertTrue(result['gaps'])
        fixture['verified'] = False
        self.assertFalse(programme_verified(fixture))
        self.assertIsNone(local_availability_score([{'verified': False, 'pathway_score': None}]))


if __name__ == '__main__':
    unittest.main()
