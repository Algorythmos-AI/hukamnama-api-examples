#!/usr/bin/env python3
"""A stand-in for the Hukamnama API, for running the examples and tests offline.

    python3 tools/mock_api.py [--port 8787] [--mode published|pending|tampered]

It serves the files in fixtures/, which are real answers of the API's code for one day
(11 November 2026, Ang 680). Modes:

  published   /today is that day (the default)
  pending     /today is a pending answer; the day is still served under its own date
  tampered    one line of the day is altered, so its hash no longer matches: every example
              must refuse to show it

Standard library only. Never use this as a source of Gurbani: it exists to test code.
"""
import argparse
import hashlib
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

FIX = Path(__file__).resolve().parents[1] / "fixtures"


def load(name):
    return json.loads((FIX / name).read_text(encoding="utf-8"))


def make_handler(mode):
    day = load("today.json")
    if mode == "tampered":
        day["lines"][1]["gurmukhi"] += " "          # one extra space: the hash must catch it
    date = day["amritsar_date"]
    routes = {
        "/v1/hukamnama/today": load("pending.json") if mode == "pending" else day,
        f"/v1/hukamnama/{date}": day,
        "/v1/hukamnama/index": load("index.json"),
        "/v1/hukamnama": load("list.json"),
        "/v1/status": load("status.json"),
    }

    class Handler(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def do_OPTIONS(self):
            self.send_response(204)
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Access-Control-Allow-Headers", "If-None-Match")
            self.end_headers()

        def do_GET(self):
            body = routes.get(urlparse(self.path).path)
            status = 200 if body is not None else 404
            if body is None:
                body = {"error": "not found", "code": "not_found", "request_id": "mock"}
            raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
            etag = '"' + hashlib.sha256(raw).hexdigest()[:32] + '"'
            if status == 200 and self.headers.get("If-None-Match") == etag:
                self.send_response(304)
                self.send_header("ETag", etag)
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                return
            self.send_response(status)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(raw)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Access-Control-Expose-Headers", "ETag")
            if status == 200:
                self.send_header("ETag", etag)
                self.send_header("Cache-Control", "public, max-age=60")
            self.end_headers()
            self.wfile.write(raw)

    return Handler


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--port", type=int, default=8787)
    ap.add_argument("--mode", choices=["published", "pending", "tampered"], default="published")
    args = ap.parse_args()
    server = ThreadingHTTPServer(("127.0.0.1", args.port), make_handler(args.mode))
    print(f"mock Hukamnama API ({args.mode}) on http://127.0.0.1:{args.port}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
