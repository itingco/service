# Phase 3 — Interactive Product & Sales Strategy Advisor Design

> **Status:** Design approved conceptually by user; this document is the implementation contract to review before coding.

## 1. Goal

Build an interactive advisor inside the existing Laravel Business Analytics module that helps management and sales teams answer four questions for a selected product:

1. **What is happening?** — factual sales diagnosis from internal data.
2. **Why is it likely happening?** — transparent inference from trend, customer, channel, and product evidence.
3. **Who should we sell it to and how?** — ranked market segments, online/offline strategy, cross-sell targets, and offer ideas.
4. **What should we test next?** — measurable experiments with baseline, KPI, success threshold, stop rule, and post-test evaluation.

The product must keep a strict separation between **FACT**, **INFERENCE**, **RECOMMENDATION**, and **EXPERIMENT**.

---

## 2. Existing Architecture to Reuse

Phase 3 extends the existing Phase 2 components rather than replacing them:

- `business_analytics_monthly_sales` — 36-month local aggregate data mart.
- `AnalyticsRepository` — trend, customer, product, channel, and driver queries.
- `BusinessAnalyticsService` — Business Analytics report composition.
- `ForecastEngine` — 3-month forecast and backtest.
- `StrategyRecommendationEngine` — rule-based management actions.
- existing dashboard authentication, role permissions, audit middleware, sidebar, and SQLite configuration (`sqlite_targets`).

Phase 3 adds a separate advisor subsystem so normal Business Analytics remains usable even when AI/internet access is unavailable.

---

## 3. Product Principles

### 3.1 Facts are internal and immutable

Numeric facts shown as internal facts must originate from local analytics/ERP data or explicit user input. AI is not allowed to invent or modify them.

Examples:

- sales 1M/3M/6M/12M/36M;
- MoM / YoY / 3M momentum;
- online/offline contribution;
- active, growing, declining, lost, and reactivated customers;
- quantity trend;
- related-item purchasing patterns;
- customer/product contribution;
- user-entered stock, product specification, reference price, margin, competitor notes.

### 3.2 External research is evidence, not fact about company performance

Internet research may provide:

- common applications of a product;
- industry/sector use cases;
- types of buyers or institutions;
- common purchasing considerations;
- market terminology and sales angles;
- public references supporting those claims.

External research must be visibly labeled **External Market Evidence** and keep source title/URL/date.

### 3.3 Recommendations must be testable

Every actionable recommendation must contain:

- hypothesis;
- target audience;
- channel;
- offer/action;
- duration;
- baseline;
- KPI;
- success threshold;
- stop rule;
- next decision (`SCALE`, `MODIFY`, or `STOP`).

### 3.4 No false causal claims

If there is no control group/A-B test, the system reports **observed uplift**, not “campaign caused +X%”. Causal language is permitted only when an experiment has a valid control/cohort design.

---

## 4. User Experience

Add a new menu under Business Analytics:

**Product & Strategy Advisor**

Primary route:

`/business-analytics/product-advisor`

The page has six work areas.

### 4.1 Product Review

User can:

- search/select an existing `ItemCode` / `ItemName` from the analytics data mart;
- optionally select source/company;
- enter or maintain product information:
  - product specifications;
  - intended use;
  - advantages / differentiators;
  - reference selling price;
  - current stock;
  - target margin;
  - known competitors;
  - notes / constraints.

The review immediately shows:

- 1M / 3M / 6M / 12M / 36M sales;
- MoM, YoY, 3M momentum;
- quantity trend;
- online vs offline share and growth;
- top historical customers;
- new / growing / declining / lost customer counts and value;
- top sales lines and salesperson contribution;
- forecast direction if enough history exists;
- anomaly and seasonality signals.

### 4.2 Diagnosis

Produce a deterministic diagnosis before calling AI:

- `HEALTHY`;
- `SLOWING`;
- `DECLINING`;
- `LOST DEMAND`;
- `NEW DEMAND`;
- `VOLATILE`;
- `CHANNEL GAP`;
- `CUSTOMER CONCENTRATION RISK`.

