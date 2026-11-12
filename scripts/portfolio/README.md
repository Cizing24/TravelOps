# Reproduce the V3 portfolio media

Run from the TravelOps worktree root. Source baseline: `11c0e7ae5fa828a69438f807be220c4f37be41c9`. These scripts do not change application behavior. All raw clips, credentials, temporary browser files and editing intermediates stay under ignored `.runtime/`.

## Isolated demo and actual capture

Use Windows PowerShell, Docker Desktop Linux Engine, Node 24, Chrome, Python 3.10+ with Pillow, and FFmpeg/ffprobe on PATH. Review commands before running. The project name and port below are reserved for this synthetic capture. Existing user demo data must not be reset.

```powershell
New-Item -ItemType Directory -Force .runtime | Out-Null
$env:TRAVELOPS_PORT = '18084'
docker compose -p travelops-media -f compose.yaml -f scripts/portfolio/media.compose.yaml up --build -d --wait
if (!(Test-Path .runtime/demo-credentials.txt)) {
  docker compose -p travelops-media -f compose.yaml -f scripts/portfolio/media.compose.yaml exec -T app php artisan travelops:demo --reconciliation-fixture --no-ansi > .runtime/demo-credentials.txt
}
npm ci --no-audit --no-fund
node scripts/portfolio/capture.mjs
```

The credentials command prints newly generated credentials only. If this dedicated media environment was already initialized, reuse its existing ignored credentials file; do not overwrite it with an empty account list. Do not use `--reset-passwords` on an existing user environment. Capture creates additional synthetic orders on each run, so stock and reporting totals can change. Recreate the same presentation from a fresh, separately named environment if exact fixture counts are required; never delete or reset existing volumes for this purpose.

`--screenshots-only` updates just the eight screenshots and their JSON manifest without replacing the chosen video takes. Each image uses the actual viewport recorded in `.runtime/media/screenshots.json`, with a full-page capture. A clearly identified media disclosure is appended. UUIDs and account text are scrubbed, and QR / credential elements are hidden before capture; these changes are flattened into the final pixels. No prices, stock, statuses or outcomes are fabricated. The capture asserts no horizontal page overflow, no visible complete identifiers and no browser page errors. Login occurs before any recording context is created.

## Edit the actual video

```powershell
python scripts/portfolio/build-video.py --traveller-cut 18 30
```

Those cut points apply to the reviewed take from 2026-11-12: remove 12 seconds of unchanged waiting, label the cut, preserve all real action and status transitions. Review fresh raw takes before reusing those times. Omit the flag when a new take does not need trimming; the script asserts a final duration of 60–90 seconds instead of silently stretching footage. The final output is H.264/yuv420p, 25 fps, 1280×720, fast-start MP4 without audio. Title cards and captions use Microsoft JhengHei; FFmpeg must include libass. English and Traditional Chinese SRT are generated from the same timeline. No music or generated voice is used.

## Diagram tooling

Clone the reviewed upstream into an ignored tools directory and check out `5af3b095fff7109c905c5284dc510dc06588bade`. Read the scripts before executing them. Set `$skillDir` to its `skills/stack-diagram` directory. The exact local run used the parent worktree's `.runtime/tools/stack-diagram`; the paths below can be changed to a separately reviewed installation.

```powershell
$skillDir = '../tools/stack-diagram/skills/stack-diagram'
python "$skillDir/scripts/fetch_icons.py" ../media-icons laravel mysql redis docker people:lucide/users queue:lucide/list-ordered clock:lucide/clock mock:lucide/plug webhook:lucide/radio tower:lucide/shield-check
python scripts/portfolio/build-architecture.py --skill $skillDir --icons ../media-icons
# The official renderer requires the Python Playwright package and its matching Chromium.
python "$skillDir/scripts/render_png.py" docs/portfolio/travelops-v3/architecture/travelops-system-overview.html docs/portfolio/travelops-v3/architecture/travelops-system-overview.png --scale 2
```

This run installed Python Playwright 1.65.1 under the parent ignored `.runtime/python-tools` via pip `--target`, set `PYTHONPATH` to that directory, then ran `python -m playwright install chromium`. Tool files are not a product runtime dependency. Logo downloads send only public technology names. Never run `share.py submit` or upload the diagram to a public gallery.

Mermaid CLI 11.12.0 was installed under the parent ignored `.runtime/tools/mermaid` with npm `--ignore-scripts`. Use a Puppeteer JSON file containing the path to the existing Chrome executable. Run the following for each of the two `.mmd` files, replacing the basename:

```powershell
node ../tools/mermaid/node_modules/@mermaid-js/mermaid-cli/src/cli.js -i docs/portfolio/travelops-v3/architecture/travelops-system-overview.mmd -o docs/portfolio/travelops-v3/architecture/travelops-system-overview.svg -p .runtime/puppeteer.json -c scripts/portfolio/mermaid.json -b '#f5f7f5'
```

`puppeteer.json` for this Windows run:

```json
{"executablePath":"C:/Program Files/Google/Chrome/Application/chrome.exe","args":["--no-sandbox"]}
```

The transaction PNG uses the same CLI with its `.mmd`, a `.png` output, `-w 1600 -s 4` (actual output 3132×11888). PNG brand overviews are the official HTML renderer's output; SVGs are Mermaid outputs. Inspect both themes and both semantic diagrams after any edit.

## Validation

```powershell
node scripts/portfolio/check-browser.mjs
python scripts/portfolio/validate.py
```

The browser check verifies actual MP4 decode/playback/seeking in a local Chrome session and renders SVG review images. The file check verifies dimensions, video format/duration/full decode, subtitle bounds, package contents and publication-text boundaries, and records sizes/hashes. Manual image/video privacy review still matters. Generated media files are small enough for ordinary Git in this delivery; raw recordings are ignored.
