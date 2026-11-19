# NPS (Net Promoter Score)

## **Overall Form Setup**

* **Form Title**: “NPS (Net Promoter Score) Measurement Form”  
* **Form Description**:  
  *“We appreciate your feedback on how you measure Net Promoter Score (NPS). Your responses will help us compare definitions and survey methods across teams to reconcile any differences.”*

Plan for a 5–10 minute completion time. In the current template model, fields are consensus-included by default (`exclude_from_consensus=false`); only `team_department` is excluded as informational context.

---

## **1\. Team / Department *(context meta)***

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short answer  
   * **Required**: Yes  
   * **Meta**: ✅ (context only)  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`).  
   * **Why**: Attribution for answers without impacting discrepancy scoring (Customer Success vs Marketing vs Product).

**Prompt**:

* *“e.g., Customer Success, Marketing, Product, Finance, etc.”*

---

## **2\. Survey Cadence & Timing *(governance + context)***

### Select core field

2. **Question**: “How often do you send the NPS survey?”  
   * **Question Type**: Single select  
   * **Required**: Yes  
   * **Canonical options** (field: `survey_cadence`):  
     * `quarterly_all_customers` – “Quarterly to all paying customers.”  
     * `biannual_all_customers` – “Biannually (twice a year) to all paying customers.”  
     * `ongoing_triggered` – “Ongoing / in-app after usage milestones.”  
     * “Other” (free-text follow-up).
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`).
   * **Why (core)**: Cadence is a primary driver of NPS differences—quarterly blasts vs always-on in-app prompts often produce different scores.

### Follow-up meta field

3. **Question**: “When in the lifecycle do you send NPS (e.g., after onboarding, before renewal)?”  
   * **Question Type**: Paragraph (long answer)  
   * **Required**: Optional  
   * **Meta**: ✅ (field: `survey_timing_notes`)  
   * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
   * **Why**: Lifecycle timing (e.g., 30 days after go-live, pre-renewal) adds nuance but should not fragment the core cadence enum.

---

## **3\. Survey Audience & Method *(governance + context)***

### Select core field

4. **Question**: “Which customers or users do you primarily survey?”  
   * **Question Type**: Single select  
   * **Required**: Yes  
   * **Canonical options** (field: `survey_audience_segment`):  
     * `all_customers` – All customers/accounts.  
     * `paying_customers_only` – Paying or contracted customers only.  
     * `active_product_users` – All active product users (regardless of buyer role).  
     * `decision_makers_only` – Decision-makers or admins only.  
     * `free_trial_included` – Includes free or trial users.  
     * “Other” (free-text follow-up).
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`).
   * **Why (core)**: Whether NPS surveys target all users, only buyers, or just admins is a major discrepancy source across teams.

### Follow-up meta field

5. **Question**: “Describe how you run the survey (channels, segments, exclusions).”  
   * **Question Type**: Paragraph  
   * **Required**: Optional  
   * **Meta**: ✅ (field: `survey_audience_notes`)  
   * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
   * **Why**: Channel choices (email vs in-app vs phone) and segment carve-outs (e.g., only large accounts) are important context but better kept as narrative.

**Prompt**:

* *“e.g., paying accounts via email, all product users via in-app pop-up, phone calls to top-tier accounts, etc.”*

---

## **4\. Handling of Passives (Scores 7–8) *(governance + diagnostic)***

### Select core field

6. **Question**: “How do you handle passives (scores 7–8)?”  
   * **Question Type**: Single select  
   * **Required**: Yes  
   * **Canonical options** (field: `passive_handling`):  
     * `ignore_passives` – “We primarily track promoters vs detractors; passives get no special treatment.”  
     * `follow_up_passives` – “We follow up or target outreach for passives.”  
     * `label_passives_only` – “We label passives but do not act differently.”  
     * “Other” (free-text follow-up).
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`).
   * **Why (core)**: Passives are excluded from the NPS formula but treated differently in workflows; consistent enums help spot gaps in follow-up expectations.

