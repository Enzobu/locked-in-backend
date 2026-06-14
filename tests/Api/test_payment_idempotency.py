#!/usr/bin/env python3
"""Payment idempotency: a duplicate POST /payments/intents reuses the PENDING hold, no duplicate reservation."""
import sys, os; sys.path.insert(0, os.path.dirname(__file__))
from _harness import *
from _harness import _req

reload_fixtures()
T = login()
ls = lockers(T); bmap = bays(T)
ok = next(l for l in ls if l["status"] == "available" and bmap[l["lockerBay"]["id"]].get("minDuration"))
dur = timedelta(minutes=bmap[ok["lockerBay"]["id"]]["minDuration"] + 30)
s = base_day(20).replace(hour=10)
e = s + dur
print(f"# locker={ok['id']} slot {iso(s)} -> {iso(e)}")

def pay():
    return _req("POST", "/api/payments/intents", T,
                {"locker": f"/api/lockers/{ok['id']}", "startsAt": iso(s), "endsAt": iso(e)})

def count_for_slot():
    data = _req("GET", "/api/reservations", T)[1]
    return [r for r in data if r.get("locker", "").endswith(f"/{ok['id']}") and r.get("startsAt") == iso(s)]

# Stripe is not configured locally, so the 1st call persists the PENDING hold then
# fails at the Stripe call (400). The 2nd identical call must REUSE that hold.
st1, b1 = pay()
check("1st intent attempt (Stripe unconfigured) -> 400", st1, 400, b1)
st2, b2 = pay()
check("2nd identical intent -> 200 reuse", st2, 200, b2)
check("  flagged idempotentReuse", b2.get("idempotentReuse"), True)

rows = count_for_slot()
check("exactly ONE reservation persisted for the slot", len(rows), 1)

summary()
