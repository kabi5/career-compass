"""Regression tests for the upgrade. Fixtures are synthetic rule cases, not programmes or research participants."""
import copy
import math
import sys
from datetime import date
from pathlib import Path

import pytest
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ai_engine'))

import app as service
import model
from chatbot import BOT, CareerBot
from eligibility import check_eligibility, normalise_grades, simulate, scholarship_status, filter_scholarships
from programmes import PROGRAMMES, INSTITUTIONS, SCHOLARSHIPS, canon
from riasec import DIMENSIONS

SCORES = {'R': 40, 'I': 90, 'A': 30, 'S': 60, 'E': 25, 'C': 50}
CAREER = {'title': 'Synthetic research occupation', 'riasec_code': 'I', 'soc_code': 'TEST'}
TODAY = date(2026, 10, 27)


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setattr(service, 'API_TOKEN', 'test-internal-token-with-32-characters')
    service.app.config.update(TESTING=True)
    return service.app.test_client()


@pytest.fixture
def headers():
    return {'X-Internal-Token': 'test-internal-token-with-32-characters'}


def responses():
    return [{'dimension': dimension, 'answer': 4, 'item_id': index * 4 + offset + 1}
            for index, dimension in enumerate(DIMENSIONS) for offset in range(4)]


def programme(rules=None, complete=True):
    # Synthetic software-test fixture. Never seeded into the deployed directory.
    return {'id': 'test-rule-fixture', 'programme': 'Synthetic rule test only',
            'university': 'Test fixture', 'verification_status': 'verified', 'verified': True,
            'source_url': 'https://example.org/test-fixture', 'last_verified': '2026-10-27',
            'admission_rules': {'grading_system': 'LGCSE', 'complete': complete,
                'rules': rules if rules is not None else [
                    {'type': 'subject', 'subject': 'Mathematics', 'minimum_grade': 'C'},
                    {'type': 'subject', 'subject': 'English', 'minimum_grade': 'D'}],
                'notes': []}}


def test_missing_components_do_not_invent_scores():
    result = model.career_match(CAREER, SCORES)
    assert result['academic_preparation'] is None
    assert result['skills_alignment'] is None
    assert result['subject_compatibility'] is None
    assert result['match_score'] == result['interest_alignment']
    assert result['effective_weights']['interest'] == 1
    assert result['effective_weights']['academic'] == 0
    assert result['demand_level'] == 'unknown'


def test_no_profile_comparison_has_unknown_scores():
    result = model.career_match(CAREER)
    assert result['match_score'] is None
    assert result['interest_alignment'] is None
    assert result['academic_preparation'] is None


def test_letter_grades_not_converted_to_percentages():
    result = model.career_match(CAREER, SCORES, subject_grades={'Mathematics': 'A*'})
    assert result['academic_preparation'] is None
    assert result['subject_compatibility'] is not None


def test_configured_weights_have_observable_effect(monkeypatch):
    weights = {name: 0 for name in model.DEFAULT_WEIGHTS}
    weights['academic'] = 1
    monkeypatch.setattr(model, 'WEIGHTS', model.configured_weights(weights))
    result = model.career_match(CAREER, SCORES, marks={'maths': 80, 'Science': 60})
    assert result['academic_preparation'] == 70
    assert result['match_score'] == 70
    assert result['effective_weights']['academic'] == 1


@pytest.mark.parametrize('value', [float('nan'), float('inf'), -1, True, '0.5'])
def test_invalid_weights_fail_closed(value):
    with pytest.raises(ValueError):
        model.configured_weights({'interest': value})


def test_zero_total_or_unknown_weight_rejected():
    with pytest.raises(ValueError):
        model.configured_weights(dict.fromkeys(model.DEFAULT_WEIGHTS, 0))
    with pytest.raises(ValueError):
        model.configured_weights({'salary': .5})


def test_aspirations_skills_preferences_explain_separately():
    result = model.career_match(CAREER, SCORES, aspirations='research occupation',
                               skills=['research'], preferences=['research'])
    assert result['aspiration_alignment'] > 0
    assert result['skills_alignment'] == 100
    assert result['preference_alignment'] == 100
    assert 'heuristic' in result['explanation']
    assert math.isclose(sum(result['effective_weights'].values()), 1, abs_tol=2e-6)


