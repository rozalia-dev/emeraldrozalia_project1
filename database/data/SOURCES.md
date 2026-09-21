# Catalogue data sources

Project 1 bundles catalogue reference data so Country → County/State/Region → Club dropdowns do not depend on a third-party API at runtime.

- `iso3166-2-world.json`: ISO 3166-2 subdivision data from the pycountry project / Debian iso-codes snapshot. pycountry is distributed under LGPL-2.1.
- `openfootball-fifa-clubs-*.csv`: normalized football club names from the OpenFootball clubs dataset, released under CC0 1.0.
- `reep-global-football-clubs.csv`: country-level football club fallback names derived from the frozen Reep v0 team register, released under CC0 1.0.

The OpenFootball snapshot is used first where its region headings can be matched to a catalogue subdivision. Reep supplies a broader country-level fallback so a FIFA country is not left without club choices merely because a county/state mapping is unavailable. Club Master remains the authoritative admin surface for corrections and additions.
