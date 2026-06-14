#!/usr/bin/env python3
"""Cancellation lifecycle: PATCH status=cancelled frees the slot, illegal transitions rejected."""
import sys, os; sys.path.insert(0, os.path.dirname(__file__))
from _harness import *
from _harness import _req

reload_fixtures()
T = login()
ls = lockers(T)
bmap = bays(T)
ok = next(l for l in ls if l["status"] == "available" and bmap[l["lockerBay"]["id"]].get("minDuration"))
bay = bmap[ok["lockerBay"]["id"]]
dur = timedelta(minutes=bay["minDuration"] + 30)
print(f"# locker={ok['id']} bay min={bay['minDuration']} max={bay['maxDuration']}")

def book(start, end):
    return _req("POST", "/api/reservations", T,
                {"locker": f"/api/lockers/{ok['id']}", "startsAt": iso(start), "endsAt": iso(end)},
                ctype="application/ld+json", accept="application/ld+json")

def patch(res_id, payload, expected, label):
    st, body = _req("PATCH", f"/api/reservations/{res_id}", T, payload,
                    ctype="application/merge-patch+json", accept="application/ld+json")
    check(label, st, expected, body)
    return body

d = base_day(0)
s, e = d.replace(hour=10), d.replace(hour=10) + dur

print("\n## cancellation frees the slot")
st, a = book(s, e)
check("create A (pending)", st, 201)
res_id = a["id"]
st, _ = book(s + timedelta(minutes=10), e + timedelta(minutes=10))
check("overlapping B blocked while A active", st, 409)
body = patch(res_id, {"status": "cancelled"}, 200, "cancel A via PATCH")
check("  -> status is cancelled", body.get("status"), "cancelled")
check("  -> refundStatus none (unpaid)", body.get("refundStatus"), "none")
check("  -> cancelledAt set", bool(body.get("cancelledAt")), True)
st, _ = book(s + timedelta(minutes=10), e + timedelta(minutes=10))
check("overlapping B now allowed after cancel", st, 201)

print("\n## illegal transitions")
s2 = base_day(1).replace(hour=10)
st, c = book(s2, s2 + dur)
check("create C (pending)", st, 201)
patch(c["id"], {"status": "confirmed"}, 422, "customer cannot self-confirm -> 422")
patch(c["id"], {"status": "active"}, 422, "customer cannot self-activate -> 422")
# cancel C, then re-cancel -> idempotent no-op (REST: same state = 200)
patch(c["id"], {"status": "cancelled"}, 200, "cancel C")
patch(c["id"], {"status": "cancelled"}, 200, "re-cancel already cancelled -> idempotent 200")

# cannot cancel a reservation that is already ACTIVE (in use)
s3 = base_day(2).replace(hour=10)
st, f = book(s3, s3 + dur)
check("create F (pending)", st, 201)
sql(f"UPDATE reservation SET status='active' WHERE id={f['id']}")
patch(f["id"], {"status": "cancelled"}, 409, "cancel an ACTIVE reservation -> 409")

summary()
