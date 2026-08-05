#!/usr/bin/env python3
"""
tersicore.com link checker.

The whole point of this site is that every scan link lands on the specific work,
so this checker does two independent things per URL:

  1. REACHABILITY. Does the URL answer at all, and how.
  2. IDENTITY. For the two hosts that carry almost the whole inventory
     (archive.org and gallica.bnf.fr), re-query the institution's own metadata
     API and confirm the record still names the work we claim it does. A
     repository redesign returns 200 on a catalogue page that no longer holds
     the scan, so reachability alone reads healthy while the citation is gone.

Nothing here deletes anything. Every finding is a proposal for a human.

Usage:
  python tools/link_check.py [--out audit/link-check-report.md] [--json audit/link-check.audit.json]
  python tools/link_check.py --controls-only     # exercise the control set and exit
"""
from __future__ import annotations

import argparse
import json
import os
import random
import re
import socket
import ssl
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import datetime, timezone

# An honest product token. Siteground's WAF blocks browser-like strings before
# PHP runs, and library defaults look like scrapers to the institutions.
UA = "tersicore-linkcheck/1.0 (+https://tersicore.com)"

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(ROOT, "public", "data")

TIMEOUT = 40
RETRIES = 3
PER_HOST_DELAY = 1.5           # institutions rate-limit; pace per host
MAX_REDIRECTS = 6

# ---------------------------------------------------------------- control set
#
# Every control is disjoint from the real inventory. The set deliberately
# includes a healthy control under the SAME PATH PREFIX as the URLs it
# validates: one platform can 404 an entire prefix to anonymous clients while
# other prefixes on the same host answer 200, and a control that does not share
# the prefix cannot see that.
CONTROLS = [
    # (label, url, expected outcome)
    # Both healthy controls were confirmed to exist against the institution's own
    # metadata API at the time they were chosen, and both are outside the inventory.
    ("healthy, same path prefix as the archive.org inventory (/details/)",
     "https://archive.org/details/lombredemardigra00four", "ok"),
    ("healthy, same path prefix as the gallica inventory (/ark:/12148/)",
     "https://gallica.bnf.fr/ark:/12148/bd6t5334701z", "ok"),
    ("healthy, unrelated host",
     "https://www.loc.gov/", "ok"),
    ("HTTP-dead: 404 from a live host",
     "https://archive.org/details/tersicore-control-definitely-not-an-item-9f3b1", "http_dead"),
    ("DNS-dead: RFC 2606 reserved name that cannot resolve",
     "https://tersicore-control.invalid/scan", "dns_dead"),
]


# --------------------------------------------------- rule 4: boundary matching
def url_host(url: str) -> str:
    return (urllib.parse.urlparse(url).hostname or "").lower().rstrip(".")


def host_matches(host: str, pattern: str) -> bool:
    """
    Host equality or a true subdomain. Substring matching is a false-positive
    generator: an entry for 'x.com' matches inside 'jazzbarisax.com' and
    relabels a live source. 'uk.glassdoor.com' must still match 'glassdoor.com'.
    """
    host = (host or "").lower().rstrip(".")
    pattern = (pattern or "").lower().lstrip(".").rstrip(".")
    if not host or not pattern:
        return False
    return host == pattern or host.endswith("." + pattern)


# ------------------------------------------------------------------- fetching
def _ssl_context():
    """
    Trust the UNION of the system CA store and certifi's bundle.

    Neither alone is enough on Windows, and each fails against a different half
    of this inventory. The system store rejects commons.wikimedia.org; certifi's
    bundle alone rejects archive.org and gallica.bnf.fr. Loading only certifi
    turned 63 healthy institutional URLs into TLS failures and, correctly, made
    the control set fail. Verification stays strict either way: this widens the
    set of trusted roots, it never disables checking.
    """
    ctx = ssl.create_default_context()          # system roots
    try:
        import certifi
        ctx.load_verify_locations(cafile=certifi.where())   # plus certifi's roots
    except ImportError:
        pass
    return ctx


_CTX = _ssl_context()


def _opener():
    return urllib.request.build_opener(urllib.request.HTTPSHandler(context=_CTX))


