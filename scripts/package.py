"""Create a clean deployment archive with per-file SHA-256 integrity manifest.

Run from any directory: python scripts/package.py
Only explicitly listed source roots are included. No databases or runtime env files.
"""
from datetime import date
from hashlib import sha256
from pathlib import Path
import json
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIRS = ('ai_engine', 'database', 'deploy', 'docs', 'frontend', 'scripts', 'tests')
ROOT_FILES = ('.dockerignore', '.env.example', '.gitignore', '.htaccess', 'docker-compose.yml', 'README.md')
EXCLUDED_DIRS = {'.git', '.venv', 'venv', '__pycache__', '.pytest_cache', 'node_modules', 'dist'}
EXCLUDED_SUFFIXES = {'.pyc', '.pyo', '.log', '.bak', '.pem', '.key', '.pfx', '.p12'}

def permitted(path):
    relative = path.relative_to(ROOT)
    return (path.is_file() and not path.is_symlink()
            and not EXCLUDED_DIRS.intersection(relative.parts)
            and path.suffix.lower() not in EXCLUDED_SUFFIXES
            and (not path.name.startswith('.env') or path.name == '.env.example'))

def main():
    candidates = [ROOT / name for name in ROOT_FILES]
    for directory in SOURCE_DIRS:
        candidates.extend((ROOT / directory).rglob('*'))
    files = sorted((p for p in candidates if permitted(p)), key=lambda p: p.as_posix())
    manifest = {p.relative_to(ROOT).as_posix(): sha256(p.read_bytes()).hexdigest() for p in files}
    destination = ROOT / 'dist'
    destination.mkdir(exist_ok=True)
    archive = destination / ('Career_Compass_Upgraded_' + date.today().isoformat() + '.zip')
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
        for path in files:
            bundle.write(path, 'Career_Compass/' + path.relative_to(ROOT).as_posix())
        bundle.writestr('Career_Compass/PACKAGE_MANIFEST.json', json.dumps(manifest, indent=2) + '\n')
    with zipfile.ZipFile(archive) as bundle:
        assert bundle.testzip() is None, 'Archive integrity check failed'
        for name, digest in manifest.items():
            assert sha256(bundle.read('Career_Compass/' + name)).hexdigest() == digest, name
    checksum = sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix('.zip.sha256').write_text(checksum + '  ' + archive.name + '\n', encoding='ascii')
    print(f'Created {archive.name}: {len(files)} source files, {archive.stat().st_size:,} bytes. CRC and SHA-256 checks passed.')

if __name__ == '__main__':
    main()
