# MioLog Phase 3: AI Notes And Shortlist

## Product Framing

These feel like the right high-level promises for MioLog's future AI features:

- Mio-chan helps you remember
- Mio-chan helps you decide
- Mio-chan helps you reflect

These are worth preserving for future product copy and the eventual website.

## Phase 3 Direction

Phase 3 should not be "duct tape AI on it."

The goal is to build AI features that:

- use personal data MioLog already has
- save real effort or unlock something hard to do manually
- feel like a companion, not a bolted-on chatbot
- stay grounded in the user's own logs, reviews, ratings, tags, platforms, and play history

## Initial Provider Plan

For the first phase-3 implementation, the provider setup should be:

- local development: `LM Studio`
- deployed server: `OpenAI API`

Why this is a good starting point:

- LM Studio gives cheap local experimentation while prompts and flows are still moving
- LM Studio's OpenAI-style API reduces local-to-remote friction
- OpenAI in production keeps quality and behavior easier to compare against local free models
- real paid usage will give direct insight into actual cost and quality for MioLog's use cases

Important note:

- provider configuration should remain a backend concern
- the PWA should only care whether a backend is available and whether AI features are exposed by that backend
- future provider swapping should happen inside Symfony configuration, not in the frontend

## Cost Control Guardrails

OpenAI cost should be treated as a design concern from day one.

For MioLog, the first implementation should follow these guardrails:

- prefer cheaper models by default
- only use AI on explicit user actions
- keep prompts narrow and grounded
- cap output length
- avoid sending the user's full history when a smaller slice is enough
- cache or reuse results when source data has not meaningfully changed
- record backend usage so real cost can be observed early
- use provider-level billing caps as a final safety net

### Practical Rules

#### Use the right model for the job

Not every feature needs the same model quality.

Likely examples:

- review drafting may justify a better model
- "what should I play next?" can likely use a smaller cheaper model
- short recaps and tag suggestions should stay cheap

#### Only run on explicit action

Mio-chan should not silently generate things in the background.

Good examples:

- Draft review
- What should I play next?
- Summarize this journey

Bad examples:

- auto-generating after every log save
- hidden background analysis
- repeated generation without user intent

#### Limit prompt scope

Only send what is actually relevant.

Examples:

- selected game
- relevant metadata
- a limited set of recent or meaningful logs
- maybe the existing review text, if present

Avoid sending:

- the user's entire library
- every log ever written
- unrelated game history

#### Limit output size

Most MioLog AI features should be concise by design.

Examples:

- short review draft
- compact recap
- one recommendation with reasoning

This keeps both cost and UI clutter under control.

#### Track and observe usage

The backend should eventually record enough information to understand usage and cost trends, for example:

- feature name
- timestamp
- chosen model
- token usage if available
- rough estimated cost

That gives real feedback before cost becomes a concern.

## Suggested Cost Mindset

For MioLog, the goal is not "free forever at all costs."

The better goal is:

- low, understandable spend
- explicit high-value AI actions
- enough instrumentation to know whether the quality is worth the cost

## Strongest Candidates

### 1. Draft A Review From My Logs

This still feels like the flagship MioLog AI feature.

Why it fits:

- uses the user's own words as source material
- turns raw session logs into a coherent shareable review
- feels like a payoff for the logging habit
- can support variants like:
  - short review
  - Steam-style review
  - spoiler-free review
  - pros / cons draft

Why it should rank high:

- deeply aligned with MioLog's identity
- no external data dependency
- emotionally satisfying and genuinely useful

### 2. What Should I Play Next?

This should help choose from the user's actual backlog and active library.

Possible inputs:

- backlog games
- current / ongoing games
- preferred platforms
- tags
- ratings
- recent finished games
- mood or time constraints

Why it fits:

- solves a real decision problem
- uses MioLog's existing structured data well
- feels companion-like instead of gimmicky

### 3. Short Recap Before I Jump Back In

For paused or older active games, Mio-chan could summarize:

- what happened recently
- what the player's last few sessions felt like
- recurring friction or excitement

Why it fits:

- reduces re-entry friction
- grounded in real logs
- especially useful for long RPGs, side games, and games revisited after a break

### 4. Summarize This Game Journey

Turn a larger set of logs into a small retrospective.

Possible outputs:

- how the mood evolved
- standout moments
- recurring positives / negatives
- what defined the experience overall

Why it fits:

- good bridge between logs and final review
- useful even if the player does not want a public-facing review

## Good Second-Wave Ideas

These feel promising, but not quite first-wave:

- Recommend me a new game
- What do I seem to like lately?
- Why did I bounce off this game?
- Suggest tags from logs
- Suggest a rating range
- Monthly gaming diary
- Year-in-review draft
- Favorite moments from my logs
- Write a recommendation for a friend

## Things To Avoid Early

These are the most likely route into generic AI sludge:

- open-ended chatbot inside the app
- "ask Mio anything" with no clear job
- automated log writing
- generic opinion generation with no source grounding
- heavy dependency on external metadata as a core requirement

## IGDB / External Enrichment Note

IGDB should not be a phase-3 priority right now.

Reason:

- matching editions, ports, and releases is messy
- metadata can become a cleanup burden
- MioLog's strongest AI opportunities already exist in the user's own data

External enrichment can still be revisited later as an optional, careful feature, but it should not block or define phase 3.

## Recommended Phase 3 Shortlist

If MioLog starts phase 3 with a focused shortlist, this feels strongest:

1. Draft a review from my logs
2. What should I play next?
3. Short recap before I jump back in
4. Summarize this game journey

## Suggested Decision Lens

When choosing the first implemented phase-3 feature, ask:

- Does this use data the user already trusts MioLog with?
- Does it reduce real friction or create a meaningful payoff?
- Does it feel like Mio-chan is helping, rather than performing?
- Would this still feel useful after the novelty of AI wears off?

If a feature passes those tests, it likely belongs.
