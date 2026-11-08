# Career Compass — software prototype acceptance checklist

Complete these checks on **your own Windows/XAMPP installation** before submission. Record **Pass / Fail / N/A**, date and a redacted screenshot for each test. Do not enter real learner records in published screenshots.

| Test | Steps | Expected result | Result |
|---|---|---|---|
| 1. Start-up | Start Apache/MySQL/Flask, open `http://localhost/Career_Compass/` | Redirect to home; no 403 | ☐ |
| 2. Health | Open `/health` on Flask | `status: ok`, `score_version: ls-v1.1` | ☐ |
| 3. Registration | Register with a *test* email and 12+ character password | Account created, dashboard opens | ☐ |
| 4. Authentication | Sign out; sign in with test account | Valid password accepted; invalid password rejected | ☐ |
| 5. Profile | Select LGCSE, year, subjects and sample grades | Profile saves, survives refresh | ☐ |
| 6. Validation | Try an invalid grade or out-of-range mark | Visible validation error, no malformed row saved | ☐ |
| 7. RIASEC | Submit all 24 questions | Dashboard opens, six bars + Holland code | ☐ |
| 8. Ranking | Check five returned careers | Sorted first by coverage descending, then alignment descending | ☐ |
| 9. Explanation | Open career detail | Five components, percent coverage, missing reasons and sources | ☐ |
| 10. Retake history | Submit again with different answers | New results display; previous run remains in History | ☐ |
| 11. Ownership | Sign in with another test account; manually enter a different user's recommendation ID | Access denied / 404 | ☐ |
| 12. Study directory | Open Study & funding and search | Programmes, source links, enquiry-only funding disclaimers | ☐ |
| 13. Subject simulator | Try alternate LGCSE grades | Preview generated, original profile unchanged | ☐ |
| 14. Compare | Choose two current recommendations | Scores and factor evidence side by side | ☐ |
| 15. Roadmap | Create plan and toggle a milestone | State persists after refresh | ☐ |
| 16. Chat | Ask an ordinary career question | Answer or helpful service-unavailable message | ☐ |
| 17. API down | Stop Flask and submit from a disposable test account | Clear error; no partial assessment or corrupted data | ☐ |
| 18. DB assertions | Run SQL shown below | `ls-v1.1` run/details present; previous runs retained | ☐ |
| 19. Python tests | Run `test_lesotho_evidence.py`, `test_model_integration.py` | All tests pass | ☐ |
| 20. PHP lint | Run `php -l` on modified PHP files | No syntax errors | ☐ |

## Read-only SQL verification

```sql
SELECT rr.id, rr.user_id, rr.method_version, rr.created_at,
       COUNT(d.recommendation_id) AS recommendation_count
FROM recommendation_runs rr
LEFT JOIN recommendation_details d ON d.run_id=rr.id
GROUP BY rr.id,rr.user_id,rr.method_version,rr.created_at
ORDER BY rr.id DESC LIMIT 10;

SELECT r.id,r.career_title,r.match_score,r.ranking_score,
       r.data_coverage,r.score_status,r.score_version,
       r.eligibility_status
FROM recommendations r ORDER BY r.id DESC LIMIT 10;
```

A `provisional` score is still based on incomplete factor coverage and **must not** be shown as a definitive match. A status of `exploratory` with `NULL` `match_score` is a valid outcome.

## Screenshots for a dissertation appendix

1. Welcome screen and login (no passwords).
2. Academic profile with fictitious sample information.
3. All 24 assessment questions and the completed-interest chart.
4. Dashboard showing score, *provisional* label and coverage.
5. Detail page explaining the five factors.
6. One history run before and after a retake.
7. Programme directory and subject simulator.
8. VS Code 17-test results and Flask `/health` output.
9. phpMyAdmin schema/results with user emails and private records hidden.

**Do not invent participant counts, usability ratings, or psychometric validity statistics.** Record only tests and interviews that actually occurred.
