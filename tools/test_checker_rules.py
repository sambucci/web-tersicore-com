#!/usr/bin/env python3
"""
Rule tests for tools/link_check.py, against the eleven mechanical checker rules.

Run: python tools/test_checker_rules.py
These are offline and deterministic. They exercise the matching and dispatch
logic, which is where rules 4 and 5 fail silently.
"""
import importlib.util
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location("lc", os.path.join(HERE, "link_check.py"))
lc = importlib.util.module_from_spec(spec)
spec.loader.exec_module(lc)

fails = []


def check(name, got, want):
    ok = got == want
    print(f"  {'pass' if ok else 'FAIL'}  {name}   got={got!r} want={want!r}")
    if not ok:
        fails.append(name)


print("RULE 4: host matching on boundaries, not substrings")
# The canonical false positive the rule names.
check("jazzbarisax.com does NOT match x.com",
      lc.host_matches("jazzbarisax.com", "x.com"), False)
check("jazzbarisax.com does NOT match archive.org",
      lc.host_matches("jazzbarisax.com", "archive.org"), False)
# A correct implementation still matches real subdomains.
check("uk.glassdoor.com matches glassdoor.com",
      lc.host_matches("uk.glassdoor.com", "glassdoor.com"), True)
check("archive.org matches archive.org",
      lc.host_matches("archive.org", "archive.org"), True)
check("web.archive.org matches archive.org",
      lc.host_matches("web.archive.org", "archive.org"), True)
check("notarchive.org does NOT match archive.org",
      lc.host_matches("notarchive.org", "archive.org"), False)
check("archive.org.example.net does NOT match archive.org",
      lc.host_matches("archive.org.example.net", "archive.org"), False)
check("notgoatcounter.com does NOT match goatcounter.com",
      lc.host_matches("notgoatcounter.com", "goatcounter.com"), False)
check("trailing dot is tolerated",
      lc.host_matches("archive.org.", "archive.org"), True)

print("\nRULE 4: the identity dispatch itself is anchored")
# An unanchored pattern would treat these as archive.org items.
check("evil host carrying archive.org in its PATH is not probed as archive.org",
      lc.identity_archive_org("https://evil.example/archive.org/details/x", "t", "a"), None)
check("notarchive.org is not probed as archive.org",
      lc.identity_archive_org("https://notarchive.org/details/x", "t", "a"), None)
check("gallica lookalike host is not probed as gallica",
      lc.identity_gallica("https://notgallica.bnf.fr.example/ark:/12148/x", "t", "a"), None)

print("\nRULE 5: a bad match must not SUPPRESS, and both call orders are checked")
# Order A: right host, unparseable path. Must be an error, never a silent pass.
r = lc.identity_archive_org("https://archive.org/some-other-shape", "t", "a")
check("archive.org URL with no parseable identifier returns probe_error",
      r[0] if r else None, "probe_error")
r = lc.identity_gallica("https://gallica.bnf.fr/not-an-ark", "t", "a")
check("gallica URL with no parseable ark returns probe_error",
      r[0] if r else None, "probe_error")
# Order B: wrong host entirely. Must decline, so another matcher can try.
check("a host we cannot probe declines (None) rather than erroring",
      lc.identity_archive_org("https://www.loc.gov/item/x", "t", "a"), None)
# And the aggregate dispatcher must not swallow a probe_error into a pass.
state, _ = lc.identity_check("https://archive.org/some-other-shape", "t", "a")
check("identity_check surfaces probe_error rather than no_probe",
      state, "probe_error")
state, _ = lc.identity_check("https://www.loc.gov/item/x", "t", "a")
check("identity_check returns no_probe for a genuinely unprobeable host",
      state, "no_probe")

print("\nRULE 7: no global verification switch above the ladder")
src = open(os.path.join(HERE, "link_check.py"), encoding="utf-8").read()
# Strip the docstrings/comments that legitimately DISCUSS these tokens.
code = "\n".join(l for l in src.splitlines() if not l.lstrip().startswith("#"))
check("no ssl.CERT_NONE", "CERT_NONE" in code, False)
check("no verify=False", bool(re.search(r"verify\s*=\s*False", code)), False)
check("no check_hostname=False", bool(re.search(r"check_hostname\s*=\s*False", code)), False)
# -k must appear only inside the rung 3 branch.
relaxed_lines = [l for l in code.splitlines() if '"-k"' in l]
check("the -k flag appears exactly once", len(relaxed_lines), 1)
lines = code.splitlines()
ki = next(i for i, l in enumerate(lines) if '"-k"' in l)
check("that one -k sits inside an 'if relaxed' branch",
      any("if relaxed" in l for l in lines[max(0, ki - 3):ki]), True)
check("the -k line is indented deeper than its guard",
      len(lines[ki]) - len(lines[ki].lstrip()) >
      min(len(l) - len(l.lstrip()) for l in lines[max(0, ki - 3):ki] if "if relaxed" in l), True)

print("\nRULE 1 and 2: the ladder confirms and retries before reporting")
import inspect
srcf = inspect.getsource(lc.check_reachable)
check("HEAD result is never returned as a failure",
      srcf.count('fetch(url, "HEAD"') == 1, True)
check("a real GET follows the HEAD screening pass",
      'fetch(url, "GET"' in srcf, True)
check("a second spaced GET runs before any failure is reported",
      srcf.count('fetch(url, "GET"') >= 2, True)
check("the ladder sleeps between the two GETs", "time.sleep" in srcf, True)
check("rung 2 and rung 3 both exist", srcf.count("_curl(url") , 2)

print("\nRULE 8: controls are disjoint from the inventory")
try:
    inv = {i["url"] for i in lc.inventory()}
    overlap = [u for _, u, _ in lc.CONTROLS if u in inv]
    check("no control URL appears in the inventory", overlap, [])
except Exception as e:                                       # noqa: BLE001
    print(f"  skip  inventory not readable here: {e}")

print()
if fails:
    print(f"{len(fails)} FAILED: {fails}")
    sys.exit(1)
print("all rule tests passed")