### Follow-up meta field

7. **Question**: “Add any nuance about how you treat passives.”  
   * **Question Type**: Paragraph  
   * **Required**: Optional  
   * **Meta**: ✅ (field: `passive_handling_notes`)  
   * **Role / visibility**: `diagnostic`, included in consensus (`exclude_from_consensus=false`).
   * **Why**: Captures details like routing to specific playbooks or thresholds for outreach without fragmenting the main enum.

---

## **5\. Sample Size & Representativeness *(governance + context)***

### Select core fields

8. **Question**: “In a typical period, how many NPS responses do you collect?”  
   * **Question Type**: Single select  
   * **Required**: Yes  
   * **Canonical options** (field: `response_volume_band`):  
     * `under_50` – Fewer than 50 responses.  
     * `50_to_199` – 50–199 responses.  
     * `200_plus` – 200+ responses.  
     * `varies_widely` – Varies widely by period.  
     * `unsure` – Not sure.  
   * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
   * **Why**: The discrepancy engine can down-weight or flag NPS comparisons that rely on very small or volatile samples.

9. **Question**: “Is this sample representative of your overall user base?”  
   * **Question Type**: Single select  
   * **Required**: Yes  
   * **Canonical options** (field: `sample_representativeness`):  
     * `very_representative` – Broadly representative.  
     * `somewhat_representative` – Skews to certain segments.  
     * `not_representative` – Mostly a narrow or biased slice.  
     * `unsure` – Not sure / haven’t checked.  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`).
   * **Why (core)**: Representation affects how seriously to take NPS discrepancies; short enums allow clear scoring rules.

### Follow-up meta field

10. **Question**: “Add any notes about sample representativeness or skew.”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅ (field: `sample_notes`)  
    * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
    * **Why**: Narrative details (e.g., “only adopters in EMEA respond”) belong in context narrative rather than canonical enums.

---

## **6\. Response Bias Mitigation (Meta)**

11. **Question**: “Do you take any steps to reduce response bias? (e.g., randomizing who sees the survey, not sending only after support issues, etc.)”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅ (field: `bias_mitigation`)  
    * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
    * **Why**: Important for interpreting NPS quality, but too narrative and varied to serve as a clean enum.

---

## **7\. Benchmarking & Trend Tracking (Meta)**

12. **Question**: “Do you compare your NPS to any external benchmark or track it monthly/quarterly for trends?”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅ (field: `benchmarking`)  
    * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
    * **Why**: Benchmarking strategy guides how stakeholders react to NPS, but differences here don’t change the base metric definition.

---

## **8\. Example NPS Calculation or Survey Results *(definition meta)***

13. **Question**: “Could you provide a short example (e.g., from your latest survey) showing how many promoters, passives, and detractors you had, and your final NPS?”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅ (field: `nps_example`)  
    * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`).
    * **Why**: A concrete example helps consultants visualize distribution and can be used as handbook/final-definition context.

---

## **9\. Known Pain Points or Observed Discrepancies (diagnostic meta)**

14. **Question**: “Where do you see the biggest confusion or conflict about NPS definitions or methods across teams?”  
    * **Question Type**: Paragraph  
    * **Required**: Recommended  
    * **Meta**: ✅ (field: `nps_pain_points`)  
    * **Role / visibility**: `diagnostic`, included in consensus (`exclude_from_consensus=false`).
    * **Why**: Surfaces where definitions or survey timing diverge; this guides human review even though it’s not a canonical enum.

---

## **10\. Additional Notes or Questions (context meta)**

15. **Question**: “Any final comments, disclaimers, or unique scenarios about how you measure or report NPS?”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅ (field: `additional_notes`)  
    * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).
    * **Why**: Catch‑all for disclaimers, such as only surveying large accounts or having a specialized approach to passives/detractors.
