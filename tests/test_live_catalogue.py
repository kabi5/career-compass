"""Dynamic catalogue regressions use synthetic data, never fictitious real programmes."""
import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'ai_engine'))
import app as service

def test_comparison_uses_current_database_catalogue(monkeypatch):
    monkeypatch.setattr(service,'API_TOKEN','')
    payload={'career_titles':['Software Developers'],'programmes':[{
        'id':'test-live-source','programme':'SYNTHETIC API fixture',
        'university':'TEST ONLY','related_careers':['software developer'],
        'required_subjects':[],'verification_status':'verified',
        'source_url':'https://example.invalid/source','last_verified':'2026-10-27'}]}
    response=service.app.test_client().post('/compare',json=payload)
    assert response.status_code==200
    assert response.json['careers'][0]['local_programmes'][0]['id']=='test-live-source'
    assert response.json['careers'][0]['interest_alignment'] is None

def test_empty_approved_catalogue_does_not_reintroduce_bundled_samples(monkeypatch):
    monkeypatch.setattr(service,'API_TOKEN','')
    response=service.app.test_client().post('/recommend',json={
        'riasec_scores':dict(R=50,I=90,A=20,S=30,E=40,C=50),'programmes':[]})
    assert response.status_code==200
    assert all(not row['local_programmes'] for row in response.json['recommendations'])

def test_live_catalogue_input_validation(monkeypatch):
    monkeypatch.setattr(service,'API_TOKEN','')
    response=service.app.test_client().post('/compare',json={
        'career_titles':['Software Developers'],'programmes':'not a catalogue'})
    assert response.status_code==400
