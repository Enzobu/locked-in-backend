#!/usr/bin/env python3
"""Expiry cron: stale PENDING reservations become EXPIRED and free the slot."""
import sys, os, subprocess; sys.path.insert(0, os.path.dirname(__file__))
from _harness import *
from _harness import _req, REPO_DIR

def run_expire(older_than=0):
    return subprocess.run(
        ["docker","compose","exec","-T","apache","php","bin/console","app:reservations:expire","--older-than",str(older_than)],
        cwd=REPO_DIR, capture_output=True, text=True)

reload_fixtures()
T = login()
ls = lockers(T)
bmap = bays(T)
ok = next(l for l in ls if l["status"] == "available" and bmap[l["lockerBay"]["id"]].get("minDuration"))
bay = bmap[ok["lockerBay"]["id"]]
dur = timedelta(minutes=bay["minDuration"] + 30)
print(f"# locker={ok['id']}")

def book(start, end):
    return _req("POST", "/api/reservations", T,
                {"locker": f"/api/lockers/{ok['id']}", "startsAt": iso(start), "endsAt": iso(end)},
                ctype="application/ld+json", accept="application/ld+json")

def get(res_id):
    return _req("GET", f"/api/reservations/{res_id}", T)[1]

d = base_day(0)
s, e = d.replace(hour=10), d.replace(hour=10) + dur

print("\n## stale PENDING gets expired and frees the locker")
st, a = book(s, e)
check("create A (pending)", st, 201)
st, _ = book(s, e)
check("overlapping B blocked while A pending", st, 409)

r = run_expire(older_than=0)
check("expire command exit 0", r.returncode, 0, {"message": (r.stdout.strip().splitlines() or [""])[-1]})

check("A is now EXPIRED", get(a["id"]).get("status"), "expired")
st, _ = book(s, e)
check("slot reusable after expiry", st, 201)

print("\n## fresh PENDING (younger than threshold) is NOT expired")
s2 = base_day(1).replace(hour=10)
st, c = book(s2, s2 + dur)
check("create C (pending)", st, 201)
r = run_expire(older_than=60)  # only expire >60min old; C is brand new
check("expire --older-than 60 keeps fresh C", get(c["id"]).get("status"), "pending")

summary()
