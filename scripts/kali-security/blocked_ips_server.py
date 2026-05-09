#!/usr/bin/env python3
"""
blocked_ips_server — minimal HTTP server that serves blocked_ips.txt on a fixed
port for OPNsense's URLTable alias to fetch from. Logs every request with
client IP + user-agent so we can see OPNsense polling.

Defaults:
  - Bind: 172.31.0.100:8080 (Kali WAN-side IP)
  - Document root: ~/.openclaw/blocked_ips/
  - Log: ~/.config/openclaw-autoblock/http_server.log

Only serves files under the document root. No directory listing. No POST.
"""
import http.server
import socketserver
import os
import sys
import datetime
from datetime import timezone
try:
    from zoneinfo import ZoneInfo
    LOCAL_TZ = ZoneInfo("America/Los_Angeles")
except Exception:
    LOCAL_TZ = None

DOC_ROOT = os.path.expanduser("~/.openclaw/blocked_ips")
LOG_FILE = os.path.expanduser("~/.config/openclaw-autoblock/http_server.log")
BIND_HOST = "172.31.0.100"
BIND_PORT = 8080
ALLOWED_FILES = {"blocked_ips.txt", "blocked_mac.txt"}


def _ts_local():
    now = datetime.datetime.now(timezone.utc)
    if LOCAL_TZ is not None:
        return now.astimezone(LOCAL_TZ).strftime("%Y-%m-%d %H:%M:%S %Z")
    return now.strftime("%Y-%m-%d %H:%M:%S UTC")


def log(msg):
    line = f"{_ts_local()} | {msg}"
    print(line, flush=True)
    try:
        with open(LOG_FILE, "a") as f:
            f.write(line + "\n")
    except Exception:
        pass


class AliasFileHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        path = self.path.lstrip("/")
        # Strip any query string
        if "?" in path:
            path = path.split("?", 1)[0]
        if path not in ALLOWED_FILES:
            self.send_error(404, "not allowed")
            log(f"DENY  {self.client_address[0]} GET /{path}  (not in allowlist)")
            return
        full = os.path.join(DOC_ROOT, path)
        if not os.path.isfile(full):
            # Serve empty file rather than 404, so OPNsense doesn't error
            content = b""
        else:
            with open(full, "rb") as f:
                content = f.read()
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Content-Length", str(len(content)))
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate")
        self.end_headers()
        self.wfile.write(content)
        ua = self.headers.get("User-Agent", "-")
        nlines = content.count(b"\n")
        log(f"OK    {self.client_address[0]} GET /{path}  bytes={len(content)} lines={nlines} ua={ua!r}")

    def do_POST(self):
        self.send_error(405, "method not allowed")
        log(f"DENY  {self.client_address[0]} POST {self.path}")

    def log_message(self, format, *args):
        # Suppress default stderr log; we log explicitly above
        return


def main():
    os.makedirs(DOC_ROOT, exist_ok=True)
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
    # Ensure blocked_ips.txt exists (empty if no blocks)
    bf = os.path.join(DOC_ROOT, "blocked_ips.txt")
    if not os.path.isfile(bf):
        open(bf, "w").close()
    log(f"START blocked_ips_server pid={os.getpid()} bind={BIND_HOST}:{BIND_PORT} doc_root={DOC_ROOT}")
    with socketserver.TCPServer((BIND_HOST, BIND_PORT), AliasFileHandler) as httpd:
        httpd.allow_reuse_address = True
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            log("STOP  blocked_ips_server (Ctrl-C)")


if __name__ == "__main__":
    main()
