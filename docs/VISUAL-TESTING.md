# Visual comparison tooling

Task P2.5 adds Playwright projects for deterministic desktop (`1536x1024`) and mobile (`390x844`) capture. The first foundation routes are registered in `tests/visual/foundation.spec.cjs`.

Run after installing the Node dependency:

```text
npm install
npx playwright install chromium
npm run visual:test
```

The tests intentionally use `maxDiffPixels: 0`. A screenshot can only be accepted after the matching approved reference baseline has been reviewed and checked into the Playwright snapshot directory. Do not use `visual:update` to bless a mismatch. The supplied guide images remain source evidence; their different dimensions do not automatically make them valid browser baselines.
