// The Hukamnama from Sri Harmandir Sahib in the browser (an ES module, no dependencies).
//
//   import { latestPublished, isToday } from "./hukamnama.js";
//
// The API is open to every origin, so a page may call it directly. latestPublished() returns
// today's Hukamnama, or the most recent one when today's is not published yet, and only after its
// lines are proved against range_sha256. It throws NotProven when they fail: show nothing then.
export const DEFAULT_API = "https://api.gurbanisoul.com";
const AMRITSAR_OFFSET_MS = (5 * 60 + 30) * 60 * 1000;   // a fixed offset: India has no summer time

export class NotProven extends Error {}

export async function get(path, api = DEFAULT_API) {
  const res = await fetch(api.replace(/\/+$/, "") + path);
  if (!res.ok) throw new Error(`${path}: HTTP ${res.status}`);
  return res.json();
}

/** Throws NotProven unless the day's lines hash to its range_sha256. */
export async function verify(day) {
  const text = day.lines.map((line) => line.gurmukhi).join("\n");
  const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(text));
  const hex = [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, "0")).join("");
  if (hex !== day.range_sha256) throw new NotProven(day.amritsar_date);
  return day;
}

/** Today's Hukamnama, or the most recent one; null when there is none. */
export async function latestPublished(api = DEFAULT_API) {
  let day = await get("/v1/hukamnama/today", api);
  if (day.status !== "published") {
    if (!day.latest) return null;
    day = await get(`/v1/hukamnama/${day.latest}`, api);
    if (day.status !== "published") return null;
  }
  return verify(day);
}

export function amritsarDate(now = new Date()) {
  return new Date(now.getTime() + AMRITSAR_OFFSET_MS).toISOString().slice(0, 10);
}

/** Call it today's only while its date is still the date in Amritsar. */
export function isToday(day, now = new Date()) {
  return day.amritsar_date === amritsarDate(now);
}

/** A stand-in API may be named with ?api= only while the page itself is opened from this computer. */
export function apiFor(location) {
  const local = ["localhost", "127.0.0.1", "[::1]"].includes(location.hostname);
  const asked = new URLSearchParams(location.search).get("api");
  return local && asked && /^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(asked) ? asked : DEFAULT_API;
}