def test_unknown_skill_is_missing_not_a_negative_judgement():
    result = model.career_match(CAREER, SCORES, skills=['a skill absent from the mapping'])
    assert result['skills_alignment'] is None
    assert result['effective_weights']['skills'] == 0


def test_grade_aliases_deduplicate_without_double_counting():
    grades = normalise_grades({'Maths': ' b ', 'Mathematics': 'B', 'English Language': 'D'})
    assert grades == {'mathematics': 'B', 'english': 'D'}
    rules = [{'type': 'count', 'count': 3, 'minimum_grade': 'D'}]
    result = check_eligibility([programme(rules)], {'Maths': 'B', 'Mathematics': 'B', 'English': 'D'})[0]
    assert result['status'] == 'requirements_not_met'


@pytest.mark.parametrize('grades', [
    {'Maths': 'C', 'Mathematics': 'B'}, {'English': 75}, {'Maths': 'H'},
    {'...': 'A'}, {'Maths': True}, {'Science': ['B']}, [], {'': 'A'}])
def test_invalid_and_conflicting_grades_rejected(grades):
    with pytest.raises(ValueError):
        normalise_grades(grades)


def test_grade_alias_does_not_equate_generic_science():
    assert canon('Physical Sciences') == canon('Physical Science')
    assert canon('Science') != canon('Physical Science')


def test_complete_synthetic_rule_met_unmet_and_missing():
    fixture = programme()
    passed = check_eligibility([fixture], {'Maths': 'C', 'English Language': 'D'})[0]
    assert passed['status'] == 'requirements_met'
    assert len(passed['met']) == 2
    assert 'not an offer' in passed['disclaimer']
    failed = check_eligibility([fixture], {'Maths': 'E'})[0]
    assert failed['status'] == 'requirements_not_met'
    assert failed['missing_subjects'] == ['English']
    assert any('Improve Mathematics' in line for line in failed['improvements'])


def test_missing_grade_is_not_a_failed_grade():
    result = check_eligibility([programme()], {'Mathematics': '', 'English': 'A'})[0]
    assert result['status'] == 'undetermined'
    assert not result['not_met']
    assert any('grade missing' in value for value in result['unknown'])


def test_partial_manual_empty_rules_cannot_report_eligibility():
    partial = programme(complete=False)
    manual = programme([{'type': 'subject', 'subject': 'Maths', 'minimum_grade': 'C'},
                        {'type': 'manual', 'description': 'Confirm completed qualification.'}])
    empty = programme([])
    for result in check_eligibility([partial, manual, empty], {'Maths': 'A', 'English': 'A'}):
        assert result['status'] == 'undetermined'
        assert result['unknown']


@pytest.mark.parametrize('change', [
    {'verification_status': 'unverified'}, {'verified': False}, {'source_url': 'javascript:alert(1)'},
    {'source_url': []}, {'last_verified': 'yesterday'}, {'last_verified': None},
])
def test_unverified_or_malformed_source_does_not_enable_checks(change):
    fixture = programme()
    fixture.update(change)
    result = check_eligibility([fixture], {'Maths': 'A', 'English': 'A'})[0]
    assert result['status'] == 'undetermined'
    assert not result['met']


def test_other_grade_system_is_unknown_without_conversion():
    result = check_eligibility([programme()], {'Maths': 'A', 'English': 'A'}, 'COSC')[0]
    assert result['status'] == 'undetermined'
    assert not result['met']


def test_nonexhaustive_science_group_does_not_false_reject():
    rule = {'type': 'any_subject', 'subjects': ['Biology', 'Physical Sciences'],
            'count': 1, 'minimum_grade': 'C', 'exhaustive': False}
    result = check_eligibility([programme([rule], complete=False)], {'Agriculture': 'A'})[0]
    assert result['status'] == 'undetermined'
    assert not result['not_met']
    assert any('other subjects' in item for item in result['unknown'])