Each diagnosis includes evidence rows, for example:

> **FACT** — Sales 3M -28.1%; online share 11.7%; category/related item sales +8.4%; active buyers 54 → 37.

> **INFERENCE** — Demand weakness appears more concentrated in distribution/customer coverage than in the related product basket.

Inference wording must use uncertainty language such as “indicates”, “suggests”, or “likely”, unless directly proven by data.

### 4.3 Market Opportunity Finder

Rank potential buyer archetypes, for example:

- contractor interior;
- MEP contractor;
- industrial maintenance;
- fabrication workshop;
- hardware/tool retailer;
- building-material store;
- facility management;
- service/repair workshop;
- project procurement;
- professional marketplace seller;
- advanced DIY consumer.

Each segment result contains:

- `fit_score` 0–100;
- why the product fits;
- evidence source: product specification / internal pattern / external research;
- suggested buyer problem to address;
- best selling angle;
- preferred channel;
- suggested first offer/test;
- confidence level.

The score is computed by deterministic rules from structured features and may be adjusted only within bounded limits by external evidence. AI must not arbitrarily assign a high score without evidence.

### 4.4 Customer Cross-Sell / Lookalike Opportunity

Internal-only logic; customer names never need to leave the server.

For selected item `S`:

1. Find customers who bought `S` in the last 12 months.
2. Find items frequently co-purchased by those customers.
3. Find active customers who buy those related items but have not bought `S` in the lookback period.
4. Rank candidates using:
   - related-item overlap;
   - customer recency;
   - customer sales value;
   - growth trend;
   - channel match.

Output:

- customer code/name;
- opportunity score;
- why the customer matches;
- recent relevant purchases;
- recommended approach;
- salesperson owner when available.

This is a recommendation lead list, not a claim that the customer will purchase.

### 4.5 Online Strategy

The advisor must distinguish strategies appropriate to available data.

With ERP-only data, measurable KPIs include:

- revenue/day;
- quantity/day;
- repeat buyer count;
- active buyers;
- order/invoice count only where de-duplication is valid for the selected item scope;
- online share;
- incremental sales versus baseline.

Do **not** claim CTR, CPC, ROAS, conversion rate, or product-page views unless marketplace/ad-platform data is integrated or the user manually enters those values.

Recommended experiment types may include:

- feature-led campaign;
- bundle / add-on;
- free accessory versus discount;
- minimum-purchase voucher;
- reactivation offer;
- marketplace seller push;
- content/demo angle test;
- product-comparison messaging;
- limited-time offer.

### 4.6 Offline Strategy

Recommended experiment types may include:

- account recovery;
- contractor/project selling;
- trial/demo program;
- reseller push;
- retail bundle;
- salesperson-specific target list;
- cross-sell campaign;
- customer reactivation;
- quotation follow-up sprint;
- industry-specific product pitch.

Each recommendation identifies the most relevant internal customer cohort where possible.

---

## 5. Interactive Advisor

### 5.1 Supported questions

Examples:

- “Kenapa barang ini turun?”
- “Stok saya 500 pcs; strategi 2 bulan apa?”
- “Lebih baik online atau offline?”
- “Customer mana yang belum beli produk ini tetapi punya pola pembelian cocok?”
- “Promo apa yang layak dites?”
- “Kalau diskon 10%, KPI apa yang harus dipantau?”
- “Barang apa yang cocok dibundle?”
- “Bidang usaha/instansi apa yang layak ditarget?”
- “Apa tindakan sales minggu ini?”

### 5.2 Response schema

Every advisor response must be stored and rendered as structured JSON:

```json
{
  "summary": "...",
  "facts": [],
  "inferences": [],
  "market_opportunities": [],
  "recommendations": [],
  "experiments": [],
  "risks": [],
  "missing_information": [],
  "external_sources": []
}
```

The UI renders these as separate sections rather than a single free-form paragraph.

