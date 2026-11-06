"""Unit checks that work without running MySQL or Flask."""
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ai_engine'))
from model import CAREERS, recommend
from riasec import score_responses, top_three_code
from programmes import PROGRAMMES


def test_reference_data_loads():
    assert len(CAREERS) > 100
    assert len(PROGRAMMES) > 0


def test_assessment_score_and_code():
    responses = [{'dimension': d, 'answer': v} for d, v in
                 [('R', 2), ('I', 5), ('A', 3), ('S', 4), ('E', 1), ('C', 2)]]
    result = score_responses(responses)
    assert result['I'] == 100.0
    assert top_three_code(result) == 'ISA'


def test_recommendations_have_explanations_and_no_demand_claims():
    scores = {'R': 40, 'I': 95, 'A': 30, 'S': 65, 'E': 35, 'C': 55}
    result = recommend(scores, marks={'maths': 83, 'science': 79, 'english': 72},
                       subjects=['Mathematics', 'English'], top_n=5)
    assert len(result) == 5
    assert all(0 <= x['match_score'] <= 100 for x in result)
    assert all(x['explanation'] for x in result)
    assert all(x['demand_level'] == 'unknown' for x in result)
    assert result == sorted(result, key=lambda x: x['match_score'], reverse=True)


def test_missing_marks_are_excluded_not_fabricated():
    scores = {'R': 20, 'I': 90, 'A': 30, 'S': 40, 'E': 20, 'C': 60}
    result = recommend(scores, marks={}, subjects=[], top_n=2)
    assert len(result) == 2
    assert result[0]['academic_preparation'] is None
    assert result[0]['effective_weights']['academic'] == 0
    assert result[0]['match_score'] == result[0]['interest_alignment']
    assert 'excluded' in result[0]['explanation']
