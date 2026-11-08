"""Static integration contract checks; live MySQL acceptance remains manual."""
import unittest
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]

class ReleaseContractTests(unittest.TestCase):
    def read(self,path):
        return (ROOT/path).read_text(encoding='utf-8')

    def test_retake_is_append_only(self):
        code=self.read('frontend/assessment.php')
        self.assertNotIn('DELETE FROM recommendations',code)
        for required in ('INSERT INTO assessments','INSERT INTO recommendation_runs',
                         'INSERT INTO recommendation_details','INSERT INTO assessment_responses',
                         'INSERT INTO profile_versions','beginTransaction()', 'rollBack()'):
            self.assertIn(required, code)

    def test_dashboard_sorts_coverage_first(self):
        code=self.read('frontend/dashboard.php')
        self.assertIn('COALESCE(r.data_coverage,0) DESC',code)
        self.assertIn('COALESCE(r.ranking_score,r.match_score,-1) DESC',code)
        self.assertIn('Provisional alignment',code)
        self.assertNotIn('Approximate five-factor match',code)

    def test_all_dashboard_routes_exist(self):
        for name in ('directory.php','simulate.php','compare.php',
                     'history.php','roadmap.php','career-detail.php'):
            self.assertTrue((ROOT/'frontend'/name).is_file(),name)

    def test_schema_creates_history_and_roadmaps(self):
        sql=self.read('database/schema.sql')
        for table in ('assessment_responses','profile_versions','recommendation_runs',
                      'recommendation_details','career_plans','roadmap_milestones'):
            self.assertIn('CREATE TABLE IF NOT EXISTS '+table,sql)
        self.assertIn('INSERT IGNORE INTO assessment_questions',sql)

    def test_no_personal_dump_bundled(self):
        self.assertFalse((ROOT/'career_guidance (2).sql').exists())
        self.assertFalse((ROOT/'ai_engine/.env').exists())
        self.assertTrue((ROOT/'index.php').exists())

    def test_score_version_and_evidence_guardrails(self):
        model=self.read('ai_engine/model.py')
        self.assertIn('ls-v1.1',model)
        self.assertIn('MIN_DISPLAY_COVERAGE = 0.75',model)
        self.assertIn('local_opportunity_evidence(soc)',model)

    def test_directory_funding_response_binding(self):
        self.assertIn("'scholarships' : 'programmes'",self.read('frontend/directory.php'))
        self.assertIn('@app.get("/scholarships")',self.read('ai_engine/app.py'))

if __name__=='__main__':
    unittest.main()
