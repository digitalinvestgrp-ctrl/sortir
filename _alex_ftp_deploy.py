#!/usr/bin/env python3
"""
_alex_ftp_deploy.py — Deploy script Trouvetateam vers OVH mutu (FTP)

Pattern Agendia : upload selectif src/, public/, config/, migrations/, vendor/
vers /ttt/ sur ftp.cluster030.hosting.ovh.net.

Usage :
    python _alex_ftp_deploy.py           # deploy full
    python _alex_ftp_deploy.py --dry     # liste les fichiers a deployer sans upload
    python _alex_ftp_deploy.py --only public  # deploy uniquement public/

Securite :
- Exclut .env (deploye separement)
- Exclut tests/, logs/*, .git/, *.swp, *.swo
- Auto-cleanup php_flag dans .htaccess (interdit OVH mutu, leve 500)
"""

import ftplib
import os
import sys
import re
from pathlib import Path

# ----- Config -----
FTP_HOST = "ftp.cluster030.hosting.ovh.net"
FTP_USER = "cijufrg"
FTP_PASS = "170289aB"
REMOTE_ROOT = "/ttt"

LOCAL_ROOT = Path(__file__).resolve().parent

INCLUDED_DIRS = ["public", "src", "config", "migrations", "vendor"]
EXCLUDED_PATTERNS = re.compile(
    r"(\.git/|\.env$|tests/|logs/.+|\.swp$|\.swo$|__pycache__|\.phpunit|coverage/)"
)


def should_skip(rel_path: str) -> bool:
    if EXCLUDED_PATTERNS.search(rel_path):
        return True
    return False


def cleanup_htaccess(content: str) -> str:
    """Supprime les lignes php_flag interdites sur OVH mutu."""
    lines = content.splitlines(keepends=True)
    kept = []
    removed = 0
    for line in lines:
        stripped = line.strip()
        if stripped.lower().startswith("php_flag ") or stripped.lower().startswith("php_value "):
            removed += 1
            continue
        kept.append(line)
    if removed:
        print(f"  [htaccess cleanup] removed {removed} php_flag/php_value lines")
    return "".join(kept)


def ensure_remote_dir(ftp: ftplib.FTP, remote_dir: str):
    """mkdir -p equivalent sur FTP."""
    parts = remote_dir.strip("/").split("/")
    current = ""
    for p in parts:
        current = f"{current}/{p}" if current else f"/{p}"
        try:
            ftp.mkd(current)
        except ftplib.error_perm as e:
            if not str(e).startswith("550"):
                raise


def upload_file(ftp: ftplib.FTP, local: Path, remote: str, dry: bool):
    if dry:
        print(f"  DRY upload : {local} -> {remote}")
        return
    # Cleanup .htaccess
    if local.name == ".htaccess":
        content = local.read_text(encoding="utf-8", errors="replace")
        clean = cleanup_htaccess(content)
        if clean != content:
            import io
            ftp.storbinary(f"STOR {remote}", io.BytesIO(clean.encode("utf-8")))
            print(f"  uploaded (cleaned) : {remote}")
            return
    with open(local, "rb") as f:
        ftp.storbinary(f"STOR {remote}", f)
    print(f"  uploaded : {remote}")


def deploy(dry: bool = False, only: str = None):
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, FTP_PASS, timeout=30)
    print(f"Connected to {FTP_HOST} as {FTP_USER}")

    try:
        ftp.cwd(REMOTE_ROOT)
    except ftplib.error_perm:
        ensure_remote_dir(ftp, REMOTE_ROOT)
        ftp.cwd(REMOTE_ROOT)

    dirs_to_deploy = [only] if only else INCLUDED_DIRS

    for d in dirs_to_deploy:
        local_dir = LOCAL_ROOT / d
        if not local_dir.is_dir():
            print(f"[skip] {d}/ not found locally")
            continue

        for local_path in local_dir.rglob("*"):
            if local_path.is_dir():
                continue
            rel = str(local_path.relative_to(LOCAL_ROOT)).replace(os.sep, "/")
            if should_skip(rel):
                continue
            remote_path = f"{REMOTE_ROOT}/{rel}"
            remote_dir = "/".join(remote_path.split("/")[:-1])
            try:
                ensure_remote_dir(ftp, remote_dir)
            except Exception as e:
                print(f"  WARN mkdir {remote_dir}: {e}")
            try:
                upload_file(ftp, local_path, remote_path, dry)
            except Exception as e:
                print(f"  ERROR uploading {rel}: {e}")

    ftp.quit()
    print("Deploy done.")


if __name__ == "__main__":
    dry = "--dry" in sys.argv
    only = None
    if "--only" in sys.argv:
        idx = sys.argv.index("--only")
        if idx + 1 < len(sys.argv):
            only = sys.argv[idx + 1]
    deploy(dry=dry, only=only)