def fetch(url: str, method: str = "GET", timeout: int = TIMEOUT, full: bool = False):
    """
    Return (status, final_url, body, error_kind, error_text).

    `full` reads the whole body instead of the first 64KB. Metadata responses
    must use it: archive.org returns 75 to 80KB for the Library of Congress
    items, so a capped read hands json.loads a truncated document and ten live
    sources report as unverifiable on every single run. Reachability checks stay
    capped, since they only ever need the status line.
    """
    req = urllib.request.Request(url, method=method, headers={
        "User-Agent": UA,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "it,en;q=0.8",
    })
    try:
        with _opener().open(req, timeout=timeout) as r:
            body = b""
            if method == "GET":
                body = r.read() if full else r.read(65536)
            return r.status, r.geturl(), body.decode("utf-8", "replace"), None, ""
    except urllib.error.HTTPError as e:
        try:
            body = e.read(16384).decode("utf-8", "replace")
        except Exception:
            body = ""
        return e.code, url, body, "http", f"HTTP {e.code}"
    except urllib.error.URLError as e:
        reason = e.reason
        kind = "dns" if isinstance(reason, socket.gaierror) else "network"
        if isinstance(reason, ssl.SSLError):
            kind = "tls"
        return None, url, "", kind, str(reason)
    except socket.timeout:
        return None, url, "", "timeout", "timed out"
    except Exception as e:                                  # noqa: BLE001
        return None, url, "", "network", f"{type(e).__name__}: {e}"


def _curl(url: str, relaxed: bool):
    """
    Rungs 2 and 3 of the probe ladder. `relaxed` belongs to rung 3 ALONE and
    lives inside this call: a blanket -k at the top of the file would pin every
    URL to the bottom rung and collapse "alive" and "alive with a broken
    certificate" into one verdict with no visible symptom.
    """
    cmd = ["curl", "-s", "-L", "-o", os.devnull, "-A", UA,
           "-m", str(TIMEOUT), "-w", "%{http_code}", url]
    if relaxed:
        cmd.insert(1, "-k")
        if sys.platform == "win32":
            cmd.insert(1, "--ssl-revoke-best-effort")
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=TIMEOUT + 20)
    except Exception as e:                                   # noqa: BLE001
        return None, f"curl failed: {type(e).__name__}"
    code = (r.stdout or "").strip()[-3:]
    if code.isdigit() and code != "000":
        return int(code), ""
    return None, (r.stderr or "curl produced no status").strip()[:160]


# HTTP statuses that are an unambiguous answer from a host that already
# completed a TLS handshake. Rungs 2 and 3 differ from rung 1 only in client and
# TLS behaviour, so they cannot turn one of these into a 200; escalating them
# only adds load. Transport failures and 5xx DO climb the ladder.
DEFINITE = {400, 401, 403, 404, 410, 451}


def check_reachable(url: str):
    """
    Probe ladder, rules 1, 2 and 7.

      screening : HEAD on rung 1. A pass here is a pass; it is never a failure.
      rung 1    : strict urllib GET. Confirms every candidate failure (rule 1).
      rule 2    : a second spaced GET before any failure is allowed to be reported.
      rung 2    : strict curl, a different TLS and HTTP client entirely.
      rung 3    : relaxed TLS. Answering only here means alive with a TLS caveat.

    Returns (status, final_url, body, kind, err, rung).
    """
    # --- screening pass. HEAD 403/405/501/404 means nothing on its own.
    st, fin, body, kind, err = fetch(url, "HEAD", TIMEOUT)
    if st and 200 <= st < 400:
        return st, fin, body, kind, err, "head"

    # --- rung 1, real GET. Rule 1: no candidate failure is reported unconfirmed.
    st, fin, body, kind, err = fetch(url, "GET", TIMEOUT)
    if st and 200 <= st < 400:
        return st, fin, body, kind, err, "rung1"

    # --- rule 2: one failure is noise. Require a second, spaced.
    time.sleep(2.5 + random.random() * 2)
    st2, fin2, body2, kind2, err2 = fetch(url, "GET", TIMEOUT)
    if st2 and 200 <= st2 < 400:
        return st2, fin2, body2, kind2, err2, "rung1-retry"
    st, fin, body, kind, err = st2, fin2, body2, kind2, err2

    # A host that answered with a definite status has a working transport.
    if st in DEFINITE:
        return st, fin, body, kind, err, "rung1"

    # --- rung 2, strict curl.
    c_st, c_err = _curl(url, relaxed=False)
    if c_st and 200 <= c_st < 400:
        return c_st, url, "", None, "", "rung2"

    # --- rung 3, relaxed TLS. Alive here is alive, with a caveat that is stated.
    r_st, r_err = _curl(url, relaxed=True)
    if r_st and 200 <= r_st < 400:
        return r_st, url, "", "tls_caveat",                "answers only with relaxed TLS: the chain is incomplete or the "                "revocation check fails, which browsers repair and strict clients refuse", "rung3"

    return st, fin, body, kind, err, "dead"


