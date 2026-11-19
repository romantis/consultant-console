# Conversion Rate 

## **Overall Form Setup**

* **Form Title**: “Conversion Rate Measurement Form”  
* **Form Description**: *“We appreciate you taking a few minutes to describe how your team measures Conversion Rate. Your answers will help us compare definitions across departments and resolve any discrepancies. Thank you\!”*

---

## **Core vs. Meta Questions**

| Field | Role | Usage | Rationale |
| --- | --- | --- | --- |
| `team_department` | `context` | Secondary only | Identifies who answered but does not change the definition. |
| `conversion_actions` | `definition` | Definition + Resolution | Dictates which actions populate the conversion numerator. |
| `conversion_actions_notes` | `context` | Secondary only | Adds context about those actions without altering the enums. |
| `conversion_denominator` | `definition` | Definition + Resolution | Establishes the denominator cohort, which drives the rate. |
| `conversion_denominator_notes` | `context` | Secondary only | Explains filters or exclusions applied to the denominator. |
| `conversion_timeframe` | `definition` | Definition + Resolution | Sets the measurement window the engine compares. |
| `conversion_timeframe_notes` | `context` | Secondary only | Captures timezone offsets or grace periods for context. |
| `funnel_stage_tracked` | `definition` | Definition + Resolution | Clarifies the funnel transition being measured. |
| `funnel_stage_notes` | `context` | Secondary only | Describes segmentation nuances beyond the canonical stages. |
| `partial_conversion_tracking` | `governance` | Resolution | Determines whether partial upgrades enter the numerator. |
| `partial_conversion_notes` | `diagnostic` | Secondary + Governance/Diagnostic | Documents thresholds or revenue minimums for those upgrades. |
| `attribution_model` | `governance` | Resolution | Channel-credit setting that influences how teams interpret the metric. |
| `attribution_model_notes` | `diagnostic` | Secondary + Governance/Diagnostic | Provides detail on attribution tooling or weights. |
| `conversion_example` | `definition` | Definition only | Illustrative example for Handbook/Definition, excluded from discrepancy severity. |
| `conversion_pain_points` | `diagnostic` | Governance/Diagnostic | Qualitative discrepancies surfaced by respondents. |
| `additional_notes` | `context` | Secondary only | Catch-all narrative context. |

---