def test_count_and_any_subject_thresholds():
    fixture = programme([
        {'type': 'count', 'count': 3, 'minimum_grade': 'C'},
        {'type': 'any_subject', 'subjects': ['Biology', 'Physical Sciences'], 'count': 1, 'minimum_grade': 'B'}])
    result = check_eligibility([fixture], {'Maths': 'A', 'English': 'C', 'Physical Science': 'B'})[0]
    assert result['status'] == 'requirements_met'
    result = check_eligibility([fixture], {'Maths': 'A', 'English': 'C', 'Physical Science': None})[0]
    assert result['status'] == 'undetermined'


def test_simulation_does_not_mutate_grades_or_rules():
    items = [programme()]
    baseline = {'Mathematics': 'E', 'English': 'C'}
    scenario = {'Mathematics': 'B', 'English': 'C'}
    before = copy.deepcopy((items, baseline, scenario))
    result = simulate(items, baseline, scenario)
    assert (items, baseline, scenario) == before
    assert result['baseline'][0]['status'] == 'requirements_not_met'
    assert result['results'][0]['status'] == 'requirements_met'
    assert result['changed_subjects'] == ['mathematics']
    assert result['scenario_saved'] is False


@pytest.mark.parametrize('item,expected', [
    ({}, 'unknown'),
    ({'application_status': 'open'}, 'unknown'),
    ({'application_status': 'closed'}, 'closed'),
    ({'application_status': 'closed', 'opens_on': 'bad'}, 'closed'),
    ({'opens_on': '2026-11-01', 'closes_on': '2026-12-01'}, 'upcoming'),
    ({'opens_on': '2026-01-01', 'closes_on': '2026-10-26'}, 'closed'),
    ({'opens_on': '2026-01-01', 'closes_on': '2026-10-27'}, 'open'),
    ({'opens_on': '2026-10-27', 'closes_on': '2026-11-30'}, 'open'),
    ({'opens_on': '2026-11-01', 'closes_on': '2026-01-01'}, 'unknown'),
    ({'opens_on': 'not-a-date'}, 'unknown'),
])
def test_funding_dates_and_provider_closure(item, expected):
    assert scholarship_status(item, TODAY) == expected


def test_scholarship_filters_are_combined_without_mutation():
    items = [{'title': 'Synthetic field award', 'provider': 'Test', 'fields': ['Computing'],
              'level': 'Degree', 'application_status': 'closed'},
             {'title': 'Synthetic school award', 'provider': 'Test', 'fields': ['Education'],
              'level': 'School'}]
    before = copy.deepcopy(items)
    matches = filter_scholarships(items, query='computing', level='degree', status='closed', today=TODAY)
    assert len(matches) == 1 and matches[0]['current_status'] == 'closed'
    assert items == before
    assert filter_scholarships(items, query='computing', level='School', today=TODAY) == []


def test_directory_integrity_and_no_false_complete_rules():
    institution_ids = {i['id'] for i in INSTITUTIONS}
    curated = [p for p in PROGRAMMES if p.get('verification_status') == 'verified']
    assert len(institution_ids) == len(INSTITUTIONS)
    assert all(p['institution_id'] in institution_ids for p in curated)
    assert all(p['source_url'].startswith('https://') and p['verification_scope'] for p in curated)
    assert all(p['admission_rules']['complete'] is False for p in curated)
    capernaum = next(s for s in SCHOLARSHIPS if s['id'] == 'capernaum-lesotho')
    assert scholarship_status(capernaum, TODAY) == 'closed'


@pytest.mark.parametrize('endpoint,method', [
    ('/health', 'get'), ('/careers', 'get'), ('/programmes', 'get'), ('/scholarships', 'get'),
    ('/score', 'post'), ('/recommend', 'post'), ('/compare', 'post'), ('/eligibility', 'post'),
    ('/simulate', 'post'), ('/chat', 'post'),
])
def test_all_api_routes_require_internal_token_when_configured(client, endpoint, method):
    assert getattr(client, method)(endpoint).status_code == 401
    assert getattr(client, method)(endpoint, headers={'X-Internal-Token': 'wrong'}).status_code == 401