# --------------------------------------------------------------- identity check
def norm_tokens(s: str, minlen: int = 4) -> set:
    s = re.sub(r"&[a-z]+;", " ", (s or "").lower())
    s = re.sub(r"[^a-z0-9À-ſ ]+", " ", s)
    return {w for w in s.split() if len(w) >= minlen}


STOP = {"della", "delle", "dell", "dans", "pour", "avec", "that", "with", "which",
        "dance", "dancing", "danse", "ballo", "balli", "danza", "tanz", "arte",
        "art", "the", "und", "der", "des", "les", "nach", "sur"}


def identity_archive_org(url: str, titolo: str, autore: str):
    # Host is matched on a boundary, never as a substring, and the path pattern
    # is anchored. An unanchored r"archive\.org/details/..." also matches
    # notarchive.org and evil.example/archive.org/details/x.
    if not host_matches(url_host(url), "archive.org"):
        return None
    m = re.match(r"^https?://[^/]+/(?:details|download)/([^/?#]+)", url)
    if not m:
        # Rule 5: the host IS probeable and the identifier did not extract. That
        # is an instrument fault, not a pass. Returning "no probe" here would
        # suppress the identity check silently and the entry would read clean.
        return ("probe_error", "archive.org URL whose item identifier did not parse")
    ident = m.group(1)
    # Read the FULL body: these responses run to 75-80KB and a capped read
    # yields truncated JSON. Retry as well, for genuine transients.
    md, last = None, ""
    for attempt in range(3):
        status, _, body, kind, err = fetch(f"https://archive.org/metadata/{ident}", "GET", TIMEOUT, full=True)
        if status != 200 or not body:
            last = f"metadata API returned {status or kind}"
        else:
            try:
                md = json.loads(body).get("metadata", {})
                break
            except Exception:
                last = "metadata API returned unparseable JSON"
        time.sleep(2.0 * (attempt + 1))
    if md is None:
        return ("unverifiable", last)
    if not md:
        return ("gone", f"archive.org has no item with identifier '{ident}'")
    got_t = md.get("title", "")
    got_c = md.get("creator", "")
    if isinstance(got_c, list):
        got_c = "; ".join(got_c)
    th = len((norm_tokens(titolo) - STOP) & (norm_tokens(got_t) - STOP))
    ah = len(norm_tokens(autore) & norm_tokens(str(got_c)))
    if th or ah:
        return ("ok", f'record reads "{str(got_t)[:110]}" / {str(got_c)[:60]}')
    return ("mismatch", f'record reads "{str(got_t)[:110]}" / {str(got_c)[:60]}')


def identity_gallica(url: str, titolo: str, autore: str):
    if not host_matches(url_host(url), "gallica.bnf.fr"):
        return None
    m = re.match(r"^https?://[^/]+/ark:/12148/([^/?#]+)", url)
    if not m:
        return ("probe_error", "Gallica URL whose ark did not parse")
    ark = m.group(1)
    status, _, body, kind, err = fetch(
        f"https://gallica.bnf.fr/services/OAIRecord?ark={ark}", "GET", TIMEOUT, full=True)
    if status != 200 or "<dc:title>" not in (body or ""):
        # Fall back to SRU, which answers when OAIRecord does not.
        q = urllib.parse.quote(f'gallica all "{titolo[:60]}"')
        status2, _, body2, _, _ = fetch(
            f"https://gallica.bnf.fr/SRU?operation=searchRetrieve&version=1.2"
            f"&maximumRecords=5&query={q}", "GET", TIMEOUT, full=True)
        if status2 == 200 and ark in (body2 or ""):
            return ("ok", "confirmed via Gallica SRU (OAIRecord did not answer)")
        return ("unverifiable", f"OAIRecord returned {status or kind}, SRU did not confirm the ark")
    got_t = " ".join(re.findall(r"<dc:title>(.*?)</dc:title>", body, re.S))
    got_c = " ".join(re.findall(r"<dc:creator>(.*?)</dc:creator>", body, re.S))
    th = len((norm_tokens(titolo) - STOP) & (norm_tokens(got_t) - STOP))
    ah = len(norm_tokens(autore) & norm_tokens(got_c))
    if th or ah:
        return ("ok", f'record reads "{re.sub(chr(60)+"[^"+chr(62)+"]*"+chr(62), "", got_t)[:110]}"')
    return ("mismatch", f'record reads "{re.sub(chr(60)+"[^"+chr(62)+"]*"+chr(62), "", got_t)[:110]}"')


