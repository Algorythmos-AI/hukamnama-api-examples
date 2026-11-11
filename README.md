# Hukamnama API examples

Working examples for the public Hukamnama API of [Gurbani Soul](https://gurbanisoul.com): the daily
Hukamnama from Sri Harmandir Sahib, Amritsar, as JSON. The API is free and needs no key.

- API: `https://api.gurbanisoul.com/v1/hukamnama/today`
- Guide, display rules and terms: <https://gurbanisoul.com/en/developers>
- Reference: <https://docs.gurbanisoul.com/api/hukamnama/>

| Folder | What it is | Needs |
|---|---|---|
| [`curl/`](curl/today.sh) | The Hukamnama in a shell script | curl, jq |
| [`python/`](python/hukamnama.py) | One file, standard library only | Python 3.9+ |
| [`node/`](node/hukamnama.mjs) | A small module and a command | Node 18+ |
| [`swift/`](swift/Hukamnama.swift) | One file with Foundation and CryptoKit | Swift 5.9+ on macOS |
| [`browser/`](browser/index.html) | A web page that calls the API directly | any modern browser |
| [`gurdwara-screen/`](gurdwara-screen/index.html) | A full-screen Hukamnama for a television or projector | any modern browser |
| [`wordpress/`](wordpress/hukamnama-sri-harmandir-sahib) | A WordPress plugin: the shortcode `[hukamnama]` | WordPress 5.8+, PHP 7.4+ |

## What every example does

These are the rules for showing Gurbani from the API. Each example keeps all of them, and the
tests check them.

1. **Prove the text before showing it.** Join the `gurmukhi` of every line with a newline, take the
   SHA-256 of the UTF-8 bytes and compare it with `range_sha256`. If they differ, show nothing.
   Late is better than wrong.
2. **Show the whole shabad, unchanged.** Never correct, shorten, re-space or reorder the Gurmukhi.
3. **Use the Hukamnama's own date.** `amritsar_date` is the day in Amritsar (UTC+05:30). Call it
   "today's" only while that is still the date there. Before the morning's Hukamnama is published
   the API answers `pending` with `latest`: show that day under its own date.
4. **Cite and credit.** Show `citation` ("Sri Guru Granth Sahib Ji · Ang N") and the words and link
   in `attribution`.
5. **Keep other layers apart.** Transliteration and any translation go under their own label, never
   inside the Gurmukhi.
6. **Do not call it official.** SGPC publishes the Hukamnama. The API finds the shabad by Ang and
   serves its text from Sri Guru Granth Sahib Ji. Gurbani Soul is not affiliated with SGPC.

## Run them

```bash
python3 python/hukamnama.py
node node/today.mjs
sh curl/today.sh
swift swift/Hukamnama.swift
```

For the two web pages, serve this folder and open them:

```bash
python3 -m http.server 8788
```

Then visit `http://localhost:8788/browser/` or `http://localhost:8788/gurdwara-screen/`. The screen
takes `?theme=dark` and `?translit=1`.

## The Gurdwara screen

[`gurdwara-screen/index.html`](gurdwara-screen/index.html) is one file. Put it on any web server, open
it on the screen's computer and leave it on. It asks the API every five minutes, sizes the text so
the whole shabad fits with nothing cut, and keeps the last proved Hukamnama up if the network drops.

## The WordPress plugin

Copy [`wordpress/hukamnama-sri-harmandir-sahib`](wordpress/hukamnama-sri-harmandir-sahib) into
`wp-content/plugins/`, activate it, and add `[hukamnama]` to a page.

- `[hukamnama translit="yes"]` adds the transliteration, under its own label.
- `[hukamnama mode="embed"]` shows gurbanisoul.com's own embed in a frame instead.

It asks the API at most once every five minutes, proves every answer (and its own cached copy)
before each display, and shows the last proved day during an outage, under its own date.

## Without the network

`tools/mock_api.py` is a stand-in for the API that serves the files in `fixtures/`. Every example
reads `HUKAMNAMA_API` (the web pages take `?api=` when opened from `localhost`):

```bash
python3 tools/mock_api.py --mode published   # or: pending, tampered
HUKAMNAMA_API=http://127.0.0.1:8787 python3 python/hukamnama.py
```

## Tests

```bash
sh tests/run.sh                 # every command-line example, in the three modes
php wordpress/tests/run.php     # the plugin, with stand-ins for WordPress
npm install && npx playwright install chromium && node tests/browser.mjs   # the two web pages
```

`tampered` alters one line by a single space. Every example must then show no line at all.

## The fixtures

The files in `fixtures/` are real answers of the API's code for one day, 11 November 2026 (Ang
680), and its lines prove against its `range_sha256`. They are there so the examples can be tested.
Do not use them as a source of Gurbani: ask the API.

## Licence

The code is under the [MIT licence](LICENSE). That licence covers the code only. The Gurbani in
`fixtures/` is from Sri Guru Granth Sahib Ji: it is not ours to license, and it must never be altered.
Use of the API is under its [terms](https://gurbanisoul.com/en/developers#terms).
