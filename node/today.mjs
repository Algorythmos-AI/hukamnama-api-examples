#!/usr/bin/env node
// Print the Hukamnama:  node node/today.mjs
import { latestPublished, NotProven, render } from "./hukamnama.mjs";

try {
  const day = await latestPublished();
  if (day === null) {
    console.error("No Hukamnama is available yet.");
    process.exit(2);
  }
  console.log(render(day));
} catch (e) {
  if (!(e instanceof NotProven)) throw e;
  console.error("The Hukamnama could not be verified, so it is not shown. Please try again later.");
  process.exit(1);
}
