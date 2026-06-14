#!/usr/bin/env python3
"""Reusable curl-style test harness for the LockedIn backend (uses stdlib only)."""
import json, os, subprocess, sys, urllib.request, urllib.error
from datetime import datetime, timedelta, timezone

BASE = os.environ.get("LOCKEDIN_BASE", "http://localhost:15436")
REPO_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PASS = FAIL = 0

def _req(method, path, token=None, body=None, ctype="application/json", accept="application/json"):
    url = BASE + path
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Accept", accept)
    if data is not None:
        req.add_header("Content-Type", ctype)
    if token:
        req.add_header("Authorization", "Bearer " + token)
    try:
        r = urllib.request.urlopen(req)
        return r.status, json.loads(r.read() or "null")
    except urllib.error.HTTPError as e:
        raw = e.read()
        try: payload = json.loads(raw)
        except Exception: payload = {"raw": raw.decode(errors="replace")}
        return e.code, payload

def login(email="customer1@openinnov.com", pw="password"):
    st, body = _req("POST", "/api/login", body={"email": email, "password": pw})
    assert st == 200, f"login failed {st} {body}"
    return body["token"]

def sql(q):
    subprocess.run(["docker","compose","exec","-T","apache","php","bin/console","dbal:run-sql",q],
                   cwd=REPO_DIR,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

def reload_fixtures():
    subprocess.run(["docker","compose","exec","-T","apache","php","bin/console","doctrine:fixtures:load","--no-interaction","--no-debug"],
                   cwd=REPO_DIR,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

def lockers(token):
    return _req("GET", "/api/lockers", token)[1]

def bays(token):
    return {b["id"]: b for b in _req("GET", "/api/locker_bays", token)[1]}

def msg(body):
    if not isinstance(body, dict): return ""
    return str(body.get("message") or body.get("detail") or body.get("description") or "")[:80]

def check(label, got, expected, body=None):
    global PASS, FAIL
    ok = got == expected
    PASS += ok; FAIL += (not ok)
    print(f"[{'PASS' if ok else 'FAIL'}] {label:<46} HTTP {got} (exp {expected}) {msg(body) if body else ''}")

def iso(dt): return dt.strftime("%Y-%m-%dT%H:%M:%S+00:00")
def base_day(n): return datetime(2026,7,1,tzinfo=timezone.utc)+timedelta(days=n)

def summary():
    print(f"\n=== {PASS} passed, {FAIL} failed ===")
    sys.exit(1 if FAIL else 0)
