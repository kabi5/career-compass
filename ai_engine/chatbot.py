"""Local source-linked retrieval. No prompt or profile goes to external AI."""
import re
from datetime import date
from urllib.parse import urlparse
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from model import CAREERS, EDUCATION, _infer_subjects
from programmes import PROGRAMMES, INSTITUTIONS, SCHOLARSHIPS, canon
from eligibility import scholarship_status

RIASEC_INFO = {
    'R': ('Realistic', 'Practical activities involving tools, objects, animals or outdoor work.'),
    'I': ('Investigative', 'Analysing, researching and solving problems.'),
    'A': ('Artistic', 'Creative and expressive activities such as design, writing or performance.'),
    'S': ('Social', 'Helping, teaching, listening and supporting people.'),
    'E': ('Enterprising', 'Leading, persuading and developing ideas with other people.'),
    'C': ('Conventional', 'Organising information and attending to detail.'),
}
UNIVERSITY_ALIASES = {
    'nul': 'National University of Lesotho', 'national university': 'National University of Lesotho',
    'botho': 'Botho University Lesotho',
    'limkokwing': 'Limkokwing University of Creative Technology (Lesotho)',
    'lerotholi': 'Lerotholi Polytechnic', 'polytechnic': 'Lerotholi Polytechnic',
    'lce': 'Lesotho College of Education', 'college of education': 'Lesotho College of Education',
    'agricultural college': 'Lesotho Agricultural College',
}
DISCLAIMER = ('Reference facts have limited verification scopes. Confirm current details with the '
              'institution/provider; these are not admission or funding decisions.')
FALLBACK = ('I do not have verified information to answer that. Try a specific career, institution '
            'or funding provider, or ask a teacher to help check an official source.')


def _text(value):
    return value if isinstance(value, str) else ''


def _texts(value):
    return [v for v in value if isinstance(v, str)] if isinstance(value, list) else []


def _approved(item):
    if not isinstance(item, dict) or item.get('verification_status') != 'verified':
        return False
    url = item.get('source_url') or item.get('official_url')
    if not isinstance(url, str) or not isinstance(item.get('last_verified'), str):
        return False
    try:
        parsed = urlparse(url)
        date.fromisoformat(item['last_verified'])
        return parsed.scheme == 'https' and bool(parsed.hostname) and not parsed.username
    except ValueError:
        return False


def _source(item):
    return {'title': _text(item.get('programme') or item.get('title') or item.get('name')) or 'Official source',
            'url': item.get('source_url') or item.get('official_url'),
            'verification_scope': _text(item.get('verification_scope')), 'last_verified': item.get('last_verified')}


def _result(reply, sources=None):
    distinct = {(s['title'], s['url']): s for s in sources or [] if s and s.get('url')}
    links = list(distinct.values())
    return {'reply': reply, 'sources': links, 'source_links': links, 'engine': 'local-retrieval'}


def _programme_text(item):
    duration = item.get('duration_years')
    duration_text = f' Published duration: {duration} years.' if type(duration) in (int, float) and duration > 0 else ' Duration not verified.'
    return (_text(item.get('programme')) + ' — ' + _text(item.get('university')) + '.' + duration_text + ' '
            + (_text(item.get('entry_requirements')) or 'Entry requirements not verified.')
            + ' Verification scope: ' + (_text(item.get('verification_scope')) or 'See the linked source.')
            + ' Confirm current accreditation and intake separately.')


def _funding_text(item):
    status = scholarship_status(item)
    label = {'closed': 'closed', 'open': 'within the published application dates',
             'upcoming': 'not yet within the published application dates', 'unknown': 'dates or current availability unconfirmed'}[status]
    return (_text(item.get('title')) + ' — ' + _text(item.get('provider')) + '. Status: ' + label + '. '
            + _text(item.get('eligibility')) + ' ' + _text(item.get('verification_scope')))


# Reviewed editorial explanations replace unsupported assertions in the legacy FAQ.
FAQ = [
    ('What is RIASEC Holland code interest types',
     'RIASEC describes Realistic, Investigative, Artistic, Social, Enterprising and Conventional interests. '
     'Your highest letters help explore activities. The 24-question Career Compass form is exploratory, '
     'not a validated psychometric assessment; a high score is not a percentile or proof of ability.'),
    ('How choose subjects school study qualifications preparation',
     'Check the official subject requirements of programmes that interest you, then use the eligibility '
     'and what-if tools. Discuss changes with a teacher; career subject suggestions are exploratory, not admission rules.'),
    ('Undecided unsure career choose compare alternatives',
     'Explore several options and compare up to three careers. Consider activities you enjoy and practical constraints. '
     'Try a small project or talk with a counsellor. You can revise your profile and recalculate without retaking the assessment.'),
    ('Recommendations accuracy trust how score calculated',
     'The engine uses weighted matching of interests and available profile information. Missing components are excluded '
     'and weights adjusted. It is not trained machine learning, an admission decision or a career-success prediction. '
     'Read each score breakdown and disagree when a suggestion does not fit.'),
    ('Careers demand employment job salary labour market',
     'No verified local occupation-level employment-demand or salary evidence is available in this catalogue. '
     'A high match does not mean jobs are available. Ask a counsellor for current official labour-market sources.'),
    ('Difference certificate diploma degree qualification',
     'Levels, duration and progression vary by programme. Use the official programme source and confirm recognition '
     'with the Council on Higher Education. Unverified duration or requirements remain unknown.'),
    ('Save plan roadmap progress milestones skills',
     'Choose a career goal, record preparation activities and milestones, and review progress with a counsellor. '
     'Suggested targets support planning; they do not guarantee entry.'),
]


