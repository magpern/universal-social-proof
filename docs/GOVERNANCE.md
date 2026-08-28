# Governance — Universal Social Proof

**Status:** Active with the architecture freeze.  
**Authoritative specification:** [architecture/FROZEN.md](architecture/FROZEN.md)

## 1. Authority

[architecture/FROZEN.md](architecture/FROZEN.md) is the authoritative implementation specification for milestones **M0–M7**. Implementation must not silently diverge from it.

## 2. Milestone order

Milestones are implemented **in order** (M0 → M7) unless Product Owner explicitly approves otherwise. Versions are cumulative: each milestone builds on the prior closed version. There is **no M8** in this freeze.

## 3. Respecting the freeze

Each milestone must respect the frozen architecture, naming, privacy boundary, and versioning policy recorded in the specification and [roadmap/README.md](roadmap/README.md).

## 4. Material divergence

Material divergence from the frozen product contract (behaviour, privacy surface, public REST DTO shape that contradicts the freeze, fake-event capability, region/city in v1, etc.) requires:

1. An explicit architecture amendment and/or ADR, and  
2. Product Owner approval where product behaviour changes.

## 5. Engineering discoveries

Engineering discoveries that **do not** alter the product contract may be resolved within the relevant milestone and documented (ADR amendment, milestone notes, or changelog). Example: concrete WooCommerce API method names discovered during M1 characterization.

## 6. M1 `occurred_at` characterization

M1’s investigation of WooCommerce 11 public APIs to freeze the exact deterministic `occurred_at` resolver is an **expected engineering investigation**, not architecture drift. The freeze already locks timestamp *semantics* (`occurred_at` vs `captured_at`); only the exact source chain is deferred to M1 evidence ([adr/0004-occurred-at-vs-captured-at.md](adr/0004-occurred-at-vs-captured-at.md)).

## 7. Explicitly out of M0–M7

- **Region** and **city** geography (schema, display, visitor matching) — deferred beyond this freeze; must not be added opportunistically.  
- **Fabricated / custom purchase events** — outside the product boundary; must not be introduced as an implementation convenience.  
- A **generic multi-event marketing platform** — not in v1.

## 8. ADR process

Required ADR topics are listed in [adr/README.md](adr/README.md). ADRs that depend on later milestone evidence remain **Proposed / Requires Mn evidence** until that evidence exists; do not fabricate implementation evidence to close them early.
