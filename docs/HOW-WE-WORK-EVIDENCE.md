# How We Work — visual evidence

The public `/factory` route now uses the approved `docs/new project image/how we work 1.png` composition as one responsive artwork so the page cannot split the header/navigation or introduce placeholder panels between the nine manufacturing stages and the factory-visit CTA.

- Asset: `public/assets/brand/how-we-work-reference.png`
- Source dimensions: `1024 × 1536`
- Approved nine-step order: Design & Development → Pattern Making & Cutting → Shaping & Steaming → Embroidery & Details → Sewing & Assembly → Quality Inspection → Finishing & Steam → Packing & Labelling → Ready to Deliver
- Functional overlays: Home, Book a Factory Visit, email, and website
- Semantic fallback: the page includes a screen-reader navigation list, ordered process list, and factory-visit description
- Shared behavior: the artwork scales to the viewport without horizontal overflow; all other public routes continue to use `layouts.site`