## **1\. Team / Department**

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short Answer (`team_department`, **meta**, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – used for labeling rows, not for discrepancy scoring or the Definition block.  
   * **Why**: You need to attribute each response to the correct team (Marketing, Sales, Product, Finance, etc.).

**Prompt**:

* *“e.g., Marketing, Sales, Product, Finance, Customer Success, etc.”*

---

## **2\. What Action(s) Count as a “Conversion”?**

2. **Question**: “Which specific action(s) do you consider a ‘conversion’ in your reporting?”  
   * **Question Type**: Checkbox group (multi-select) with canonical values plus an “Other” option (`conversion_actions`, **core**, `definition`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – part of the core Definition and reconciliation payload.  
   * **Why**: The biggest source of confusion—some track sign-ups, some track paying customers, some track upgrades.

**Canonical Options**:

1. `form_completion` – Form completion or lead capture  
2. `trial_activation` – Trial sign-up or activation event  
3. `purchase_checkout` – Purchase or subscription checkout  
4. `contract_signature` – Contract signed / closed won  
5. `product_upgrade` – Upgrade or expansion event  
6. `Other` – Respondents describe a custom action

**Follow-up (`conversion_actions_notes`)**  
*Question Type*: Paragraph (`conversion_actions_notes`, **meta**, `context`, optional)  
*Prompt*: “Add nuance or exceptions for the actions selected (segments, channels, etc.).”

---

## **3\. Which Group Forms the Denominator?**

3. **Question**: “*Who or what forms the ‘total group’ you measure conversion against?*”  
   * **Question Type**: Select with canonical values plus “Other” (`conversion_denominator`, **core**, `definition`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – core denominator choice for the metric.  
   * **Why**: Different denominators (all website visitors vs. leads vs. free trial users) produce drastically different conversion rates.

**Canonical Options**:

1. `all_visitors` – All visitors or sessions  
2. `captured_leads` – All captured leads  
3. `qualified_leads` – Qualified leads (MQL/SQL)  
4. `trial_accounts` – Trial or freemium accounts  
5. `pipeline_opportunities` – Pipeline opportunities / deals  
6. `prior_step_customers` – Customers at the prior step (pilot, freemium, etc.)  
7. `Other` – Describe the denominator group

**Follow-up (`conversion_denominator_notes`)**  
*Question Type*: Paragraph (`conversion_denominator_notes`, **meta**, `context`, optional)  
*Prompt*: “Detail filters, cohorts, or exclusions that shape this denominator.”

---

## **4\. Time Frame for Conversion**

4. **Question**: “Over what time frame do you measure your conversion rate?”  
   * **Question Type**: Select with canonical values plus “Other” (`conversion_timeframe`, **core**, `definition`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – part of the core agreed Definition.  
   * **Why**: A short (7-day) vs. long (90-day) window can lead to different reported conversion percentages.

**Canonical Options**:

1. `within_7_days` – Within 7 days  
2. `within_30_days` – Within 30 days  
3. `within_90_days` – Within 90 days  
4. `rolling_window` – Rolling or indefinite window  
5. `event_triggered` – Event-triggered / campaign-specific window  
6. `Other` – Describe the window

**Follow-up (`conversion_timeframe_notes`)**  
*Question Type*: Paragraph (`conversion_timeframe_notes`, **meta**, `context`, optional)  
*Prompt*: “Mention timezone handling, lag windows, or grace periods.”

---

## **5\. Funnel Step or Stage (If Applicable)**

5. **Question**: “Which part of the funnel are you tracking for this conversion?”  
   * **Question Type**: Select with canonical stage transitions plus “Other” (`funnel_stage_tracked`, **core**, `definition`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – identifies the specific funnel step being defined.  
   * **Why**: ‘Conversion’ can refer to different funnel steps, like visitor-to-lead, lead-to-customer, trial-to-paid, etc.

**Canonical Options**:

1. `visitor_to_lead` – Visitor → Lead  
2. `lead_to_mql` – Lead → MQL  
3. `mql_to_sql` – MQL → SQL  
4. `sql_to_opportunity` – SQL → Opportunity  
5. `opportunity_to_customer` – Opportunity → Customer (closed won)  
6. `trial_to_paid` – Trial/freemium → Paid  
7. `expansion_upgrade` – Customer → Expansion/upgrade  
8. `Other` – Describe the exact funnel step pair

**Follow-up (`funnel_stage_notes`)**  
*Question Type*: Paragraph (`funnel_stage_notes`, **meta**, `context`, optional)  
*Prompt*: “Clarify segmentation, region splits, or multiple funnels that change this stage.”

---

## **6\. Attribution Model (If Relevant)**

6. **Question**: “Which attribution model assigns conversions to sources?”
   * **Question Type**: Select with canonical values plus “Other” (`attribution_model`, `governance`)
   * **Required**: Optional but useful if marketing channels are debated.  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – channel-credit policy visible in Governance/Diagnostic and reconciliation flows.  
   * **Why**: Different attribution models allocate the same conversions to different sources. The field participates in reconciliation because source-level reports can conflict, but attribution does not change the total conversion count unless source filters also change.

**Canonical Options**:

1. `first_touch` – First-touch attribution  
2. `last_touch` – Last-touch attribution  
3. `multi_touch` – Multi-touch attribution  
4. `no_model` – No formal attribution model  
5. `Other` – Describe the model

**Follow-up (`attribution_model_notes`)**  
*Question Type*: Paragraph (`attribution_model_notes`, **meta**, `diagnostic`, optional)  
*Prompt*: “Add detail on the attribution weights, tools, or lookback windows.”

---

## **7\. Partial Conversions or Upgrades**

7. **Question**: “Do you consider partial upgrades or smaller add-on purchases as a ‘conversion,’ or do you only track the main purchase/subscription?”  
   * **Question Type**: Select with canonical values plus “Other” (`partial_conversion_tracking`, **core**, `governance`)  
   * **Required**: Yes (core for numerator definition)  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – shows how strictly teams treat partial events in the numerator.  
   * **Why**: Some businesses count an upgrade to a mid-tier plan as a conversion, while others only count the initial paid subscription.

**Canonical Options**:

1. `primary_only` – Only the main purchase/subscription  
2. `include_upgrades` – Primary conversion plus partial upgrades  
3. `include_addons` – Primary conversion plus add-on purchases  
4. `separate_events` – Each event tracked separately  
5. `Other` – Describe the policy

**Follow-up (`partial_conversion_notes`)**  
*Question Type*: Paragraph (`partial_conversion_notes`, **meta**, `diagnostic`, optional)  
*Prompt*: “Note thresholds or revenue minimums for counting upgrades.”

---

## **8\. Example Calculation (Optional)**

8. **Question**: “Could you provide a brief example illustrating how you calculate your conversion rate in a typical scenario?”  
   * **Question Type**: Paragraph (`conversion_example`, **meta**, `definition`)  
   * **Required**: Optional  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block and Draft Final Definition but is excluded from discrepancy severity.  
   * **Why**: Real or hypothetical examples can reveal hidden logic (e.g., “We had 1,000 visitors, 50 purchased \= 5% conversion.”).

---

## **9\. Known Pain Points or Observed Discrepancies**

9. **Question**: “Where do you see the biggest confusion or differences about conversion rate definitions across teams?”  
   * **Question Type**: Paragraph (`conversion_pain_points`, **meta**, `diagnostic`)  
   * **Required**: Recommended  
   * **Role / visibility**: `diagnostic` – directly informs Governance/Diagnostic review.  
   * **Why**: Encourages them to note known friction—like differences in denominator, time window, or multiple partial conversions.

---

## **10\. Additional Notes or Questions (Optional)**

10. **Question**: “Anything else we should know about how you measure or interpret conversion rate?”  
* **Question Type**: Paragraph (`additional_notes`, **meta**, `context`)  
* **Required**: Optional  
* **Role / visibility**: `context` – catch-all for disclaimers or edge cases, shown in secondary displays.  
* **Why**: A catch-all for disclaimers or edge cases (e.g., complex B2B cycles that take months, a unique product-led funnel, etc.).