class CareerBot:
    def __init__(self, programmes=None, institutions=None, scholarships=None):
        self.programmes = [p for p in (PROGRAMMES if programmes is None else programmes) if _approved(p)]
        self.institutions = [p for p in (INSTITUTIONS if institutions is None else institutions) if _approved(p)]
        self.scholarships = [p for p in (SCHOLARSHIPS if scholarships is None else scholarships) if _approved(p)]
        self.docs = [{'kind': 'faq', 'title': q, 'text': q, 'answer': a, 'sources': []} for q, a in FAQ]
        for p in self.programmes:
            title = _text(p.get('programme'))
            self.docs.append({'kind': 'programme', 'title': title,
                'text': ' '.join([title, _text(p.get('university')), *_texts(p.get('related_careers'))]),
                'answer': _programme_text(p), 'sources': [_source(p)]})
        for p in self.institutions:
            title = _text(p.get('name'))
            self.docs.append({'kind': 'institution', 'title': title, 'text': title,
                'answer': title + ': ' + _text(p.get('verification_scope')), 'sources': [_source(p)]})
        for p in self.scholarships:
            title = _text(p.get('title'))
            self.docs.append({'kind': 'funding', 'title': title,
                'text': ' '.join([title, _text(p.get('provider')), *_texts(p.get('fields'))]),
                'item': p, 'sources': [_source(p)]})
        for career in CAREERS:
            self.docs.append({'kind': 'career', 'title': career['title'], 'text': career['title'], 'career': career})
        self.vectorizer = TfidfVectorizer(stop_words='english', ngram_range=(1, 2), sublinear_tf=True)
        self.matrix = self.vectorizer.fit_transform([d['text'] for d in self.docs])

    def _career_answer(self, career):
        education = EDUCATION.get('by_occupation', {}).get(career.get('soc_code'), {})
        lines = [career['title'] + ': reference interest code ' + career.get('riasec_code', ''),
                 'Typical education in international reference data: ' + education.get('most_common_label', 'not specified') + '.',
                 'Exploratory subject suggestions: ' + _infer_subjects(career) + '. These are not entry requirements.']
        title = canon(career['title']).rstrip('s')
        related = [p for p in self.programmes if any(canon(t).rstrip('s') in title for t in _texts(p.get('related_careers')))]
        sources = []
        soc = career.get('soc_code', '')
        if re.fullmatch(r'\d{2}-\d{4}\.\d{2}', soc):
            sources.append({'title': career['title'] + ' — international occupational reference',
                'url': 'https://www.onetonline.org/link/summary/' + soc,
                'verification_scope': 'Legacy international reference data; original release version unresolved.', 'last_verified': None})
        if related:
            lines.append('Possible programmes to investigate: ' + '; '.join(
                p['programme'] + ' at ' + p['university'] for p in related[:3]) + '.')
            lines.append('Programme identity does not establish current accreditation, eligibility or intake availability.')
            sources.extend(_source(p) for p in related[:3])
        else:
            lines.append('No verified related local programme is in this catalogue; this does not mean none exists.')
        return '\n'.join(lines), sources

    def _answer_for(self, doc):
        if doc['kind'] == 'career':
            return self._career_answer(doc['career'])
        if doc['kind'] == 'funding':
            return _funding_text(doc['item']), doc['sources']
        return doc['answer'], doc.get('sources', [])

    def reply(self, message, context=None):
        message = _text(message).strip()
        msg = message.lower()
        context = context or {}
        if not message:
            return _result('Please type a question.')
        if msg in ('hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening'):
            return _result('Hello! Ask about careers, interests, subjects, official programme references or funding.')
        if msg in ('thanks', 'thank you', 'cheers'):
            return _result("You're welcome. You can keep exploring or discuss your next step with a counsellor.")
        code = _text(context.get('riasec_code'))
        if code and re.search(r'\b(my|me)\b.*\b(result|code|profile|interest|type)s?\b', msg):
            parts = ['Your exploratory interest code is ' + code + '.']
            parts.extend(c + ' — ' + RIASEC_INFO[c][0] + ': ' + RIASEC_INFO[c][1] for c in code if c in RIASEC_INFO)
            parts.append('These are interests, not fixed abilities or a validated prediction of your future.')
            return _result('\n'.join(parts))
        if re.search(r'\b(scholarship|scholarships|bursary|bursaries|funding|nmds|sponsorship)\b', msg):
            matches = self.scholarships
            provider = next((t for t in ('nmds', 'botho', 'capernaum', 'higherlife') if t in msg), None)
            if provider:
                matches = [s for s in matches if provider in (_text(s.get('title')) + ' ' + _text(s.get('provider'))).lower()]
            if not matches:
                return _result('No verified funding information matching that provider is available. Check official announcements.')
            return _result('\n\n'.join(_funding_text(s) for s in matches[:3]) + '\n\n' + DISCLAIMER, [_source(s) for s in matches[:3]])
        university = next((name for alias, name in UNIVERSITY_ALIASES.items()
                           if re.search(r'\b' + re.escape(alias) + r'\b', msg)), None)
        if university and re.search(r'offer|programme|program|course|study|faculty|qualification|requirement|admission', msg):
            matches = [p for p in self.programmes if p.get('university') == university]
            if matches:
                return _result('\n\n'.join(_programme_text(p) for p in matches[:4]) + '\n\n' + DISCLAIMER, [_source(p) for p in matches[:4]])
            return _result('No verified programme details for that institution are available. Consult its official prospectus.')
        similarities = cosine_similarity(self.vectorizer.transform([message]), self.matrix)[0]
        index = int(similarities.argmax())
        if float(similarities[index]) < .12:
            return _result(FALLBACK)
        answer, sources = self._answer_for(self.docs[index])
        return _result(answer + '\n\n' + DISCLAIMER, sources)


BOT = CareerBot()

