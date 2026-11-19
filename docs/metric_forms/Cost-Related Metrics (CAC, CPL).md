# CAC & CPL

## **Overall Form Setup**

* **Form Title**: “CAC & CPL Measurement Form”  
* **Form Description**:  
  *“We appreciate your input on how your team calculates Customer Acquisition Cost (CAC) and Cost per Lead (CPL). Your responses will help us compare methods across departments and reconcile any differences.”*

Include an estimated completion time (5–10 minutes) and note which items are mandatory.

---

## **1\. Team / Department *(context meta)***

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short Answer (`team_department`, **meta`, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – used for labeling rows, not for discrepancy scoring or the Definition block.  
   * **Why**: You must know who’s providing the responses (Finance, Marketing, Sales, Product, etc.).  
   * **Prompt**: *“e.g., Finance, Marketing, Sales, Product…”*

---

## **2\. Which Costs Are Included in CAC/CPL? *(definition + context)* **

2. **Question**: “Which costs do you include when calculating CAC or CPL? (Check all that apply)”  
   * **Question Type**:  
     * Core: Checkbox group (`costs_included`, **core**, `definition`)  
     * Meta: Paragraph (`costs_included_notes`, **meta**, `context`)  
   * **Required**: Yes (for the checkbox group)  
   * **Role / visibility**: `costs_included` is `definition`, included in consensus (`exclude_from_consensus=false`) – part of the core Definition and reconciliation payload.  
   * **Why**: The largest source of confusion is often *which* expenses are part of the cost base.

**Canonical Options (`costs_included`)**:

* `ad_spend` – Direct advertising spend (Google, LinkedIn, etc.)  
* `marketing_comp` – Marketing salaries / agency fees  
* `sales_comp` – Sales salaries / commissions  
* `overhead` – Overhead (office, software, general expenses)  
* `direct_campaign_only` – Only direct campaign costs (ad spend + campaign tools)  
* `other` – via “Other” option + free-text

**Follow-up (`costs_included_notes`)**  
*Question Type*: Paragraph (optional, meta, `context`)  
*Prompt*: “Add detail on which GL codes or cost centers are included or excluded.”

---

## **3\. How Do You Define a “Lead” for CPL? *(definition + context)* **

3. **Question**: “What do you count as a ‘lead’ in your CPL calculation? (e.g., any form fill, only qualified leads, etc.)”  
   * **Question Type**:  
     * Core: Select (`lead_definition`, **core**, `definition`)  
     * Meta: Paragraph (`lead_definition_notes`, **meta`, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – defines the CPL denominator cohort.  
   * **Why**: The denominator in CPL (number of leads) can vary across teams—some treat any new contact as a lead, others only count qualified ones.

**Canonical Options (`lead_definition`)**:

* `any_contact` – Any new contact or form fill  
* `marketing_qualified` – Marketing Qualified Leads (MQLs)  
* `sales_qualified` – Sales Qualified Leads (SQLs)  
* `opportunity` – Opportunities / pipeline deals  
* `product_qualified` – Product-qualified trials or signups  
* `other` – via “Other” option + free-text

**Follow-up (`lead_definition_notes`)**  
*Question Type*: Paragraph (optional, meta, `context`)  
*Prompt*: “Add nuance or qualifiers to your lead definition (e.g., score thresholds, channels, regions).”

---

## **4\. How Do You Define a “Customer” for CAC? *(definition + context)* **

4. **Question**: “When do you consider someone an ‘acquired customer’ in your CAC calculation? (e.g., contract signed, first payment processed, etc.)”  
   * **Question Type**:  
     * Core: Select (`customer_definition`, **core`, `definition`)  
     * Meta: Paragraph (`customer_definition_notes`, **meta`, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – defines the CAC denominator (who counts as acquired).  
   * **Why**: The denominator in CAC (number of new paying customers) depends on whether a user must pay or simply sign up.

**Canonical Options (`customer_definition`)**:

* `contract_signed` – Contract signed / closed won  
* `first_payment` – First payment processed  
* `go_live` – Go-live date or onboarding completion  
* `activated_subscription` – Activated paid subscription or seat  
* `other` – via “Other” option + free-text

**Follow-up (`customer_definition_notes`)**  
*Question Type*: Paragraph (optional, meta, `context`)  
*Prompt*: “Add nuance or exceptions for customer counting (e.g., free tiers, pilots, grace periods).”

---

## **5\. Time Frame for Matching Costs to Leads/Customers *(governance + context)* **

5. **Question**: “How do you match the timing of your sales/marketing costs to the leads or customers acquired? (e.g., same month vs. cohort approach vs. quarter)”  
   * **Question Type**:  
     * Core: Select (`cost_timing`, **core`, `governance`)  
     * Meta: Paragraph (`cost_timing_notes`, **meta`, `context`)  
   * **Required**: Yes (for the select)  
   * **Role / visibility**: `cost_timing` is `governance`, included in consensus (`exclude_from_consensus=false`) – key governance lever for when costs are recognized.  
   * **Why**: Some attribute cost only to that month’s new leads/customers, others do a cohort approach if leads take months to convert.

**Canonical Options (`cost_timing`)**:

* `same_month` – We book costs in the same month as the leads/customers  
* `cohort` – We use a cohort approach across months  
* `quarterly_or_semiannual` – Quarterly or semiannual aggregation  
* `other` – via “Other” option + free-text

**Follow-up (`cost_timing_notes`)**  
*Question Type*: Paragraph (optional, meta, `context`)  
*Prompt*: “Describe lookback windows or lag between spend and results.”

---

## **6\. Deduplication & Tracking Across Multiple Touchpoints (Optional, governance + diagnostic)**

6. **Question**: “If the same person interacts with multiple campaigns, do you count them multiple times for CPL, or unify them into one lead?”  
   * **Question Type**:  
     * Core: Select (`deduplication_method`, **core**, `governance`, optional)  
     * Meta: Paragraph (`deduplication_notes`, **meta`, `diagnostic`)  
   * **Required**: Optional (but recommended if multi-touch campaigns exist)  
   * **Role / visibility**: `deduplication_method` is `governance`, included in consensus (`exclude_from_consensus=false`) – directly affects lead counts for CPL and CAC.  
   * **Why**: Duplicate leads can inflate lead counts, artificially lowering CPL. Similarly for CAC if multiple campaigns or channels are credited.

**Canonical Options (`deduplication_method`)**:

* `count_all_touches` – Count each campaign touch as a separate lead  
* `unify_by_person` – Unify multiple touches into one lead per person  
* `hybrid_rules` – Hybrid rules depending on channel or stage  
* `other` – via “Other” option + free-text

**Follow-up (`deduplication_notes`)**  
*Question Type*: Paragraph (optional, meta, `diagnostic`)  
*Prompt*: “Detail any matching logic or IDs used for deduplication (e.g., email, CRM ID).”

---

## **7\. Handling of Partial / Indirect Costs (Optional, governance + context)**

7. **Question**: “Do you include partial overhead (e.g., shared tools, general salaries), or do you only track direct campaign spend?”  
   * **Question Type**:  
     * Core: Select (`overhead_handling`, **core**, `governance`, optional)  
     * Meta: Paragraph (`overhead_handling_notes`, **meta`, `context`)  
   * **Required**: Optional  
   * **Role / visibility**: `overhead_handling` is `governance`, included in consensus (`exclude_from_consensus=false`) – shows how fully loaded the cost base is.  
   * **Why**: Some businesses do a fully loaded cost approach; others only count direct marketing spend.

**Canonical Options (`overhead_handling`)**:

1. `fully_loaded` – Fully loaded: include all marketing, sales, and overhead costs  
2. `direct_only` – Only direct campaign expenses  
3. `partial_allocation` – Partial overhead allocation  
4. `other` – via “Other” option + free-text

**Follow-up (`overhead_handling_notes`)**  
*Question Type*: Paragraph (optional, meta, `context`)  
*Prompt*: “Explain any allocation or apportionment rules.”

---

## **8\. Example Calculation (Optional, definition meta)**

8. **Question**: “Could you provide a short example illustrating how you calculate CAC or CPL in a typical period?”  
   * **Question Type**: Paragraph (`cac_example`, **meta**, `definition`)  
   * **Required**: Optional  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block and Draft Final Definition, but is excluded from discrepancy severity.  
   * **Why**: Real examples (e.g., “Spent $10k in Q1, got 500 leads → $20 CPL”) help clarify the method.

---

## **9\. Known Pain Points or Observed Discrepancies *(diagnostic meta)* **

9. **Question**: “Where do you see the biggest confusion or conflict about CAC/CPL definitions across teams?”  
   * **Question Type**: Paragraph (`cac_pain_points`, **meta**, `diagnostic`)  
   * **Required**: Recommended  
   * **Role / visibility**: `diagnostic` – appears in Governance/Diagnostic to surface known friction (overhead vs direct costs, salary treatment, etc.).  
   * **Why**: Directly captures known friction points (e.g., overhead vs. direct cost, how to handle sales salaries, etc.).

---

## **10\. Additional Notes or Questions (Optional, context meta)**

10. **Question**: “Any final comments, disclaimers, or unique scenarios about how you measure CAC or CPL?”  
* **Question Type**: Paragraph (`additional_notes`, **meta**, `context`)  
* **Required**: Optional  
* **Role / visibility**: `context` – catch-all narrative in secondary displays.  
* **Why**: Catch-all for disclaimers (e.g., large enterprise deals with extended sales cycles, partial overhead, or multiple channel attributions).