### 5.3 Missing-information behavior

If a recommendation materially depends on missing information, the advisor asks one focused question, for example:

- stock level;
- price flexibility;
- professional vs consumer positioning;
- margin constraint;
- geographic sales focus;
- whether demo/trial is possible.

It must not fabricate an answer to fill missing information.

---

## 6. External AI / Internet Provider Architecture

Use a provider interface rather than coupling business logic to one vendor:

`MarketResearchProvider`

Responsibilities:

- internet research for product applications and buyer sectors;
- return structured source-backed research;
- no direct access to Laravel DB.

`AdvisorSynthesisProvider`

Responsibilities:

- consume sanitized internal facts + research evidence;
- produce the structured advisor response schema;
- never query database directly.

Configuration:

```dotenv
BUSINESS_ADVISOR_PROVIDER=disabled
BUSINESS_ADVISOR_MODEL=
BUSINESS_ADVISOR_API_KEY=
BUSINESS_ADVISOR_WEB_RESEARCH=true
BUSINESS_ADVISOR_TIMEOUT_SECONDS=45
```

Initial implementation may provide an OpenAI adapter, but all application code talks only to the interfaces. `disabled` mode must remain fully usable with deterministic internal analysis and Strategy Action Lab.

### 6.1 Data-minimization rule

By default, external providers may receive:

- item code/name;
- product specifications supplied for research;
- generic sales trend percentages;
- generic channel shares;
- anonymized cohort counts;
- product/market constraints explicitly entered for strategy purposes.

They must **not** receive by default:

- customer names/codes;
- salesperson names;
- invoice numbers;
- contact information;
- raw transaction rows;
- database credentials;
- detailed customer-level revenue;
- confidential notes marked internal-only.

Customer cross-sell calculations stay local.

---

## 7. Data Model

All new tables use `sqlite_targets`.

### 7.1 `business_analytics_product_profiles`

Stores maintained product context.

Fields:

- `id`
- `source` (`ALL`, `INGCO`, `SMI`)
- `item_code`
- `item_name`
- `specifications` text
- `use_cases` text nullable
- `advantages` text nullable
- `reference_price` decimal nullable
- `current_stock` decimal nullable
- `target_margin_percent` decimal nullable
- `competitor_notes` text nullable
- `constraints` text nullable
- `updated_by_user_id` nullable
- timestamps

Unique: `source + item_code`.

### 7.2 `business_analytics_product_reviews`

A reproducible snapshot of one review.

Fields:

- `id`
- `source`
- `item_code`
- `review_period`
- `internal_facts_json`
- `profile_snapshot_json`
- `diagnosis_json`
- `external_research_json` nullable
- `strategy_json` nullable
- `research_status`
- `created_by_user_id` nullable
- timestamps

### 7.3 `business_analytics_advisor_messages`

Fields:

- `id`
- `review_id`
- `role` (`USER`, `ADVISOR`)
- `message_text`
- `response_json` nullable
- `provider` nullable
- `model` nullable
- `created_by_user_id` nullable
- timestamps

### 7.4 `business_analytics_experiments`

Fields:

- `id`
- `review_id` nullable
- `item_code`
- `source`
- `channel` (`ONLINE`, `OFFLINE`, `OMNICHANNEL`)
- `title`
- `hypothesis`
- `target_audience`
- `offer_description`
- `start_date`
- `end_date`
- `baseline_start_date`
- `baseline_end_date`
- `kpi_json`
- `success_threshold_json`
- `stop_rule`
- `control_definition_json` nullable
- `status` (`DRAFT`, `RUNNING`, `COMPLETED`, `SCALED`, `MODIFIED`, `STOPPED`)
- `result_json` nullable
- `decision` nullable
- `created_by_user_id` nullable
- timestamps

### 7.5 `business_analytics_customer_segments` (optional enrichment)

Allows users to explicitly classify customers without guessing from names.

Fields:

