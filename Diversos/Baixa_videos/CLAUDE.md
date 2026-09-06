# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

The repo runs on Windows in development. Do **not** use `.venv\Scripts\activate`
(ExecutionPolicy blocks it) — call the venv Python directly:

```powershell
py -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements-dev.txt
.\.venv\Scripts\python.exe -m uvicorn app.main:app --reload   # or run.bat
.\.venv\Scripts\python.exe -m pytest                          # full suite (~2s, no network)
.\.venv\Scripts\python.exe -m pytest tests/test_validator.py::test_tiktok_valido
.\.venv\Scripts\python.exe -m pyflakes app                    # lint
```

CI (`.github/workflows/ci.yml`) runs `pyflakes app` + `pytest` on Linux with
FFmpeg installed, plus `docker build`.

FFmpeg is required for audio extraction and for YouTube (video+audio merge). If
it is not on `PATH`, set `FFMPEG_PATH` in `.env`. Without it, audio downloads
return HTTP 501 and video falls back to progressive-only streams.

## Architecture

FastAPI backend + vanilla HTML/CSS/JS frontend. yt-dlp does the actual
extraction/download, isolated behind a service contract — nothing outside
`app/services/ytdlp*.py` imports yt-dlp.

### Adding a platform is the load-bearing pattern

A URL is routed by **auto-detection**, not by a per-platform endpoint:

1. `services/validator.py` `validate_url()` — scheme/size/char checks, host
   **allowlist** (`PLATFORM_HOSTS`, exact match, never substring), anti-SSRF
   (`core/security.assert_public_host`), per-platform path regex, and a
   canonical URL. Query params are dropped except `KEPT_QUERY_KEYS` per platform
   (Facebook/YouTube keep `v` / `story_fbid`).
2. `services/registry.py` `get_service(platform)` → a `YtdlpPlatformService`
   subclass.
3. `services/ytdlp_service.py` — the shared engine: caching, threads, format
   selection, the SSE job flow, audio extraction, temp-dir cleanup.
4. `services/{instagram,tiktok,facebook,youtube}.py` — each is ~50 lines that
   only declare `name`, `_path_types`, `_cookiefile()`, and `_classify(raw)`
   (translate a raw yt-dlp error string into the right `ServiceError`).

So a new platform = new `services/<x>.py` + hosts in `validator.PLATFORM_HOSTS`
(+ path rules) + one line in `registry._REGISTRY`. The core does not change.
Known-but-unsupported hosts get a friendly message via
`validator.KNOWN_UNSUPPORTED_HOSTS`.

### Download flow is job-based with SSE progress

- `POST /api/download` → `_precheck` (bad kind / audio-without-FFmpeg → HTTP
  status), then `service.start_download()` creates an in-memory job
  (`services/jobs.py`) and an `asyncio.create_task`, returns `{job_id}` (202).
- `GET /api/download/{id}/events` → `text/event-stream` of
  `{state, message, percent}`; `_HEARTBEAT_SECONDS` keep-alive comments during
  long silent phases (FFmpeg merge); ends on `done`/`error`.
- `GET /api/download/{id}/file` → `FileResponse` + `BackgroundTask` that deletes
  the job dir and discards the job.
- Errors **during** the download surface through the SSE stream, not as an HTTP
  status. `services/jobs.JobStore` is size-capped and TTL-expired; swept by
  `core/cleanup.py` alongside `storage/temp`.
- `services/downloader.format_selector` is FFmpeg-aware: with FFmpeg it emits
  `bv*+ba` merge selectors (required for YouTube — no progressive streams above
  360p); without FFmpeg it stays progressive-only.

### Error handling

`services/errors.ServiceError` subtypes carry a user-safe `.message` and a short
`.reason` (log only). Endpoints are **thin** — no try/except; they let
exceptions bubble to the central handlers in `app/main.py`, which map
`UrlValidationError`→400, `UnsupportedPlatformError`→400,
`FeatureUnavailableError`→501, `MediaTooLargeError`→413, other `ServiceError`→502,
anything else→500. No response ever leaks a stack trace; yt-dlp/FFmpeg messages
stay in the log.

### Cross-cutting

- `core/config.py` — pydantic-settings from `.env` (`get_settings()` is
  `lru_cache`d; tests monkeypatch the instance or the `Settings` class).
- `core/middleware.SecurityHeadersMiddleware` — CSP (allows Google Fonts +
  `img-src https:` for thumbnails; exempt for `/docs`) and a `Content-Length`
  body-size guard on `/api/*`.
- `core/ratelimit.py` — slowapi per-IP. `headers_enabled` is deliberately OFF:
  with it on, slowapi requires a `response: Response` param in every endpoint
  and 500s on any success response.
- `core/cleanup.CleanupScheduler` — started in `main.py` lifespan; also runs one
  sweep at boot.
- `utils/cache.TTLCache` — metadata cache, keyed by normalized URL.

## Testing conventions

- **No network, no yt-dlp, no FFmpeg** in tests. Services accept injectable
  `extractor` / `downloader` / `audio_extractor`. For endpoint tests,
  monkeypatch `app.services.instagram._default_downloader` /
  `_default_extractor` (the real yt-dlp entry points), or
  `app.services.ytdlp.run_extract`.
- SSE tests **must** use a context-managed `TestClient` (see the `client`
  fixture in `tests/test_download_endpoint.py`) so one event loop persists
  across requests and the background job task can advance. A module-level
  `TestClient` gives each request its own loop and the job never progresses.
- `tests/conftest.py` disables the rate limiter by default; tests that need it
  re-enable it explicitly.
- `dns_check=False` on `validate_url` / `validate_instagram_url` skips the
  network SSRF check; anti-SSRF has its own coverage in `tests/test_security.py`.

## Notes

- Anonymous extraction from Instagram / TikTok / Facebook is unreliable (the
  platforms gate logged-out access). Mitigate per platform in `.env`:
  `<PLATFORM>_COOKIES_FILE` (a `cookies.txt`, takes precedence) or
  `<PLATFORM>_COOKIES_FROM_BROWSER` (`chrome`/`firefox`/`edge`/..., optional
  `:profile`). `ytdlp.common_opts` builds the yt-dlp opts;
  `ytdlp._reraise_ytdlp` turns the "cookie database locked" (browser open on
  Windows) and "browser not found" errors into their own messages. Each service
  reads its settings via `getattr(settings, f"{self.name}_cookies_*")` — new
  platforms get this for free. YouTube generally works anonymously.
- Commit messages in this repo end with a `Co-Authored-By: Claude ...` line.
