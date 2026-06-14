#!/usr/bin/env python3
import sys; sys.path.insert(0, __import__("os").path.dirname(__file__))
from _harness import *
from _harness import _req

reload_fixtures()
T = login()
ls = lockers(T)
bmap = bays(T)

# pick two AVAILABLE lockers whose bay has min/max set
avail = [l for l in ls if l["status"] == "available" and bmap[l["lockerBay"]["id"]].get("minDuration") and bmap[l["lockerBay"]["id"]].get("maxDuration")]
assert len(avail) >= 2, f"need >=2 available lockers with bounds, got {len(avail)}"
ok_l = avail[0]
oof_l = avail[1]
bay = bmap[ok_l["lockerBay"]["id"]]
mn, mx = bay["minDuration"], bay["maxDuration"]
print(f"# locker_ok={ok_l['id']} bay min={mn} max={mx} ; locker_out_of_order={oof_l['id']}")

# force one locker out_of_order
sql(f"UPDATE locker SET status='out_of_order' WHERE id={oof_l['id']}")

def res(label, locker_id, start, end, expected):
    st, body = _req("POST", "/api/reservations", T,
                    {"locker": f"/api/lockers/{locker_id}", "startsAt": iso(start), "endsAt": iso(end)},
                    ctype="application/ld+json", accept="application/ld+json")
    check(label, st, expected, body)

def pay(label, locker_id, start, end, expected):
    st, body = _req("POST", "/api/payments/intents", T,
                    {"locker": f"/api/lockers/{locker_id}", "startsAt": iso(start), "endsAt": iso(end)})
    check(label, st, expected, body)

valid = timedelta(minutes=mn + 30)   # within bounds
d = base_day(0)
print("\n## /api/reservations guard")
res("A valid booking",                 ok_l["id"], d.replace(hour=10), d.replace(hour=10)+valid, 201)
res("B overlapping -> conflict",       ok_l["id"], d.replace(hour=10)+timedelta(minutes=10), d.replace(hour=10)+valid+timedelta(minutes=10), 409)
res("C adjacent slot ok",              ok_l["id"], d.replace(hour=10)+valid, d.replace(hour=10)+valid+valid, 201)
res("D too short -> 422",              ok_l["id"], base_day(1).replace(hour=10), base_day(1).replace(hour=10)+timedelta(minutes=mn-1), 422)
res("E too long -> 422",               ok_l["id"], base_day(2).replace(hour=8), base_day(2).replace(hour=8)+timedelta(minutes=mx+60), 422)
res("F endsAt<=startsAt -> 422",       ok_l["id"], base_day(3).replace(hour=12), base_day(3).replace(hour=10), 422)
res("G out_of_order locker -> 409",    oof_l["id"], base_day(4).replace(hour=10), base_day(4).replace(hour=10)+valid, 409)

print("\n## /api/payments/intents shares the guard (conflict before Stripe)")
pay("H pay overlapping -> 409",        ok_l["id"], d.replace(hour=10)+timedelta(minutes=30), d.replace(hour=10)+timedelta(minutes=30)+valid, 409)
pay("I pay free slot -> reaches Stripe (400 no key)", ok_l["id"], base_day(10).replace(hour=10), base_day(10).replace(hour=10)+valid, 400)

summary()
