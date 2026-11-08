"""Run after installing Flask dependencies: python -m pytest tests/test_api.py"""
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ai_engine'))
import pytest
pytest.importorskip('flask')
from app import app


def test_health():
    client = app.test_client()
    response = client.get('/health', headers={'X-Internal-Token': __import__('os').environ.get('INTERNAL_API_TOKEN', '')})
    assert response.status_code == 200
    assert response.json['careers'] > 100


def test_rejects_malformed_assessment():
    client = app.test_client()
    headers = {'X-Internal-Token': __import__('os').environ.get('INTERNAL_API_TOKEN', '')}
    assert client.post('/recommend', json={'responses': []}, headers=headers).status_code == 400
    assert client.post('/chat', json={'message': ''}, headers=headers).status_code == 400


def test_recommendation_endpoint():
    client = app.test_client()
    responses = [{'dimension': d, 'answer': a, 'question_id': index * 4 + n + 1}
                 for index, (d, a) in enumerate(
                     [('R', 2), ('I', 5), ('A', 3), ('S', 4), ('E', 1), ('C', 2)])
                 for n in range(4)]
    headers = {'X-Internal-Token': __import__('os').environ.get('INTERNAL_API_TOKEN', '')}
    r = client.post('/recommend', json={'responses': responses, 'top_n': 3}, headers=headers)
    assert r.status_code == 200
    assert len(r.json['recommendations']) == 3
    assert r.json['top_code'] == 'ISA'
    assert r.json['score_version'] == 'ls-v1.1'
    assert all('components' in match for match in r.json['recommendations'])
    assert all(match['score_status'] in ('exploratory', 'provisional', 'complete') for match in r.json['recommendations'])
    assert all(match['components']['local_opportunity'] is None for match in r.json['recommendations'])
    assert all(0 <= match['data_coverage'] <= 1 for match in r.json['recommendations'])


def test_public_reference_directory_is_labelled():
    client=app.test_client()
    headers={'X-Internal-Token': __import__('os').environ.get('INTERNAL_API_TOKEN', '')}
    data=client.get('/scholarships',headers=headers)
    assert data.status_code==200
    assert all(not item['currently_open'] for item in data.json['scholarships'])
