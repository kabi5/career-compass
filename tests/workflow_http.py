"""Real PHP + Flask + MySQL learner workflows, only against a disposable localhost app.
Set CC_TEST_BASE_URL and CC_TEST_DB_NAME=career_compass_qa (or test_*).
No real learner data is needed. Synthetic records remain in the disposable database.
"""
import html
import json
import os
from pathlib import Path
import re
import secrets
import tempfile
from urllib.parse import urlparse
import requests

BASE = os.getenv('CC_TEST_BASE_URL', 'http://127.0.0.1:8088').rstrip('/')
assert urlparse(BASE).hostname in ('127.0.0.1', 'localhost'), 'Local test server required'
assert os.getenv('CC_TEST_DB_NAME', '').startswith(('career_compass_qa', 'test_')), 'Explicit disposable database name required'
client = requests.Session()
checks = []

def check(condition, label):
    assert condition, label
    checks.append(label)

def get(page):
    response = client.get(BASE + '/' + page, timeout=30)
    check(response.status_code == 200, 'GET ' + page)
    check(not re.search(r'(Fatal error:|Warning:|Parse error:)', response.text), 'No PHP errors: ' + page)
    return response

def csrf(response):
    match = re.search(r'name="csrf" value="([a-f0-9]+)"', response.text)
    assert match, 'CSRF token exists'
    return match.group(1)

def post(page, values, source=None):
    token = csrf(source or get(page))
    response = client.post(BASE + '/' + page, data={**values, 'csrf': token}, timeout=60)
    check(response.status_code == 200, 'POST ' + page)
    check(not re.search(r'(Fatal error:|Warning:|Parse error:)', response.text), 'No PHP errors after POST ' + page)
    return response

landing = get('index.php')
check('no-store' in landing.headers.get('Cache-Control', ''), 'PHP responses never cached')
check('Content-Security-Policy' in landing.headers, 'CSP present')
check(client.post(BASE+'/profile.php', data={}).url.endswith('login.php'), 'Anonymous profile is inaccessible')
email = 'workflow-' + secrets.token_hex(5) + '@example.invalid'
password = secrets.token_urlsafe(24)
registered = post('register.php', {'full_name':'Synthetic QA Learner','email':email,'password':password,'grade':'Grade 11','privacy_ack':'1','role':'admin'})
check(registered.url.endswith('dashboard.php'), 'Registration succeeds as learner')
check(client.get(BASE+'/admin.php').status_code == 403, 'Registration cannot elevate admin role')
check(client.get(BASE+'/teacher.php').status_code == 403, 'Learner cannot open teacher portal')
check(client.post(BASE+'/profile.php', data={'csrf':'wrong'}).status_code == 419, 'CSRF rejects profile write')
profile = {'subjects':'Mathematics, English, Physical Sciences','subject_grades':'Mathematics = D\nEnglish = C\nPhysical Sciences = C','maths_mark':'55','science_mark':'65','english_mark':'70','skills':'problem solving, analysis','preferences':'research','guidance_needs':'funding, choosing subjects','aspirations':'software developer'}
saved = post('profile.php', profile)
check('Your profile is saved' in saved.text, 'Profile saved with marks and grades')
bad = post('profile.php', {**profile,'subject_grades':'Maths = A\nMathematics = C'})
check('Enter each subject only once' in bad.text, 'Conflicting subject aliases rejected')
bad = post('profile.php', {**profile,'maths_mark':'101'})
check('between 0 and 100' in bad.text, 'Invalid percentage rejected')
bad = post('profile.php', {**profile,'skills':'x'*101})
check('100 characters or fewer' in bad.text, 'Oversized profile entry rejected before saving')
assessment = get('assessment.php')
questions = sorted(set(re.findall(r'name="(q\d+)"', assessment.text)))
check(len(questions)==24, 'Assessment has all 24 items')
bad = post('assessment.php', {questions[0]:'5'}, assessment)
check('Please answer every question' in bad.text, 'Incomplete assessment rejected')
result = post('assessment.php', {q:str((int(q[1:])%5)+1) for q in questions}, assessment)
check('completed=1' in result.url, 'Assessment generates and persists recommendations')
rec_ids = re.findall(r'career-detail.php\?id=(\d+)', result.text)
check(len(rec_ids)==5, 'Dashboard shows five current matches')
detail = get('career-detail.php?id='+rec_ids[0])
check('Interest alignment' in detail.text and 'Academic preparation' in detail.text, 'Separate score components displayed')
feedback = post('career-detail.php?id='+rec_ids[0], {'rating':'4','comment':'Synthetic QA: explanation is useful.'}, detail)
check('Feedback saved' in feedback.text, 'Recommendation feedback persists')
refreshed = post('recalculate.php', {}, get('profile.php'))
check('refreshed=1' in refreshed.url, 'Recalculate without retaking assessment')
check(get('career-detail.php?id='+rec_ids[0]).status_code==200, 'Previous recommendations retained')
history=get('history.php')
check(history.text.count('Profile version #')>=2, 'History associates runs with profile versions')
eligibility=post('eligibility.php', {'programme':'lp-computer-engineering','grades':profile['subject_grades']})
check('Requirements not met' in eligibility.text and 'Mathematics' in eligibility.text, 'Verified prerequisite failure explained')
scenario=post('simulate.php', {'scenario':'Mathematics = B\nEnglish = C\nPhysical Sciences = C'})
check('Current profile' in scenario.text and 'Scenario' in scenario.text, 'Scenario comparison rendered')
check('Mathematics = D' in html.unescape(get('profile.php').text), 'Simulation does not overwrite actual grades')
comparison=post('compare.php', {'career0':'Software Developers','career1':'Civil Engineers','career2':'Registered Nurses'})
check('No current occupation-specific dataset available' in comparison.text, 'Comparison does not fabricate labour-market data')
saved=post('progress.php', {'action':'save','type':'career','id':'Software Developers'}, get('compare.php'))
check('Software Developers' in saved.text, 'Favourite career saved')
for kind, item in [('programme','lp-computer-engineering'),('scholarship','nmds-loan-bursary')]:
    saved=post('progress.php', {'action':'save','type':kind,'id':item})
