#!/usr/bin/env python3
"""Stripe webhook: confirm -> locker reserved/occupied, failure -> freed, idempotent, signature enforced."""
import sys, os, json, hmac, hashlib, time, urllib.request, urllib.error
sys.path.insert(0, os.path.dirname(__file__))
from _harness import *
from _harness import _req

# Must match STRIPE_WEBHOOK_SECRET in .env.local (dev/test value).
WEBHOOK_SECRET = os.environ.get("STRIPE_WEBHOOK_SECRET", "whsec_dev_dummy_secret")

def post_webhook(event, sign=True, secret=WEBHOOK_SECRET):
    body = json.dumps(event)
    ts = int(time.time())
    req = urllib.request.Request(BASE + "/api/stripe/webhook", data=body.encode(), method="POST")
    req.add_header("Content-Type", "application/json")
    if sign:
        signed = f"{ts}.{body}".encode()
        v1 = hmac.new(secret.encode(), signed, hashlib.sha256).hexdigest()
        req.add_header("Stripe-Signature", f"t={ts},v1={v1}")
    else:
        req.add_header("Stripe-Signature", f"t={ts},v1=deadbeef")
    try:
        r = urllib.request.urlopen(req)
        return r.status, json.loads(r.read() or "null")
    except urllib.error.HTTPError as e:
        try: return e.code, json.loads(e.read())
        except Exception: return e.code, {}

def event(pi_id, status, reservation_id, etype="payment_intent.succeeded", flow="initial"):
    return {"type": etype, "data": {"object": {
        "id": pi_id, "status": status,
        "metadata": {"flow": flow, "reservation_id": str(reservation_id)}}}}

reload_fixtures()
T = login()
ls = lockers(T); bmap = bays(T)
ok = next(l for l in ls if l["status"] == "available" and bmap[l["lockerBay"]["id"]].get("minDuration"))
dur = timedelta(minutes=bmap[ok["lockerBay"]["id"]]["minDuration"] + 30)
print(f"# locker={ok['id']}")

def book(start, end):
    return _req("POST", "/api/reservations", T,
                {"locker": f"/api/lockers/{ok['id']}", "startsAt": iso(start), "endsAt": iso(end)},
                ctype="application/ld+json", accept="application/ld+json")
def get_res(i): return _req("GET", f"/api/reservations/{i}", T)[1]
def get_locker(i): return _req("GET", f"/api/lockers/{i}", T)[1]

print("\n## payment succeeded -> CONFIRMED + locker RESERVED")
s = base_day(0).replace(hour=10)
st, a = book(s, s + dur); check("create A pending", st, 201)
code, body = post_webhook(event("pi_A", "succeeded", a["id"]))
check("webhook succeeded -> 200", code, 200, body)
check("  A is confirmed", get_res(a["id"]).get("status"), "confirmed")
check("  A paymentStatus succeeded", get_res(a["id"]).get("paymentStatus"), "succeeded")
check("  locker now reserved", get_locker(ok["id"]).get("status"), "reserved")

print("\n## idempotent re-delivery")
code, body = post_webhook(event("pi_A", "succeeded", a["id"]))
check("re-delivered succeeded -> 200", code, 200, body)
check("  A still confirmed", get_res(a["id"]).get("status"), "confirmed")

print("\n## bad signature rejected")
code, body = post_webhook(event("pi_A", "succeeded", a["id"]), sign=False)
check("invalid signature -> 400", code, 400, body)

print("\n## payment failed -> CANCELLED + locker freed")
s2 = base_day(1).replace(hour=10)
st, b = book(s2, s2 + dur); check("create B pending", st, 201)
# isolate: cancel A so the locker can return to available
_req("PATCH", f"/api/reservations/{a['id']}", T, {"status": "cancelled"}, ctype="application/merge-patch+json")
code, body = post_webhook(event("pi_B", "requires_payment_method", b["id"], etype="payment_intent.payment_failed"))
check("webhook failed -> 200", code, 200, body)
check("  B is cancelled", get_res(b["id"]).get("status"), "cancelled")
check("  locker back to available", get_locker(ok["id"]).get("status"), "available")

print("\n## occupied when confirmed slot covers now")
# force a slot spanning now via SQL, then confirm
past = (datetime.now(timezone.utc) - timedelta(hours=1))
future = (datetime.now(timezone.utc) + timedelta(hours=1))
st, c = book(future + timedelta(days=30), future + timedelta(days=30) + dur); check("create C pending", st, 201)
from _harness import sql
sql(f"UPDATE reservation SET starts_at='{past.strftime('%Y-%m-%d %H:%M:%S')}', ends_at='{future.strftime('%Y-%m-%d %H:%M:%S')}' WHERE id={c['id']}")
code, body = post_webhook(event("pi_C", "succeeded", c["id"]))
check("webhook succeeded (covers now) -> 200", code, 200, body)
check("  locker now occupied", get_locker(ok["id"]).get("status"), "occupied")

print("\n## unknown reservation is acked (200) so Stripe stops retrying")
code, body = post_webhook(event("pi_X", "succeeded", 99999999))
check("unknown reservation -> 200 ack", code, 200, body)

summary()
