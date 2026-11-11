#!/usr/bin/env python3
"""Print the Hukamnama from Sri Harmandir Sahib, checked against its hash.

    python3 python/hukamnama.py

Standard library only. Set HUKAMNAMA_API to use another host (the tests use a local stand-in).

What it shows you how to do:
  1. ask for today's Hukamnama (today is the date in Amritsar);
  2. when it is not published yet, read the latest one and show it under ITS OWN date;
  3. prove the lines against range_sha256 before printing a word, and print nothing if they fail;
  4. show the whole shabad, the citation and the credit.
"""
import hashlib
import json
import os
import sys
import urllib.request
from datetime import datetime, timedelta, timezone

API = os.environ.get("HUKAMNAMA_API", "https://api.gurbanisoul.com").rstrip("/")
AMRITSAR = timezone(timedelta(hours=5, minutes=30))     # a fixed offset: India has no summer time


class NotProven(Exception):
    """The lines do not match their hash. Show nothing."""


def get(path):
    req = urllib.request.Request(API + path, headers={"User-Agent": "hukamnama-api-examples (python)"})
    with urllib.request.urlopen(req, timeout=15) as res:
        return json.load(res)


def verify(day):
    """Raise NotProven unless the day's lines hash to its range_sha256."""
    text = "\n".join(line["gurmukhi"] for line in day["lines"])
    if hashlib.sha256(text.encode("utf-8")).hexdigest() != day["range_sha256"]:
        raise NotProven(day["amritsar_date"])
    return day


def latest_published():
    """Today's Hukamnama, or the most recent one when today's is not here yet. None when there is none."""
    day = get("/v1/hukamnama/today")
    if day["status"] != "published":
        if not day.get("latest"):
            return None
        day = get("/v1/hukamnama/" + day["latest"])
        if day["status"] != "published":
            return None
    return verify(day)


def is_today(day, now=None):
    """Call it today's only while its date is still the date in Amritsar."""
    now = now or datetime.now(timezone.utc)
    return day["amritsar_date"] == now.astimezone(AMRITSAR).date().isoformat()


def render(day, now=None):
    label = "Today's Hukamnama" if is_today(day, now) else "Hukamnama"
    out = ["Hukamnama · Sri Harmandir Sahib", f"{label}, {day['amritsar_date']} (Amritsar)", ""]
    out += [line["gurmukhi"] for line in day["lines"]]          # every line, exactly as it arrived
    out += ["", day["citation"], f"{day['attribution']['text']}: {day['attribution']['url']}"]
    return "\n".join(out)


def main():
    try:
        day = latest_published()
    except NotProven:
        print("The Hukamnama could not be verified, so it is not shown. Please try again later.", file=sys.stderr)
        return 1
    if day is None:
        print("No Hukamnama is available yet.", file=sys.stderr)
        return 2
    print(render(day))
    return 0


if __name__ == "__main__":
    sys.exit(main())