def identity_check(url, titolo, autore):
    for fn in (identity_archive_org, identity_gallica):
        r = fn(url, titolo, autore)
        if r is not None:
            return r
    return ("no_probe", "no metadata API known for this host")


# ------------------------------------------------------------------ inventory
def load(name):
    p = os.path.join(DATA, name)
    if not os.path.isfile(p):
        return []
    with open(p, encoding="utf-8") as fh:
        return json.load(fh)


def inventory():
    """Every external URL the site publishes, with the work it claims to point at."""
    items = []
    for f in load("fonti.json"):
        u = (f.get("scansione") or {}).get("url")
        if u:
            items.append({"kind": "fonte", "id": f["id"], "slug": f["slug"], "url": u,
                          "titolo": f.get("titolo", ""), "autore": f.get("autore", ""),
                          "istituzione": (f.get("scansione") or {}).get("istituzione", ""),
                          "page": f"/fonti/{f['slug']}/"})
        for e in f.get("altre_edizioni") or []:
            if isinstance(e, dict) and e.get("url"):
                items.append({"kind": "edizione", "id": f["id"], "slug": f["slug"], "url": e["url"],
                              "titolo": f.get("titolo", ""), "autore": f.get("autore", ""),
                              "istituzione": e.get("istituzione", ""),
                              "page": f"/fonti/{f['slug']}/"})
    for o in load("iconografia.json"):
        if o.get("url_opera"):
            items.append({"kind": "opera", "id": o.get("id", o.get("slug")), "slug": o["slug"],
                          "url": o["url_opera"], "titolo": o.get("titolo", ""),
                          "autore": o.get("autore", ""), "istituzione": o.get("istituzione", ""),
                          "page": f"/iconografia/{o['slug']}/"})
    for c in load("credits.json"):
        for key in ("source_url", "license_url"):
            if c.get(key):
                items.append({"kind": "credito", "id": c.get("file", ""), "slug": c.get("file", ""),
                              "url": c[key], "titolo": c.get("title", ""),
                              "autore": c.get("author", ""), "istituzione": c.get("institution", ""),
                              "page": "/crediti/"})

    # A Commons file page is both an artwork's url_opera and its credit's
    # source_url, and one licence page backs many credits. Checking the same URL
    # twice costs a request against a rate-limited host and prints every finding
    # twice. Keep the first occurrence and record where else it appears.
    seen, deduped = {}, []
    for it in items:
        if it["url"] in seen:
            other = seen[it["url"]]
            if it["page"] not in other["also_on"]:
                other["also_on"].append(it["page"])
            continue
        it["also_on"] = []
        seen[it["url"]] = it
        deduped.append(it)
    return deduped


# --------------------------------------------------------------------- runner
def classify(status, kind, err, body):
    if kind == "dns":
        return "dns_dead", f"the hostname does not resolve ({err})"
    if kind in ("timeout", "network"):
        return "unreachable", f"no answer ({err})"
    if kind == "tls":
        return "tls_error", f"TLS failure ({err})"
    if status is None:
        return "unreachable", f"no answer ({err})"
    if status in (401, 403):
        return "gated", (f"HTTP {status} on every probe: the host refuses anonymous clients, "
                         "so reachability cannot be judged from here")
    if status == 429:
        # Rule 5 separates these two on purpose. A host refusing every probe the
        # same way is handled by the unverifiable classification and needs
        # nothing further. A host that answers sometimes is a rate limiter, and
        # the report has to name it rather than quietly excluding it.
        return "rate_limited", ("HTTP 429: this host rate-limits. It stays in the inventory and is "
                                "named here rather than excluded, which would suppress a real failure later")
    if status in (404, 410):
        return "http_dead", f"HTTP {status}"
    if 500 <= status < 600:
        return "server_error", f"HTTP {status}: a fault at the host, which may be temporary"
    if 200 <= status < 400:
        return "ok", f"HTTP {status}"
    return "other", f"HTTP {status}"