- `customer_code`
- `segment`
- `subsegment` nullable
- `notes` nullable
- timestamps

This enables later analysis such as “industrial maintenance customers” using explicit company-owned classification.

### 7.6 `business_analytics_daily_sales`

Phase 2 monthly aggregates are insufficient for 14/21-day experiment measurement. Add a rolling daily mart for recent data.

Fields mirror the monthly mart plus `sales_date`.

Retention target: **180 days** by default.

Sync behavior:

- nightly incremental sync for yesterday/current month;
- manual command can rebuild the last N days;
- experiment evaluator uses daily data for baseline and result windows;
- 36-month historical charts continue using monthly data.

---

## 8. Core Services

### 8.1 `ProductFactService`

Builds internal immutable facts for a selected product.

Outputs:

- trend windows;
- customer movement;
- channel movement;
- line/salesperson contribution;
- concentration;
- related-item graph;
- cross-sell candidate list;
- seasonality/anomaly;
- forecast context;
- data-quality warnings.

### 8.2 `ProductDiagnosisService`

Rule-based diagnosis from `ProductFactService` only.

No external calls.

### 8.3 `ProductOpportunityScorer`

Combines:

- product specification features;
- internal sales/channel behavior;
- source-backed external market evidence;

and produces ranked buyer archetypes.

### 8.4 `CustomerCrossSellService`

Local collaborative-filtering style opportunity scoring.

No customer data leaves the application.

### 8.5 `MarketResearchService`

Coordinates provider call, validates result schema, records sources, caches research per product/profile hash, and exposes errors without breaking internal advisor functionality.

### 8.6 `InteractiveStrategyAdvisorService`

Builds sanitized advisor context, calls the synthesis provider if enabled, validates JSON response, and merges it with deterministic recommendations.

### 8.7 `ExperimentService`

Creates, starts, and closes experiments.

Automatically captures baseline where possible.

### 8.8 `ExperimentEvaluationService`

Computes:

- baseline revenue/day;
- result revenue/day;
- quantity/day;
- observed uplift;
- target achievement;
- optional control-cohort delta;
- decision recommendation (`SCALE`, `MODIFY`, `STOP`).

---

## 9. Strategy Memory

Completed experiments become company-specific evidence.

Future advisor context may include only aggregate experiment learnings, for example:

- “Contractor bundle tests: 3 experiments; median observed uplift +18%.”
- “Marketplace 10% voucher: 2 tests; median uplift +2%, AOV -9%.”

Recommendations can then prioritize approaches with positive company history.

A minimum sample rule prevents over-learning from one experiment. The UI must display the number of prior experiments supporting a learned recommendation.

---

## 10. Online/Offline Measurement Rules

### Online

Use sales line/channel classification already present in analytics. When only ERP sales data exists, restrict claims to ERP-observable outcomes.

Future connectors can add marketplace metrics without changing advisor interfaces.

### Offline

Measure account-level and salesperson-level outcomes from sales data:

- contacted/review cohort (manual experiment target list);
- recovered sales;
- active/reactivated buyers;
- quantity and sales uplift;
- quotation/demo counts only if manually recorded or later integrated.

---

## 11. Routes

All routes remain behind `dashboard.auth`, `dashboard.audit`, and `dashboard.permission:business_analytics`.

Planned routes:

- `GET /business-analytics/product-advisor`
- `GET /business-analytics/product-advisor/items`
- `GET /business-analytics/product-advisor/review/{itemCode}`
- `POST /business-analytics/product-advisor/profile`
- `POST /business-analytics/product-advisor/research`
- `POST /business-analytics/product-advisor/ask`
- `POST /business-analytics/product-advisor/experiments`
- `PATCH /business-analytics/product-advisor/experiments/{experiment}`
- `POST /business-analytics/product-advisor/experiments/{experiment}/evaluate`

Mutating routes are audited.

---

## 12. UI Structure

### Header

- Product search
- Company/source
- Review period
- Review Product button

### Product Health strip