def test_score_requires_exactly24_balanced_items(client, headers):
    valid = responses()
    response = client.post('/score', json={'responses': valid}, headers=headers)
    assert response.status_code == 200
    assert response.json['scores'] == dict.fromkeys(DIMENSIONS, 80.0)
    assert response.json['assessment_version'] == 'cc-exploratory-24-v1'
    assert response.headers['Cache-Control'] == 'no-store'
    assert response.headers['X-Content-Type-Options'] == 'nosniff'
    malformed = [valid[:6], valid[:-1], valid + [valid[0]]]
    wrong_balance = copy.deepcopy(valid)
    wrong_balance[0]['dimension'] = 'I'
    malformed.append(wrong_balance)
    duplicate = copy.deepcopy(valid)
    duplicate[1]['item_id'] = duplicate[0]['item_id']
    malformed.append(duplicate)
    for bad in malformed:
        assert client.post('/score', json={'responses': bad}, headers=headers).status_code == 400


@pytest.mark.parametrize('answer', [0, 6, 2.5, '4', True, None])
def test_invalid_assessment_answer_rejected(client, headers, answer):
    payload = responses()
    payload[0]['answer'] = answer
    assert client.post('/score', json={'responses': payload}, headers=headers).status_code == 400


@pytest.mark.parametrize('patch', [
    {'marks': {'Maths': float('nan')}}, {'marks': {'Maths': float('inf')}},
    {'marks': {'Maths': True}}, {'marks': {'Maths': -1}}, {'marks': {'Maths': 101}},
    {'riasec_scores': {**SCORES, 'I': float('nan')}},
    {'riasec_scores': {**SCORES, 'I': True}}, {'riasec_scores': {'I': 80}},
    {'subjects': False}, {'skills': 'research'}, {'preferences': 0}, {'marks': []},
    {'aspirations': False}, {'subject_grades': []},
    {'marks': {'science': 40, 'Physical Sciences': 60}},
    {'marks': {'Maths': 40, 'Mathematics': 60}},
    {'subject_grades': {'Maths': 'B', 'Mathematics': 'A'}},
    {'top_n': True}, {'top_n': 1.5}, {'top_n': 11},
])
def test_profile_api_validates_shapes_finite_values_aliases(client, headers, patch):
    payload = {'riasec_scores': SCORES, **patch}
    response = client.post('/recommend', json=payload, headers=headers)
    assert response.status_code == 400


def test_recommendations_include_alternatives_and_versions(client, headers):
    response = client.post('/recommend', json={'riasec_scores': SCORES, 'top_n': 3}, headers=headers)
    assert response.status_code == 200
    main, alternatives = response.json['recommendations'], response.json['alternative_suggestions']
    assert len(main) == len(alternatives) == 3
    assert not {c['title'] for c in main} & {c['title'] for c in alternatives}
    assert all(c['academic_preparation'] is None for c in main)
    assert response.json['method_version'] == model.METHOD_VERSION


def test_compare_without_assessment_and_limit(client, headers):
    names = [c['title'] for c in model.CAREERS[:3]]
    response = client.post('/compare', json={'career_titles': names}, headers=headers)
    assert response.status_code == 200 and len(response.json['careers']) == 3
    assert all(c['interest_alignment'] is None for c in response.json['careers'])
    assert client.post('/compare', json={'career_titles': names + ['fourth']}, headers=headers).status_code == 400
    assert client.post('/compare', json={'career_titles': [names[0], names[0]]}, headers=headers).status_code == 400
    unknown = client.post('/compare', json={'career_titles': ['not a catalogue career']}, headers=headers)
    assert unknown.json['unknown_titles'] == ['not a catalogue career']


def test_eligibility_and_simulation_api(client, headers):
    fixture = programme()
    grades = {'Maths': 'A', 'English': 'B'}
    response = client.post('/eligibility', json={'programmes': [fixture], 'subject_grades': grades}, headers=headers)
    assert response.status_code == 200 and response.json['results'][0]['status'] == 'requirements_met'
    response = client.post('/simulate', json={'programmes': [fixture], 'baseline_grades': {'Maths': 'E'},
        'scenario_grades': grades}, headers=headers)
    assert response.status_code == 200 and response.json['scenario_saved'] is False
    assert response.json['results'][0]['status'] == 'requirements_met'


