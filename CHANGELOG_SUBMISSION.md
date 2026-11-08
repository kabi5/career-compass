# Release notes — 2026-11-08

## Updated since the earlier package

- Preserved current user-supplied `profile.php` and `assessment.php` functionality, and integrated the supplied `ls-v1.1` Python scoring model and reviewed Lesotho programme catalogue.
- Removed destructive recommendation deletion on assessment retakes.
- Recorded assessment responses, profile snapshots, recommendation run metadata and complete per-career JSON evidence transactionally.
- Fixed dashboard ordering: evidence coverage DESC, then ranking score DESC.
- Corrected provisional score labels on dashboard and career-detail page; missing labour evidence remains unknown.
- Created working dashboard destinations: `history.php`, `directory.php`, `simulate.php`, `compare.php`, and `roadmap.php`.
- Added guarded source links and explicitly unconfirmed funding reference listings.
- Added root `index.php` redirect so the project folder opens without a root directory-listing 403.
- Extended both clean schema and non-destructive migration for history and roadmap tables. Does not query `information_schema`.
- Updated legacy test expectations to scoring v1.1 while testing missing academic information independently of verified pathway data.

## Evidence safeguards

- No invented Lesotho occupation-demand points, missing-factor score inflation, or guarantees of admission.
- Grade-to-percentage values used for exploration only; current entry criteria determine eligibility when reviewed.
- No uploaded user MySQL dump or private tokens are included in this release.

## Limitations

Python scoring tests and PHP syntax checks run in the build environment. Live Apache/MySQL/Flask browser interaction, availability checks, and evaluation with real learners require verification on the user's Windows machine. This is a **functional research prototype**, not a security-audited production service.
