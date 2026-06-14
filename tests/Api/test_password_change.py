#!/usr/bin/env python3
"""Password change: validates current password + strength, then the new password works for login."""
import sys, os; sys.path.insert(0, os.path.dirname(__file__))
from _harness import *
from _harness import _req

reload_fixtures()
EMAIL = "customer1@openinnov.com"
T = login(EMAIL, "password")

def change(cur, new, token=T):
    return _req("POST", "/api/customers/me/password", token, {"currentPassword": cur, "newPassword": new})

def try_login(pw):
    return _req("POST", "/api/login", body={"email": EMAIL, "password": pw})[0]

print("## validation")
check("wrong current password -> 403", change("wrongpass", "newStrongPass1")[0], 403)
check("too short new password -> 422", change("password", "short")[0], 422)
check("same as current -> 422", change("password", "password")[0], 422)
check("missing fields -> 422", _req("POST", "/api/customers/me/password", T, {"currentPassword": "password"})[0], 422)

print("\n## happy path")
check("valid change -> 204", change("password", "newStrongPass1")[0], 204)
check("login with NEW password -> 200", try_login("newStrongPass1"), 200)
check("login with OLD password -> 401", try_login("password"), 401)

print("\n## unauthenticated")
check("no token -> 401", _req("POST", "/api/customers/me/password", None, {"currentPassword": "x", "newPassword": "yyyyyyyy"})[0], 401)

summary()
