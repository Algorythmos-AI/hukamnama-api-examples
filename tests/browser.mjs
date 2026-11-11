// Open the two web pages in a real browser against the stand-in API, in its three modes.
//   npm install && npx playwright install chromium && node tests/browser.mjs
import { spawn } from "node:child_process";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { chromium } from "playwright";

const root = fileURLToPath(new URL("..", import.meta.url));
const SITE = Number(process.env.SITE_PORT ?? 8788);
const API = Number(process.env.PORT ?? 8787);
const day = JSON.parse(readFileSync(root + "fixtures/today.json", "utf8"));
const want = day.lines.map((l) => l.gurmukhi);
const api = `http://127.0.0.1:${API}`;
let failed = 0;
const check = (ok, what) => { console.log((ok ? "ok    " : "FAIL  ") + what); if (!ok) failed++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function up(url) {
  for (let i = 0; i < 40; i++) {
    try { if ((await fetch(url)).ok) return; } catch { /* not yet */ }
    await sleep(150);
  }
  throw new Error(`${url} did not start`);
}

const site = spawn("python3", ["-m", "http.server", String(SITE), "--bind", "127.0.0.1"], { cwd: root, stdio: "ignore" });
await up(`http://127.0.0.1:${SITE}/browser/index.html`);
const browser = await chromium.launch();

async function withMock(mode, fn) {
  const mock = spawn("python3", ["tools/mock_api.py", "--port", String(API), "--mode", mode], { cwd: root, stdio: "ignore" });
  try {
    await up(`${api}/v1/status`);
    await fn();
  } finally {
    mock.kill();
    await sleep(200);
  }
}

async function open(path, viewport = { width: 1280, height: 720 }) {
  const page = await browser.newPage({ viewport });
  const errors = [];
  page.on("pageerror", (e) => errors.push(String(e)));
  await page.goto(`http://127.0.0.1:${SITE}/${path}${path.includes("?") ? "&" : "?"}api=${encodeURIComponent(api)}`);
  return { page, errors };
}

try {
  for (const mode of ["published", "pending"]) {
    await withMock(mode, async () => {
      // the plain page
      let { page, errors } = await open("browser/index.html");
      await page.waitForSelector("#lines p");
      check(JSON.stringify(await page.locator("#lines p").allTextContents()) === JSON.stringify(want), `${mode}: page shows every line, in order, unchanged`);
      const label = await page.locator("#date").textContent();
      check(label.includes("11 November 2026") && !label.includes("Today"), `${mode}: page dates it by its Amritsar date, not as today's`);
      check((await page.locator("#citation").textContent()) === day.citation, `${mode}: page shows the citation`);
      check((await page.locator("#credit").getAttribute("href")) === day.attribution.url, `${mode}: page credits with the link`);
      check((await page.locator("#lines").getAttribute("lang")) === "pa" && errors.length === 0, `${mode}: page marks the Gurmukhi as Punjabi, no script errors`);
      await page.close();

      // the Gurdwara screen, at television and small sizes, with and without transliteration
      for (const [w, h, q] of [[1920, 1080, ""], [1280, 720, "?translit=1&theme=dark"], [800, 480, ""]]) {
        ({ page, errors } = await open("gurdwara-screen/index.html" + q, { width: w, height: h }));
        await page.waitForSelector("#lines p");
        const shown = await page.locator("#lines p:not(.t)").allTextContents();
        check(JSON.stringify(shown) === JSON.stringify(want), `${mode}: screen ${w}x${h}${q} shows every line`);
        const fits = await page.evaluate(() => {
          const s = document.getElementById("stage"), l = document.getElementById("lines");
          return l.scrollHeight <= s.clientHeight && l.scrollWidth <= s.clientWidth
            && document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth;
        });
        check(fits, `${mode}: screen ${w}x${h}${q} fits the whole shabad with nothing cut`);
        if (q) check((await page.locator("#lines p.t").count()) === want.length && (await page.locator("#lines p.t").first().getAttribute("lang")) === "pa-Latn", `${mode}: screen transliteration is its own labelled lines`);
        check(!(await page.locator("#date").textContent()).includes("Today") && errors.length === 0, `${mode}: screen ${w}x${h} is not called today's on another date`);
        await page.close();
      }
    });
  }

  await withMock("tampered", async () => {
    let { page } = await open("browser/index.html");
    await page.waitForFunction(() => document.getElementById("date").textContent.includes("could not be verified"));
    check((await page.locator("#lines p").count()) === 0, "tampered: page shows no line");
    await page.close();
    ({ page } = await open("gurdwara-screen/index.html"));
    await page.waitForFunction(() => document.getElementById("status").textContent.includes("cannot be shown"));
    check((await page.locator("#lines p").count()) === 0, "tampered: screen shows no line");
    await page.close();
  });

  // ?api= is honoured only for a local stand-in, from a page opened on this computer
  const { page } = await open("browser/index.html");
  const picked = await page.evaluate(async () => {
    const m = await import("./hukamnama.js");
    const at = (href) => m.apiFor(new URL(href));
    return [at("http://127.0.0.1:1/x?api=https://evil.example"), at("https://example.org/x?api=http://127.0.0.1:9"), at("http://localhost:1/x?api=http://127.0.0.1:9")];
  });
  check(picked[0] === "https://api.gurbanisoul.com" && picked[1] === "https://api.gurbanisoul.com" && picked[2] === "http://127.0.0.1:9", "the API host can only be replaced by a local stand-in, from a local page");
  await page.close();
} finally {
  await browser.close();
  site.kill();
}
console.log(failed ? `\n${failed} check(s) failed` : "\nall browser checks passed");
process.exit(failed ? 1 : 0);