def run(items, do_identity=True):
    results = []
    last_hit = defaultdict(float)
    for i, it in enumerate(items, 1):
        host = urllib.parse.urlparse(it["url"]).netloc
        wait = PER_HOST_DELAY - (time.time() - last_hit[host])
        if wait > 0:
            time.sleep(wait)
        status, final, body, kind, err, rung = check_reachable(it["url"])
        last_hit[host] = time.time()
        state, detail = classify(status, kind, err, body)
        if rung == "rung3":
            # Rule 7: alive, and the caveat is stated rather than folded into "ok".
            state, detail = "ok_tls_caveat", err

        ident_state, ident_detail = ("skipped", "")
        if do_identity and state in ("ok", "ok_tls_caveat") and it["kind"] in ("fonte", "edizione"):
            wait = PER_HOST_DELAY - (time.time() - last_hit[host])
            if wait > 0:
                time.sleep(wait)
            ident_state, ident_detail = identity_check(it["url"], it["titolo"], it["autore"])
            last_hit[host] = time.time()

        results.append({**it, "state": state, "detail": detail, "http": status, "rung": rung,
                        "final_url": final, "identity": ident_state, "identity_detail": ident_detail})
        print(f"[{i}/{len(items)}] {state:<14} {ident_state:<12} {rung:<11} {it['url'][:66]}", flush=True)
    return results


def environment_line():
    if os.environ.get("GITHUB_ACTIONS"):
        return (f"GitHub Actions runner ({os.environ.get('RUNNER_OS', 'unknown OS')}), "
                f"workflow {os.environ.get('GITHUB_WORKFLOW', '?')}, "
                f"run {os.environ.get('GITHUB_RUN_ID', '?')}")
    return f"local machine, {sys.platform}, python {sys.version.split()[0]}"


HEADER_RULES = """\
## How to read this report

**Nothing here has been deleted or changed. Every line below is a proposal for a human.**

1. A failing link is evidence about the URL. It is not evidence about the work.
   Before removing any entry, search for a successor at the same institution and
   then at another. Institutions move scans and redesign catalogues.
2. Every flag carries its URL and the quoted response that produced it. A flag
   without that evidence is not actionable and should be discarded.
3. Removals are logged append-only, with the date and the quote behind them.
4. `gated` is not `dead`. A 401, 403 or 429 means this environment could not
   judge the link. Those need a human to open them in a browser.
5. `mismatch` means the link answered but the institution's own record no longer
   names the work this site claims. That is the failure mode an HTTP check
   cannot see, and it is the one that matters most here.

6. `tls_error` is almost always the checking machine rather than the host. A
   Windows Python with an incomplete system CA store rejects certificates that
   are perfectly valid. This checker uses certifi's bundle when it is installed;
   if you see a wall of TLS failures against one host, install certifi and
   re-run before believing any of it.

**Every environment is a partial view.** This run happened on: {env}.
Reachability from a datacenter runner and from a residential connection fail in
opposite directions: datacenter IP ranges get blocked by institutional WAFs that
let a home connection through, and some hosts answer a cloud runner faster and
more reliably than a domestic line. A later session cannot correct for that
unless it knows which one it is looking at, so it is stated here rather than
left implicit.
"""


