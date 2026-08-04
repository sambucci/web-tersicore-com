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


# ------------------------------------------------------------------- fetching
def _opener():
    ctx = ssl.create_default_context()
    return urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx))


def fetch(url: str, method: str = "GET", timeout: int = TIMEOUT):
    """Return (status, final_url, body_head, error_kind, error_text)."""
    req = urllib.request.Request(url, method=method, headers={
        "User-Agent": UA,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "it,en;q=0.8",
    })
    try:
        with _opener().open(req, timeout=timeout) as r:
            body = b""
            if method == "GET":
                body = r.read(65536)
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


def check_reachable(url: str):
    """HEAD first, fall back to GET. Retries with backoff on transient failures."""
    last = None
    for attempt in range(RETRIES):
        status, final, body, kind, err = fetch(url, "HEAD", TIMEOUT)
        # Many institutional servers reject or mishandle HEAD.
        if status in (403, 405, 501, None):
            status, final, body, kind, err = fetch(url, "GET", TIMEOUT)
        last = (status, final, body, kind, err)
        if status and 200 <= status < 400:
            return last
        if kind == "dns":
            return last                      # deterministic, do not retry
        if status and status in (401, 403, 404, 410):
            return last                      # deterministic answer from the host
        time.sleep(1.5 * (attempt + 1) + random.random())
    return last


# --------------------------------------------------------------- identity check
def norm_tokens(s: str, minlen: int = 4) -> set:
    s = re.sub(r"&[a-z]+;", " ", (s or "").lower())
    s = re.sub(r"[^a-z0-9À-ſ ]+", " ", s)
    return {w for w in s.split() if len(w) >= minlen}


STOP = {"della", "delle", "dell", "dans", "pour", "avec", "that", "with", "which",
        "dance", "dancing", "danse", "ballo", "balli", "danza", "tanz", "arte",
        "art", "the", "und", "der", "des", "les", "nach", "sur"}


def identity_archive_org(url: str, titolo: str, autore: str):
    m = re.search(r"archive\.org/(?:details|download)/([^/?#]+)", url)
    if not m:
        return None
    ident = m.group(1)
    status, _, body, kind, err = fetch(f"https://archive.org/metadata/{ident}", "GET", TIMEOUT)
    if status != 200 or not body:
        return ("unverifiable", f"metadata API returned {status or kind}")
    try:
        md = json.loads(body).get("metadata", {})
    except Exception:
        return ("unverifiable", "metadata API returned unparseable JSON")
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
    m = re.search(r"gallica\.bnf\.fr/ark:/12148/([^/?#]+)", url)
    if not m:
        return None
    ark = m.group(1)
    status, _, body, kind, err = fetch(
        f"https://gallica.bnf.fr/services/OAIRecord?ark={ark}", "GET", TIMEOUT)
    if status != 200 or "<dc:title>" not in (body or ""):
        # Fall back to SRU, which answers when OAIRecord does not.
        q = urllib.parse.quote(f'gallica all "{titolo[:60]}"')
        status2, _, body2, _, _ = fetch(
            f"https://gallica.bnf.fr/SRU?operation=searchRetrieve&version=1.2"
            f"&maximumRecords=5&query={q}", "GET", TIMEOUT)
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
    return items


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
        return "gated", f"HTTP {status}: the host refuses anonymous clients, so reachability cannot be judged from here"
    if status in (429,):
        return "gated", "HTTP 429: rate-limited, so reachability cannot be judged from this run"
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
        status, final, body, kind, err = check_reachable(it["url"])
        last_hit[host] = time.time()
        state, detail = classify(status, kind, err, body)

        ident_state, ident_detail = ("skipped", "")
        if do_identity and state == "ok" and it["kind"] in ("fonte", "edizione"):
            wait = PER_HOST_DELAY - (time.time() - last_hit[host])
            if wait > 0:
                time.sleep(wait)
            ident_state, ident_detail = identity_check(it["url"], it["titolo"], it["autore"])
            last_hit[host] = time.time()

        results.append({**it, "state": state, "detail": detail, "http": status,
                        "final_url": final, "identity": ident_state, "identity_detail": ident_detail})
        print(f"[{i}/{len(items)}] {state:<12} {ident_state:<12} {it['url'][:78]}", flush=True)
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
    ident_bad = [r for r in results if r["identity"] in ("mismatch", "gone")]
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
    for k in ("ok", "gated", "server_error", "http_dead", "dns_dead", "unreachable", "tls_error", "other"):
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
        status, final, body, kind, err = check_reachable(url)
        state, _ = classify(status, kind, err, body)
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
