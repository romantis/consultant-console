# Leads & Lead Quality

## **Overall Form Setup**

* **Form Title**: “Leads & Lead Quality Measurement Form”  
* **Form Description**:  
  *“We’d like to understand how your team defines a ‘lead’ and how you measure lead quality. Your responses will help us compare definitions across departments and reconcile any differences. Thank you\!”*

Mention approximate completion time (5–10 minutes) and which questions are mandatory.

---

## **1\. Team / Department *(context meta)***

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short Answer (`team_department`, meta, `context`)  
   * **Required**: Yes  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – used for labeling rows, not for discrepancy scoring or the Definition block.  
   * **Why**: We need to identify who’s responding (Marketing, Sales, Finance, etc.).  
   * **Prompt**: *“e.g., Finance, Sales, Marketing, Customer Success, etc.”*

---

## **2\. How Do You Define a “Lead”? *(definition + context)* **

2. **Question**: “What do you count as a lead?”  
   * **Question Type**: Multiple choice (with “Other” + short notes) (`lead_definition`, `definition`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – core Definition of a lead.  
   * **Why**: Different teams call anything from a simple email sign-up to a fully qualified contact a “lead,” so capturing that logic in a canonical category is key for the discrepancy engine.

**Canonical Options**:

* “Any new contact or form fill”  
* “Marketing Qualified Lead (MQL)”  
* “Sales Qualified Lead (SQL)”  
* “Opportunities / pipeline deals”  
* “Product-qualified trials or signups”  
* “Other (please describe)”  

Pair this with a short free-text field (e.g., `lead_definition_notes`, `context`) for qualifiers or edge cases.

---

## **3\. Which “Lead Stages” Do You Use? (MQL, SQL, Opportunity) *(definition + context)* **

3. **Question**: “Which lead stages does your team typically track?”  
   * **Question Type**: Checkboxes (`lead_stages_tracked`, `definition`)  
   * **Required**: Yes  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – describes the stages that drive lead pipeline reporting.  
   * **Why**: Many organizations use MQL (Marketing Qualified Lead), SQL (Sales Qualified Lead), Opportunity, etc.

**Suggested Options** (`lead_stages_tracked`):

* “MQL (Marketing Qualified Lead)”  
* “SQL (Sales Qualified Lead)”  
* “Opportunity”  
* “Other (please specify)”

*(If “Other,” add a follow-up short-answer question conditionally, `lead_stages_notes`, `context`.)*

---

## **4\. Time Frame or Reporting Frequency *(governance + context)* **

4. **Question**: “How often do you measure or report on lead counts/quality? (e.g., monthly, quarterly, rolling, campaign-based, etc.)”  
   * **Question Type**: Multiple choice (with “Other” + optional notes) (`lead_reporting_frequency`, `governance`)  
   * **Required**: Recommended / typically required  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – shows reporting cadence in Governance/Diagnostic and discrepancy support.  
   * **Why**: Some teams report leads monthly or quarterly, others after specific campaigns. Time frames impact how leads are tallied.

**Possible Multiple Choice**:

* “Monthly (calendar-based)”  
* “Quarterly”  
* “Rolling window (e.g., last 30 days)”  
* “Campaign-based (ad hoc after a marketing campaign)”  
* “Other (please describe)”  

Optionally add a short free-text follow-up (e.g., `lead_reporting_notes`, `context`) for timezone cutoffs or offsets.

---

## **5\. Deduplication & Tracking Across Multiple Touchpoints *(governance + diagnostic)* **

5. **Question**: “How do you deduplicate leads across touchpoints?”  
   * **Question Type**: Multiple choice (with “Other” + short notes) (`lead_deduplication`, `governance`)  
   * **Required**: Recommended / typically required  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – directly influences lead counts and quality metrics.  
   * **Why**: Duplicate leads can artificially lower CPL or lead to conflicting lead counts.

**Canonical Options**:

* “Count each form fill / touch as a separate lead”  
* “Unify multiple sign-ups into one lead per person”  
* “Hybrid rules depending on channel or stage”  
* “Other (please describe)”  

Use a companion notes field (e.g., `lead_deduplication_notes`, `diagnostic`) to capture matching logic or ID rules.

---

## **6\. Lead Quality / Scoring Method *(governance + diagnostic)* **

6. **Question**: “How do you determine the quality of a lead? Do you have a scoring model (e.g., points for job title, company size, engagement) or a simpler A/B/C rating?”  
   * **Question Type**: Multiple choice (with “Other” + short notes) (`lead_quality_model`, `governance`)  
   * **Required**: Recommended / typically required  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – surfaces how quality is formalized in Governance/Diagnostic views.  
   * **Why**: Knowing if they use a lead score or a subjective rating clarifies how they label “high-quality” vs. “low-quality.”

**Canonical Options**:

* “No formal scoring model; manual review only”  
* “Numeric score (e.g., 0–100 points)”  
* “Tiered rating (e.g., A/B/C or hot/warm/cold)”  
* “Rule-based qualification (e.g., must meet BANT/ICP criteria)”  
* “Other (please describe)”  

Pair with a short free-text field (e.g., `lead_quality_notes`, `diagnostic`) for the specific signals and thresholds used.

---

## **7\. Special Cases or Edge Scenarios (Optional, diagnostic meta)**

7. **Question**: “Are there any special lead flows (like free-trial users, enterprise vs. SMB leads, or repeated event attendees) that affect how you count or qualify leads?”  
   * **Question Type**: Paragraph (`lead_special_cases`, `diagnostic`)  
   * **Required**: Optional  
   * **Role / visibility**: `diagnostic` – appears in Governance/Diagnostic to highlight edge paths.  
   * **Why**: Some might handle big enterprise deals differently than standard leads, or have separate rules for repeated events.

---

## **8\. Example Calculation or Lifecycle (Optional, definition meta)**

8. **Question**: “Could you provide a short example showing how a new inquiry progresses from ‘new contact’ to an MQL or SQL, and eventually to a customer?”  
   * **Question Type**: Paragraph (`lead_example`, `definition`)  
   * **Required**: Optional  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block and Draft Final Definition but is excluded from discrepancy severity.  
   * **Why**: Real examples help reveal the handoff between marketing and sales, or how the funnel is structured.

---

## **9\. Known Pain Points or Observed Discrepancies *(diagnostic meta)* **

9. **Question**: “Where do you see the biggest confusion or conflict about leads and lead quality between your team and others?”  
   * **Question Type**: Paragraph (`lead_pain_points`, `diagnostic`)  
   * **Required**: Recommended  
   * **Role / visibility**: `diagnostic` – used in Governance/Diagnostic views during workshops.  
   * **Why**: Captures known friction—maybe Marketing calls a new email sign-up a lead, while Sales wants additional qualification.

---

## **10\. Additional Notes or Questions (Optional, context meta)**

10. **Question**: “Any other comments or disclaimers you’d like to share about how you measure or track leads?”  
* **Question Type**: Paragraph (`additional_notes`, `context`)  
* **Required**: Optional  
* **Role / visibility**: `context` – catch-all narrative surfaced in secondary displays.  
* **Why**: A catch-all for unique scenarios or disclaimers.
