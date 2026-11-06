"""Conservative, source-bound admission prerequisite checks.

LGCSE grades are compared ordinally, never converted to school percentages.
Only complete, verified rule sets can produce requirements_met. Missing rules,
unrecognised conditions and manual qualification checks remain undetermined.
"""
from datetime import date, datetime
from zoneinfo import ZoneInfo
from urllib.parse import urlparse

from programmes import canon

GRADE_ORDER = {grade: index for index, grade in enumerate(['A*', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'U'])}
DISCLAIMER = ('This compares published prerequisites only. Meeting checked requirements is not an offer, '
              'acceptance or guarantee of admission. Confirm the current rules and selection process with the institution.')


def normalise_grades(subject_grades):
    if not isinstance(subject_grades, dict) or len(subject_grades) > 40:
        raise ValueError('Provide at most 40 named subjects and LGCSE letter grades')
    result = {}
    for subject, grade in subject_grades.items():
        if not isinstance(subject, str) or not 1 <= len(subject.strip()) <= 100:
            raise ValueError('Subject names must contain 1–100 characters')
        if grade is None or grade == '':
            value = None
        elif not isinstance(grade, str) or grade.upper().strip() not in GRADE_ORDER:
            raise ValueError('Use LGCSE grades A*, A, B, C, D, E, F, G or U; do not enter percentages')
        else:
            value = grade.upper().strip()
        key = canon(subject)
        if not key:
            raise ValueError('Enter a valid subject name')
        if key in result and result[key] != value:
            raise ValueError('Conflicting grades for the same subject: ' + subject)
        result[key] = value
    return result


def _valid_source(programme):
    source = programme.get('source_url') or programme.get('official_url') or ''
    if not isinstance(source, str) or not isinstance(programme.get('last_verified'), str):
        return False
    try:
        parsed = urlparse(source)
        date.fromisoformat(programme['last_verified'])
        return (programme.get('verification_status') == 'verified' and programme.get('verified') is not False
                and parsed.scheme == 'https' and bool(parsed.hostname) and not parsed.username)
    except ValueError:
        return False