def report(results, controls, started):
    by_state = defaultdict(list)
    for r in results:
        by_state[r["state"]].append(r)
    ident_bad = [r for r in results if r["identity"] in ("mismatch", "gone", "probe_error")]
    ident_unver = [r for r in results if r["identity"] == "unverifiable"]

    L = []
    A = L.append
    A(f"# Link check, tersicore.com")
    A("")
    A(f"Run started {started.strftime('%Y-%m-%d %H:%M')} UTC. "
      f"{len(results)} external URLs in the inventory.")
    A("")
    A(HEADER_RULES.format(env=environment_line()))
    A("")

    A("## Control set")
    A("")
    A("Controls are disjoint from the inventory. If a control misbehaves, the run "
      "itself is suspect and its findings should not be acted on.")
    A("")
    A("| control | expected | observed | verdict |")
    A("|---|---|---|---|")
    ctrl_ok = True
    for label, url, expected, got in controls:
        good = (got == expected)
        ctrl_ok &= good
        A(f"| {label} | `{expected}` | `{got}` | {'pass' if good else '**FAIL**'} |")
    A("")
    A(f"**Control set: {'pass' if ctrl_ok else 'FAIL, treat this run as unreliable'}.**")
    A("")

    A("## Summary")
    A("")
    A("| state | count |")
    A("|---|---|")
    for k in ("ok", "ok_tls_caveat", "gated", "rate_limited", "server_error",
              "http_dead", "dns_dead", "unreachable", "tls_error", "other"):
        if by_state.get(k):
            A(f"| {k} | {len(by_state[k])} |")
    A(f"| identity mismatch or gone | {len(ident_bad)} |")
    A(f"| identity unverifiable | {len(ident_unver)} |")
    A("")

    def table(rows, title, note=""):
        if not rows:
            return
        A(f"## {title}")
        A("")
        if note:
            A(note)
            A("")
        for r in rows:
            A(f"- **{r['titolo']}** ({r['autore']})")
            A(f"  - page: `{r['page']}`")
            A(f"  - url: {r['url']}")
            A(f"  - evidence: `{r['detail']}`" + (f" / `{r['identity_detail']}`" if r["identity_detail"] else ""))
            A(f"  - institution as recorded: {r['istituzione'] or 'not recorded'}")
        A("")

    table(ident_bad, "Identity failures (highest priority)",
          "These URLs answered, and the holding institution's own record no longer names the work "
          "this site claims. Confirm at the institution, then find the successor record.")
    table(by_state.get("http_dead", []), "HTTP dead",
          "The host answered and said the resource is not there. Search for a successor before removing anything.")
    table(by_state.get("dns_dead", []), "DNS dead",
          "The hostname did not resolve. Confirm from a second network before concluding the institution is gone.")
    table(by_state.get("unreachable", []) + by_state.get("tls_error", []), "Unreachable or TLS failure",
          "May be transient or may be this environment. Re-run before acting.")
    table(by_state.get("server_error", []), "Server error",
          "A fault at the host. Usually temporary; re-check on the next run before acting.")
    table(by_state.get("rate_limited", []), "Rate-limited hosts (named, and kept in the inventory)",
          "These answer sometimes. They are named here rather than excluded: adding a flapping host "
          "to an exclusion list is a suppression failure arriving as a housekeeping improvement.")
    table(by_state.get("ok_tls_caveat", []), "Alive with a TLS caveat",
          "These answered only on the relaxed rung of the probe ladder. They are ALIVE. The chain is "
          "incomplete or revocation checking fails, which a browser repairs and a strict client refuses.")
    table(by_state.get("gated", []), "Gated, unverifiable from this environment",
          "The host refused anonymous or automated clients. This says nothing about whether the "
          "scan is still there. A human needs to open these in a browser.")
    if ident_unver:
        table(ident_unver, "Identity unverifiable",
              "Reachable, but the metadata API did not answer well enough to confirm the work. Re-run, then check by hand.")

    A("---")
    A("")
    A(f"Report generated by `tools/link_check.py`. Environment: {environment_line()}.")
    return "\n".join(L), ctrl_ok, len(ident_bad) + len(by_state.get("http_dead", [])) + len(by_state.get("dns_dead", []))


def run_controls():
    out = []
    for label, url, expected in CONTROLS:
        status, final, body, kind, err, rung = check_reachable(url)
        state, _ = classify(status, kind, err, body)
        if rung == "rung3":
            state = "ok_tls_caveat"
        got = "ok" if state == "ok" else state
        out.append((label, url, expected, got))
        print(f"control: {got:<12} (expected {expected:<10}) {label}", flush=True)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=os.path.join(ROOT, "audit", "link-check-report.md"))
    ap.add_argument("--json", default=os.path.join(ROOT, "audit", "link-check.audit.json"))
    ap.add_argument("--controls-only", action="store_true")
    ap.add_argument("--no-identity", action="store_true")
    args = ap.parse_args()

    started = datetime.now(timezone.utc)
    print(f"tersicore link check :: {environment_line()}", flush=True)

    controls = run_controls()
    if args.controls_only:
        bad = [c for c in controls if c[2] != c[3]]
        print("\ncontrol set:", "FAIL" if bad else "pass")
        return 1 if bad else 0

    items = inventory()
    print(f"inventory: {len(items)} external URLs\n", flush=True)
    results = run(items, do_identity=not args.no_identity)

    md, ctrl_ok, n_actionable = report(results, controls, started)
    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        fh.write(md)
    with open(args.json, "w", encoding="utf-8") as fh:
        json.dump({"started": started.isoformat(), "environment": environment_line(),
                   "controls": controls, "results": results}, fh, ensure_ascii=False, indent=1)
    print(f"\nreport -> {args.out}")
    print(f"actionable findings: {n_actionable}; controls: {'pass' if ctrl_ok else 'FAIL'}")

    if os.environ.get("GITHUB_OUTPUT"):
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as fh:
            fh.write(f"actionable={n_actionable}\n")
            fh.write(f"controls_ok={'true' if ctrl_ok else 'false'}\n")
    return 0


if __name__ == "__main__":
    sys.exit(main())
