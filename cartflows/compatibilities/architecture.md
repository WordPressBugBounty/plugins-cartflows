---
module: compatibilities
---

# compatibilities

## Responsibility

Third-party integration shims. Detects active page builders, themes, LMS plugins and gateways at bootstrap and conditionally loads one self-registering singleton per integration, each of which adds or removes hooks so CartFlows step pages render correctly inside that third party.

Owns only the glue — the CartFlows behaviour being adapted (templates, checkout, helpers, `wcf()`) lives elsewhere, and each file assumes the third party's own classes and constants already exist.

## Why it is this way

Nearly every file encodes an undocumented fact about someone else's plugin: a run-once global to reset, a hook priority that must beat theirs, a superglobal flag their code reads, a raw SQL write because their code hijacks the API. None of it is inferable from the CartFlows side.

The failure modes are also silent rather than loud — a `remove_class_filter` returning `false`, a missed hook removal, a stale meta cache. That is why the priorities and the load order in `load_files()` are treated as fixed constants here rather than as tunable defaults.