def check_programme(programme, grades, grading_system='LGCSE'):
    result = {
        'id': programme.get('id'), 'programme': programme.get('programme', programme.get('title', 'Programme')),
        'university': programme.get('university', ''), 'status': 'undetermined',
        'met': [], 'not_met': [], 'missing_subjects': [], 'unknown': [], 'improvements': [],
        'alternative_pathways': ['Discuss foundation, bridging, certificate or subject-retake routes with the institution; availability is not confirmed.'],
        'source_url': programme.get('source_url') or programme.get('official_url'),
        'last_verified': programme.get('last_verified'), 'disclaimer': DISCLAIMER,
    }
    if not _valid_source(programme):
        result['unknown'].append('Programme admission information has not been verified against a dated official source.')
        return result
    admission = programme.get('admission_rules') or {}
    if not isinstance(admission, dict) or not isinstance(admission.get('rules'), list):
        result['unknown'].append('No machine-readable admission rules have been verified.')
        return result
    if grading_system != 'LGCSE' or admission.get('grading_system') != grading_system:
        result['unknown'].append('The supplied qualification/grade system does not match the verified rules. No conversion is assumed.')
        return result
    if not grades:
        result['unknown'].append('Enter your subjects and letter grades before checking prerequisites.')
        return result
    rules = admission['rules']
    if not rules:
        result['unknown'].append('Published minimum grades are not yet encoded or verified.')
    for rule in rules:
        if not isinstance(rule, dict):
            result['unknown'].append('A rule needs content-review correction.')
            continue
        kind = rule.get('type')
        minimum = rule.get('minimum_grade')
        if kind == 'manual':
            result['unknown'].append(rule.get('description') or rule.get('label') or 'An additional condition requires institutional review.')
            continue
        if minimum not in GRADE_ORDER or minimum == 'U':
            result['unknown'].append('An unrecognised grade threshold requires review.')
            continue
        if kind == 'subject' and isinstance(rule.get('subject'), str):
            subject = rule['subject']
            grade = grades.get(canon(subject))
            label = f'{subject}: {minimum} or better'
            if canon(subject) not in grades:
                result['missing_subjects'].append(subject)
                result['not_met'].append(label + ' — subject not supplied')
                result['improvements'].append(f'Consider taking {subject} and aim for {minimum} or better, after counselling.')
            elif grade is None:
                result['unknown'].append(label + ' — grade missing')
            elif GRADE_ORDER[grade] <= GRADE_ORDER[minimum]:
                result['met'].append(label + f' (supplied {grade})')
            else:
                result['not_met'].append(label + f' (supplied {grade})')
                result['improvements'].append(f'Improve {subject} from {grade} to at least {minimum}.')
        elif kind in ('any_subject', 'count'):
            count = rule.get('count', 1)
            if type(count) is not int or not 1 <= count <= 40:
                result['unknown'].append('A subject-count rule requires review.')
                continue
            if kind == 'any_subject':
                subjects = rule.get('subjects')
                if not isinstance(subjects, list) or not subjects or any(not isinstance(s, str) for s in subjects):
                    result['unknown'].append('A subject-choice rule requires review.')
                    continue
                pool = {canon(s) for s in subjects}
                label = f'At least {count} of {", ".join(subjects)} at {minimum} or better'
            else:
                pool = set(grades)
                label = f'At least {count} distinct subjects at {minimum} or better'
            passing = [s for s in pool if grades.get(s) is not None and GRADE_ORDER[grades[s]] <= GRADE_ORDER[minimum]]
            pending = [s for s in pool if s in grades and grades[s] is None]
            if len(passing) >= count:
                result['met'].append(label + f' ({len(passing)} recorded)')
            elif kind == 'any_subject' and rule.get('exhaustive') is False:
                result['unknown'].append(label + ' — the institution may recognise other subjects; confirm equivalence.')
            elif len(passing) + len(pending) >= count:
                result['unknown'].append(label + ' — some grades are missing')
            else:
                result['not_met'].append(label + f' ({len(passing)} recorded)')
                result['improvements'].append(label + '; review your subject choices and results.')
                if kind == 'any_subject' and not pool.intersection(grades):
                    result['missing_subjects'].append('One of: ' + ', '.join(subjects))
        else:
            result['unknown'].append('An unsupported admission condition requires institutional review.')
    if admission.get('complete') is not True:
        result['unknown'].append('This is a partial rule set; additional requirements or qualification evidence need verification.')
    result['notes'] = admission.get('notes', [])
    if result['not_met']:
        result['status'] = 'requirements_not_met'
    elif result['met'] and not result['unknown']:
        result['status'] = 'requirements_met'
    return result


def check_eligibility(programmes, subject_grades, grading_system='LGCSE'):
    grades = normalise_grades(subject_grades)
    return [check_programme(p, grades, grading_system) for p in programmes]


def simulate(programmes, baseline_grades, scenario_grades, grading_system='LGCSE'):
    baseline = normalise_grades(baseline_grades)
    scenario = normalise_grades(scenario_grades)
    return {'baseline': [check_programme(p, baseline, grading_system) for p in programmes],
            'results': [check_programme(p, scenario, grading_system) for p in programmes],
            'changed_subjects': sorted(k for k in set(baseline) | set(scenario) if baseline.get(k) != scenario.get(k)),
            'disclaimer': DISCLAIMER, 'scenario_saved': False}


def scholarship_status(item, today=None):
    today = today or datetime.now(ZoneInfo('Africa/Maseru')).date()
    if item.get('application_status') == 'closed':
        return 'closed'
    try:
        opens = date.fromisoformat(item['opens_on']) if item.get('opens_on') else None
        closes = date.fromisoformat(item['closes_on']) if item.get('closes_on') else None
    except (ValueError, TypeError):
        return 'unknown'
    if opens and closes and opens > closes:
        return 'unknown'
    if closes and closes < today:
        return 'closed'
    if opens and opens > today:
        return 'upcoming'
    if opens and closes and opens <= today <= closes:
        return 'open'
    # An undated enquiry route is not an open call.
    return 'unknown'


def filter_scholarships(items, query='', level='', status='', today=None):
    result = []
    for item in items:
        current = scholarship_status(item, today)
        searchable = ' '.join([item.get('title', ''), item.get('provider', ''), *item.get('fields', [])]).lower()
        if query.lower() not in searchable or (level and level.lower() not in item.get('level', '').lower()):
            continue
        if status and current != status:
            continue
        result.append({**item, 'current_status': current})
    return result
