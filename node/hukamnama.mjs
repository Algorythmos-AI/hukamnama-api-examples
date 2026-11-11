// The Hukamnama from Sri Harmandir Sahib for Node 18+ (no dependencies).
//
//   import { latestPublished, isToday } from "./hukamnama.mjs";
//
// latestPublished() returns today's Hukamnama, or the most recent one when today's is not
// published yet, and only after its lines are proved against range_sha256. It throws NotProven
// when they fail: show nothing then.
import { createHash } from "node:crypto";

export const API = (process.env.HUKAMNAMA_API ?? "https://api.gurbanisoul.com").replace(/\/+$/, "");
const AMRITSAR_OFFSET_MS = (5 * 60 + 30) * 60 * 1000;   // a fixed offset: India has no summer time

export class NotProven extends Error {}

export async function get(path) {
  const res = await fetch(API + path, { headers: { "User-Agent": "hukamnama-api-examples (node)" } });
  if (!res.ok) throw new Error(`${path}: HTTP ${res.status}`);
  return res.json();
}

/** Throws NotProven unless the day's lines hash to its range_sha256. */
export function verify(day) {
  const text = day.lines.map((line) => line.gurmukhi).join("\n");
  const hex = createHash("sha256").update(text, "utf8").digest("hex");
  if (hex !== day.range_sha256) throw new NotProven(day.amritsar_date);
  return day;
}

/** Today's Hukamnama, or the most recent one; null when there is none. */
export async function latestPublished() {
  let day = await get("/v1/hukamnama/today");
  if (day.status !== "published") {
    if (!day.latest) return null;
    day = await get(`/v1/hukamnama/${day.latest}`);
    if (day.status !== "published") return null;
  }
  return verify(day);
}

/** The date in Amritsar at an instant, as YYYY-MM-DD. */
export function amritsarDate(now = new Date()) {
  return new Date(now.getTime() + AMRITSAR_OFFSET_MS).toISOString().slice(0, 10);
}

/** Call it today's only while its date is still the date in Amritsar. */
export function isToday(day, now = new Date()) {
  return day.amritsar_date === amritsarDate(now);
}

export function render(day, now = new Date()) {
  const label = isToday(day, now) ? "Today's Hukamnama" : "Hukamnama";
  return [
    "Hukamnama · Sri Harmandir Sahib",
    `${label}, ${day.amritsar_date} (Amritsar)`,
    "",
    ...day.lines.map((line) => line.gurmukhi),     // every line, exactly as it arrived
    "",
    day.citation,
    `${day.attribution.text}: ${day.attribution.url}`,
  ].join("\n");
}