check('NMDS student loan bursary' in saved.text, 'Programme and scholarship saved')
plan=post('roadmap.php', {'action':'create','career':'Software Developers'})
check('roadmap.php?id=' in plan.url, 'Roadmap generated and saved')
plan_id=re.search(r'id=(\d+)',plan.url).group(1)
plan=post('roadmap.php?id='+plan_id, {'action':'milestone','plan_id':plan_id,'title':'Complete a synthetic test project','due_date':'2026-12-01'},plan)
check('Complete a synthetic test project' in plan.text, 'Custom milestone saved')
mid=re.findall(r'name="milestone_id" value="(\d+)"',plan.text)[-1]
plan=post('roadmap.php?id='+plan_id, {'action':'toggle','plan_id':plan_id,'milestone_id':mid},plan)
check('1 of 5 completed' in plan.text, 'Milestone progress tracked')
pdf=client.get(BASE+'/report.php?plan='+plan_id,timeout=30)
check(pdf.status_code==200 and pdf.content.startswith(b'%PDF-1.4') and b'%%EOF' in pdf.content, 'Actual PDF download generated')
Path(tempfile.gettempdir(),'career-compass-qa-report.pdf').write_bytes(pdf.content)
check('no-store' in pdf.headers.get('Cache-Control',''), 'PDF not cached')
closed=get('scholarships.php?status=closed')
check('Capernaum' in closed.text and 'Botho University financial aid' not in closed.text, 'Scholarship closure filter works without invented deadline')
chat_page=get('chat.php')
token=re.search(r'const csrf="([a-f0-9]+)"',chat_page.text).group(1)
chat=client.post(BASE+'/chat_api.php',json={'message':'What funding opportunities are available?'},headers={'X-CSRF-Token':token},timeout=45)
check(chat.status_code==200 and 'reply' in chat.json(), 'Private source-linked assistant responds')
for page in ['directory.php?type=institutions','account.php','progress.php','history.php','profile.php?lang=st']:
    get(page)
check('lang="st"' in get('profile.php?lang=st').text, 'Language preference switches')
second=requests.Session()
check(second.get(BASE+'/report.php?plan='+plan_id).url.endswith('login.php'), 'Anonymous PDF access denied')
check(second.get(BASE+'/roadmap.php?id='+plan_id).url.endswith('login.php'), 'Anonymous plan access denied')
Path(tempfile.gettempdir(),'career-compass-qa-browser.json').write_text(json.dumps({'email':email,'password':password,'plan_id':plan_id}),encoding='utf-8')
Path(__file__).resolve().parents[1].joinpath('docs','workflow-results.json').write_text(json.dumps({'checks_passed':len(checks),'checks':checks},indent=2),encoding='utf-8')
print(f'{len(checks)} learner HTTP checks passed. Synthetic browser credentials saved outside the package in the OS temp directory.')