- Sales 3M
- YoY
- Momentum 3M
- Online share
- Active buyers
- Lost revenue
- Stock (if supplied)
- status badge

### Tabs / sections

1. **Diagnosis**
2. **Market Opportunities**
3. **Customer Cross-Sell**
4. **Online Strategy**
5. **Offline Strategy**
6. **Ask Advisor**
7. **Experiments**

Advisor outputs use visible labels:

- blue/neutral: `FACT`
- secondary: `INFERENCE`
- emphasized: `RECOMMENDATION`
- experiment card: `TEST`
- external citation badge: `EXTERNAL EVIDENCE`

Color is supplemental; text labels remain mandatory.

---

## 13. Error / Degraded Modes

### SQL Server unavailable

Use cached local data mart and visibly show last successful sync date.

### AI provider disabled

All internal facts, diagnosis, cross-sell, rule-based strategies, and experiment tracking continue to work. Internet research and natural-language synthesis are marked unavailable.

### AI provider fails/times out

Do not fail the page. Preserve deterministic output and show a concise research/advisor warning.

### Research result invalid

Reject provider response; do not render unvalidated JSON as strategy.

### Insufficient history

Show data-quality warning and lower confidence rather than filling missing history with invented values.

---

## 14. Audit and Security

Audit actions:

- view product advisor;
- save product profile;
- run external research;
- ask advisor;
- create/start/update/close experiment;
- evaluate experiment.

External provider prompts must be logged only in sanitized form or hashed metadata; do not write sensitive raw customer-level data into application logs.

API keys remain server-side `.env` values and are never rendered to Blade/JavaScript.

---

## 15. Testing Requirements

### Unit tests

- Product diagnosis classifications.
- Opportunity score bounds and evidence requirements.
- Customer cross-sell ranking.
- Context sanitizer removes customer and salesperson identifiers.
- External research JSON schema validation.
- Advisor response schema validation.
- Experiment baseline/result math.
- SCALE/MODIFY/STOP rules.

### Integration tests

Using SQLite fixtures:

- product review pulls correct 1/3/6/12M facts;
- selected item does not mix other items;
- cross-sell excludes customers who already bought selected item;
- daily experiment window is correct;
- routes require `business_analytics` permission;
- external provider failure degrades gracefully.

### View verification

- compile Blade with Laravel compiler;
- `php -l` compiled output;
- route list verification;
- no external research result is rendered without source metadata.

### Provider tests

Use fake providers in automated tests. Tests must not depend on live internet/API.

---

## 16. Phase 3 Delivery Scope

### Included in v1

- Product selector and product profile.
- Product internal fact review.
- Decline diagnosis.
- Market buyer archetype ranking.
- Optional internet/AI market research provider interface and first adapter.
- Customer cross-sell candidates.
- Online/offline strategy cards.
- Interactive Ask Advisor.
- Experiment creation and result evaluation.
- 180-day daily mart.
- Strategy memory based on completed experiments.
- Windows local installer for `C:\xampp82\htdocs\sales-report-web`.
- Linux patch package for server deployment.

### Explicitly not included in v1

- automatic ad creation/publishing;
- automatic marketplace price changes;
- automatic email/WhatsApp outreach;
- scraping private/non-public prospect data;
- claiming profitability without margin/COGS data;
- CTR/CPC/ROAS claims without ad-platform data;
- causal claims without valid control design;
- automatic customer-industry inference from company name.

These boundaries keep the first advisor useful, auditable, and safe to deploy.

---

## 17. Success Criteria

Phase 3 is successful when a user can select one declining item and, in one workflow, obtain:

1. a reproducible factual diagnosis;
2. ranked buyer/industry archetypes with reasons;
3. internal cross-sell/customer opportunities;
4. separate online and offline strategy recommendations;
5. an interactive answer to a product strategy question;
6. at least one measurable experiment card;
7. a stored experiment whose result can later be evaluated against baseline;
8. transparent distinction between internal facts, external evidence, inference, recommendation, and observed test result.
