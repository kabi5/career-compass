"""Approved directory data plus retained, clearly unverified legacy records."""
import json
import re
from pathlib import Path

BASE_DIR = Path(__file__).parent

def _read(relative):
    path = BASE_DIR / relative
    return json.loads(path.read_text(encoding='utf-8-sig')) if path.exists() else []


PROGRAMMES = _read('data/directory/programmes.json') + _read('data/programmes/programmes.json')
INSTITUTIONS = _read('data/directory/institutions.json')
SCHOLARSHIPS = _read('data/directory/scholarships.json')


def canon(text):
    text = re.sub(r'[^a-z0-9 ]', ' ', (text or '').lower())
    text = ' '.join(text.split())
    return {'maths': 'mathematics', 'math': 'mathematics', 'mathematics': 'mathematics',
            'physical sciences': 'physical science',
            'english language': 'english'}.get(text, text)


def programmes_for_career(career_title, learner_subjects=None, limit=4, catalogue=None):
    title = canon(career_title).rstrip('s')
    learner = {canon(s) for s in (learner_subjects or [])}
    out = []
    for p in (PROGRAMMES if catalogue is None else catalogue):
        if not any(canon(k).rstrip('s') in title for k in p.get('related_careers', [])):
            continue
        required = p.get('required_subjects', [])
        met = [s for s in required if canon(s) in learner]
        missing = [s for s in required if canon(s) not in learner]
        verified = p.get('verification_status') == 'verified' and bool(p.get('source_url'))
        out.append({**p, 'missing_subjects': missing,
                    'subject_fit': round(len(met)/len(required), 2) if required else None,
                    'verified': verified, 'verification_status': 'verified' if verified else 'unverified',
                    'subject_fit_note': 'Presence of listed subjects only; not an admission decision.'})
    out.sort(key=lambda p: (not p['verified'], -(p['subject_fit'] or 0), p['programme']))
    return out[:limit]


def local_availability_score(programmes):
    # Retained compatibility helper. Never used in the recommendation score.
    return None if not programmes else max((p.get('subject_fit') or 0) for p in programmes)
