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
    # The public contract now requires all 24 items, four per dimension.
    responses = [{'dimension': d, 'answer': a} for d, a in
                 [('R', 2), ('I', 5), ('A', 3), ('S', 4), ('E', 1), ('C', 2)] for _ in range(4)]
    headers = {'X-Internal-Token': __import__('os').environ.get('INTERNAL_API_TOKEN', '')}
    r = client.post('/recommend', json={'responses': responses, 'top_n': 3}, headers=headers)
    assert r.status_code == 200
    assert len(r.json['recommendations']) == 3
    assert r.json['top_code'] == 'ISA'
