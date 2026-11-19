# Lifetime Value (LTV)

## **Overall Form Setup**

* **Form Title**: “Lifetime Value (LTV) Measurement Form”  
* **Form Description**:  
  *“We’d like to understand how your team defines and calculates Lifetime Value (LTV). Your responses will help compare definitions across departments and reconcile any discrepancies.”*

Include the approximate completion time (5–10 minutes) and which items are mandatory.

---

## **1\. Team / Department *(context meta)***

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short Answer (`team_department`, meta, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – used for labeling rows, not for discrepancy scoring or the Definition block.  
   * **Why**: We need to know who’s providing the LTV definition (Finance, Marketing, Product, etc.).  
   * **Prompt**: *“For instance, Finance, Product, Marketing, Customer Success…”*

---

## **2\. How Do You Define LTV Conceptually? *(definition + context)* **

2. **Question**: “How do you calculate or conceptualize LTV? (e.g., simple ARPU × average lifespan, discounted cash flow, margin-based, etc.)”  
   * **Question Type**:  
     * Core: Single choice (`ltv_formula`, **core**, `definition`)  
     * Meta: Long answer (`ltv_formula_notes`, **meta**, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – the conceptual model drives the Definition and reconciliation.  
   * **Why**: Different teams might do “monthly revenue × months,” or a more advanced NPV approach; we want a canonical model type for discrepancies plus narrative detail for context.

**Canonical Options (`ltv_formula`)**:

* `simple_revenue_lifespan` – Simple revenue × average lifespan (e.g., ARPU × months)  
* `discounted_cash_flow` – Discounted cash flow / NPV model  
* `margin_based` – Margin-based LTV (using gross margin)  
* `cohort_retention` – Cohort or retention-based model  
* `other` (via “Other” + free-text)

**Notes Field (`ltv_formula_notes`, meta, `context`)**:

* Prompt: *“Briefly describe your main formula (e.g., ARPU × average months, or discounted margin-based model) and any key assumptions.”*

---

## **3\. Time Horizon or Customer Lifespan Assumption *(definition + context)* **

3. **Question**: “Which time horizon or customer lifespan do you assume for LTV? (e.g., 24 months, 36 months, indefinite until churn, etc.)”  
   * **Question Type**:  
     * Core: Single choice (`lifespan_assumption`, **core**, `definition`)  
     * Meta: Long answer (`lifespan_notes`, **meta**, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – the assumed lifespan is part of the core Definition.  
   * **Why**: A 2-year assumption vs. a 3-year or indefinite approach can drastically change the LTV.

**Canonical Options (`lifespan_assumption`)**:

* `24_months` – 24 months  
* `30_months` – 30 months  
* `36_months` – 36 months  
* `churn_derived` – Based on churn rate (e.g., 1 / monthly churn)  
* `other` (via “Other” + free-text)

**Notes Field (`lifespan_notes`, meta, `context`)**:

* Prompt: *“Add context on customer lifespan or churn assumptions (e.g., different horizons by segment, caps, or rolling windows).”*

---

## **4\. Revenue Streams Included (Subscription, Upsells, Add-ons) *(definition + context)* **

4. **Question**: “Which revenue streams do you include in your LTV calculation? (Check all that apply)”  
   * **Question Type**:  
     * Core: Multi-select checkboxes (`revenue_streams_included`, **core**, `definition`)  
     * Meta: Long answer (`revenue_stream_notes`, **meta**, `context`)  
   * **Required**: Yes (for the checkbox group)  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – defines which revenue streams the LTV includes.  
   * **Why**: Some only use base subscription revenue, others include upsells, cross-sells, etc.

**Canonical Options (`revenue_streams_included`)**:

* `base_subscription` – Base subscription fees  
* `upsells_addons` – Upsells or add-on features  
* `professional_services` – Professional services / implementation fees  
* `usage_based` – Usage-based / overages  
* `one_time_fees` – One-time setup fees  
* `other` (via “Other” + free-text)

**Notes Field (`revenue_stream_notes`, meta, `context`)**:

* Prompt: *“Note any special revenue streams or exclusions (e.g., internal/comp accounts, credits, unusual usage fees).”*

---

## **5\. Discount Rate or Present Value Approach *(governance + context)* **

5. **Question**: “Do you discount future revenue to a present value, or just multiply average monthly revenue by lifespan?”  
   * **Question Type**:  
     * Core: Single choice (`discount_method`, **core**, `governance`)  
     * Meta: Long answer (`discount_method_notes`, **meta**, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – controls whether LTV uses NPV or simple revenue × lifespan.  
   * **Why**: If one team is using net present value (NPV) with a certain discount rate, and another is not, LTV will differ significantly.

**Canonical Options (`discount_method`)**:

* `no_discounting` – No discounting (simple ARPU × months)  
* `discount_rate` – Apply a discount rate for present value (e.g., 8–10% annual)  
* `hybrid` – Partial discounting or hybrid method  
* `other` (via “Other” + free-text)

**Notes Field (`discount_method_notes`, meta, `context`)**:

* Prompt: *“Add detail on discount rates, time horizon, or NPV setup (e.g., which rate, how often you recompute).”*

---

## **6\. Cost Components (If You Calculate Margin-Based LTV) *(governance + context)* **

6. **Question**: “Is your LTV based on **gross revenue** or **gross margin** (subtracting cost of goods sold), or do you factor in overhead costs?”  
   * **Question Type**:  
     * Core: Single choice (`margin_basis`, **core**, `governance`)  
     * Meta: Long answer (`margin_basis_notes`, **meta**, `context`)  
   * **Required**: Optional (encouraged for margin-based teams)  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – defines whether we reason about revenue or margin in LTV.  
   * **Why**: Some teams prefer a revenue-based LTV, others prefer margin-based to see true profitability.

**Canonical Options (`margin_basis`)**:

* `revenue_only` – Revenue-based only (no costs subtracted)  
* `gross_margin` – Gross margin–based (subtract direct costs/COGS)  
* `fully_loaded_margin` – Fully loaded margin (includes overhead)  
* `other` (via “Other” + free-text)

**Notes Field (`margin_basis_notes`, meta, `context`)**:

* Prompt: *“Explain which cost components are included or excluded (e.g., COGS, support, hosting, overhead).”*

---

## **7\. Handling of One-Time Fees or Setup Costs *(governance + context)* **

7. **Question**: “Do you include any one-time setup fees or onboarding charges in the LTV calculation?”  
   * **Question Type**:  
     * Core: Single choice (`one_time_fee_handling`, **core**, `governance`)  
     * Meta: Long answer (`one_time_fee_notes`, **meta**, `context`)  
   * **Required**: Optional (core field); notes encouraged when behavior is nuanced  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – clarifies how non-recurring revenue is treated in LTV.  
   * **Why**: Some organizations add them, others consider them non-recurring and exclude them from LTV.

**Canonical Options (`one_time_fee_handling`)**:

* `exclude_one_time` – No, exclude one-time fees from LTV  
* `include_upfront_or_spread` – Yes, recognize upfront or spread across months  
* `depends_on_type` – Depends on the type of fee  
* `other` (via “Other” + free-text)

**Notes Field (`one_time_fee_notes`, meta, `context`)**:

* Prompt: *“Note any special handling for setup, implementation, or onboarding fees (e.g., amortization rules, thresholds, exceptions).”*

---

## **8\. Example Calculation (Optional, definition meta)**

8. **Question**: “Could you provide a brief example illustrating your LTV formula in practice?”  
   * **Question Type**: Paragraph (`ltv_example`, **meta**, `definition`)  
   * **Required**: Optional  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block and Draft Final Definition, but is excluded from discrepancy severity.  
   * **Why**: A real or hypothetical scenario helps reveal hidden assumptions, e.g., monthly revenue, discount rate, lifespan, etc.

---

## **9\. Known Pain Points or Observed Discrepancies *(diagnostic meta)* **

9. **Question**: “Where do you see the biggest confusion or conflict about LTV definitions across teams?”  
   * **Question Type**: Paragraph (`ltv_pain_points`, `diagnostic`)  
   * **Required**: Recommended  
   * **Role / visibility**: `diagnostic` – used in Governance/Diagnostic views during workshops to highlight gaps.  
   * **Why**: Directly captures known friction (e.g., different lifespans, discount rates, or margin-based vs. revenue-based).

---

## **10\. Additional Notes or Questions (Optional, context meta)**

10. **Question**: “Any final comments, disclaimers, or unique scenarios about how you measure LTV?”  
* **Question Type**: Paragraph (`additional_notes`, `context`)  
* **Required**: Optional  
* **Role / visibility**: `context` – catch-all narrative in secondary displays, excluded from discrepancy scoring.  
* **Why**: Catch-all for disclaimers or special contract types, extremely long-lifespan accounts, etc.