def test_scholarship_api_reports_explicit_closed(client, headers):
    response = client.get('/scholarships?status=closed&q=Capernaum', headers=headers)
    assert response.status_code == 200
    assert response.json['count'] == 1


def test_chat_has_official_links_and_excludes_legacy_programmes(client, headers):
    response = client.post('/chat', json={'message': 'What programmes does NUL offer?'}, headers=headers)
    assert response.status_code == 200
    assert 'Computer Science' in response.json['reply']
    assert 'Bachelor of Education' not in response.json['reply']
    assert any(source['url'].startswith('https://nul.ls/') for source in response.json['sources'])
    assert all(source['url'].startswith('https://') for source in response.json['sources'])
    assert response.json['engine'] == 'local-retrieval'


def test_chat_never_calls_external_generator(client, headers, monkeypatch):
    import requests
    calls = []
    def forbidden(*args, **kwargs):
        calls.append((args, kwargs))
        raise AssertionError('No external request permitted')
    monkeypatch.setenv('ENABLE_GEMINI', 'true')
    monkeypatch.setenv('GEMINI_API_KEY', 'synthetic-test-placeholder')
    monkeypatch.setattr(requests, 'post', forbidden)
    response = client.post('/chat', json={'message': 'Explain my interests',
        'context': {'riasec_code': 'ISA', 'full_name': 'PRIVATE', 'notes': 'PRIVATE'}}, headers=headers)
    assert response.status_code == 200
    assert 'PRIVATE' not in response.json['reply']
    assert calls == []
    assert client.get('/health', headers=headers).json['gemini_enabled'] is False


def test_chat_context_whitelist(client, headers, monkeypatch):
    captured = {}
    def reply(message, context):
        captured.update(context)
        return {'reply': 'test', 'sources': []}
    monkeypatch.setattr(service.BOT, 'reply', reply)
    client.post('/chat', json={'message': 'Hello', 'context': {
        'riasec_code': 'ISA', 'subjects': ['Maths'], 'email': 'PRIVATE', 'marks': {'Maths': 50}}}, headers=headers)
    assert captured == {'riasec_code': 'ISA', 'subjects': ['Maths']}


def test_chat_uses_current_approved_catalogue(client, headers):
    fixture = programme()
    fixture.update(university='National University of Lesotho', programme='Synthetic approved test programme')
    response = client.post('/chat', json={'message': 'What programmes does NUL offer?',
        'programmes': [fixture], 'institutions': [], 'scholarships': []}, headers=headers)
    assert 'Synthetic approved test programme' in response.json['reply']
    fixture['verification_status'] = 'draft'
    response = client.post('/chat', json={'message': 'What programmes does NUL offer?',
        'programmes': [fixture]}, headers=headers)
    assert 'Synthetic approved test programme' not in response.json['reply']
    assert 'No verified programme details' in response.json['reply']


@pytest.mark.parametrize('payload', [
    {'message': ''}, {'message': ['bad']}, {'message': 'Hello', 'context': []},
    {'message': 'Hello', 'context': {'riasec_code': 'X'}},
    {'message': 'Hello', 'programmes': 'invalid'},
])
def test_chat_rejects_invalid_input(client, headers, payload):
    assert client.post('/chat', json=payload, headers=headers).status_code == 400


def test_chat_funding_exposes_closed_status():
    response = BOT.reply('Capernaum scholarship')
    assert 'Status: closed' in response['reply']
    assert response['sources'][0]['url'].startswith('https://moshoeshoe1.higherlifefoundation.com/')


def test_invalid_json_and_oversize_return_json_errors(client, headers):
    response = client.post('/recommend', data='[', content_type='application/json', headers=headers)
    assert response.status_code == 400 and response.is_json
    response = client.post('/recommend', data='x' * (513 * 1024), content_type='application/json', headers=headers)
    assert response.status_code == 413 and response.is_json

