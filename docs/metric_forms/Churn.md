# Churn

## **Overall Form Setup**

* **Form Title**: “Churn Measurement Form”  
* **Form Description**:  
  *“We appreciate you taking a few minutes to describe how your team measures churn. Your answers will help us compare definitions across departments and resolve any discrepancies. Thank you\!”*

Include an estimate of how long it will take (e.g., 5–10 minutes) and a note on which questions are mandatory.

---

## **1\. Team / Department *(context meta)***

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short answer  
   * **Required**: Yes  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – used for labeling rows, not for discrepancy scoring or the Definition block.  
   * **Why**: We must identify who’s providing the churn definition—Finance, Product, Marketing, Customer Success, etc.

**Prompt**:

* *“E.g., Finance, Product, Marketing, Customer Success, etc.”*

---

## **2\. Which Churn Metrics Do You Track? (Logo vs. Revenue) *(definition + context)* **

2. **Question**: “Which churn scopes do you track? (select canonical values)”  
   * **Question Type**: Checkboxes  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – part of the core Definition and reconciliation payload.  
   * **Why**: The discrepancy engine needs consistent enum values (`logo`, `revenue`, `net_revenue`) so answers normalize across teams.

**Canonical Options**:

* `logo` — Count of customers/logos that churn.  
* `revenue` — Gross recurring revenue churn.  
* `net_revenue` — Net revenue churn (after offsets such as expansion).  
* “Other” (with explanation) — only if a team insists on a custom slice.

**Context Field**:

*Add `churn_types_notes` (paragraph, optional, meta) so teams can clarify filters/segments without polluting discrepancy comparisons.*  
*Role / visibility*: `context`, excluded from consensus (`exclude_from_consensus=true`).

---

## **3\. Time Frame for Churn Measurement *(definition + context)* **

3. **Question**: “Which reporting window do you use for churn?”  
   * **Question Type**: Dropdown  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – key part of the agreed definition.  
   * **Why**: We compare `monthly`, `quarterly`, `annual`, or `rolling` submissions directly, so structured values are mandatory.

**Canonical Options**: `monthly`, `quarterly`, `annual`, `rolling` (plus “Other” if necessary).

**Context Field**: `churn_timeframe_notes` (optional meta textarea) captures snapshot timing or offset rules.  
*Role / visibility*: `context`, excluded from consensus (`exclude_from_consensus=true`).

---

## **4\. Handling of Downgrades vs. Full Cancellation *(governance + diagnostic)* **

4. **Question**: “How do downgrades factor into churn?”  
   * **Question Type**: Dropdown  
   * **Required**: Yes  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – reconciled explicitly in workshops and shown in Governance/Diagnostic.

**Canonical Options**:

1. `count_as_churn` — every recurring revenue reduction.  
2. `exclude_downgrades` — only full cancellations.  
3. `track_as_contraction` — downgrades in a separate contraction bucket.  
4. “Other” (requires explanation).

**Context Field**: `downgrade_handling_notes` (meta textarea) stores the narrative examples.
*Role / visibility*: `diagnostic` – captures nuance and edge cases.

---

## **5\. Voluntary vs. Involuntary Churn *(governance + context)* **

5. **Question**: “How do you treat voluntary vs involuntary churn?”  
   * **Question Type**: Dropdown  
   * **Required**: Yes  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – helps align when involuntary churn is bundled vs separated.  
   * **Why**: Involuntary churn can skew results if not treated separately.

**Canonical Options**: `separate`, `combined`, “Other (explain).”  
Pair with `voluntary_treatment_notes` for grace-period/grace-reversal details.  
`voluntary_treatment_notes` is `context` (secondary display).

---

## **6\. Churn Timing / Cutoff *(governance + context)* **

6. **Question**: “When do you mark churn effective?”  
   * **Question Type**: Multiple choice  
   * **Required**: Yes  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – a major driver of discrepancies across teams.  
   * **Why**: Some remove them immediately; others wait until the billing cycle ends.

**Canonical Options**: `immediate`, `end_of_cycle`, `after_grace`, “Other (describe).”  
Capture nuances under `churn_timing_notes` so edge cases stay meta-only.
`churn_timing_notes` is `context` – additional narrative only.

---

## **7\. Special Cases & Exceptions (Optional, diagnostic meta)**

7. **Question**: “Special cases (trials, pauses, refunds) that affect churn?”  
   * **Question Type**: Paragraph (`special_case_notes`, meta)  
   * **Required**: Optional  
   * **Why**: Documents unconventional policies without triggering discrepancy rules.
   * **Role / visibility**: `diagnostic` – appears in Governance/Diagnostic, not the core Definition.

---

## **8\. Example Calculation (Optional, definition meta)**

8. **Question**: “Provide a short example showing how you calculate churn in a typical month or quarter.”  
   * **Question Type**: Paragraph  
   * **Required**: Optional  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – helps document the Handbook-style example without affecting discrepancy severity.  
   * **Why**: Helps reveal hidden assumptions. e.g., “We started with 100 paying accounts, ended with 95, so churn is 5%.”

---

## **9\. Known Pain Points or Observed Discrepancies *(diagnostic meta)* **

9. **Question**: “Where do churn definitions or numbers differ across teams?”  
   * **Question Type**: Paragraph (`churn_pain_points`, meta)  
   * **Required**: Required  
   * **Why**: Directly captures friction—maybe someone lumps downgrades in churn, or another disagrees about timing.
   * **Role / visibility**: `diagnostic` – key workshop cue, appears in Governance/Diagnostic and secondary surfacing.

---

## **10\. Additional Notes or Questions (Optional, context meta)**

10. **Question**: “Any extra comments, disclaimers, or clarifications you want to add?”  
* **Question Type**: Paragraph  
* **Required**: Optional  
* **Role / visibility**: `context` – catch-all for unique scenarios or disclaimers, shown in secondary displays.
