# MRR

## **Overall Form Setup**

* **Form Title**: “MRR Measurement Form”  
* **Form Description**:  
  *“Thank you for taking the time to describe how your team currently measures Monthly Recurring Revenue (MRR). Your responses will help us compare definitions across departments and resolve any discrepancies. Please answer as accurately as possible.”*

You can also include a short note about how long it should take (“\~5–10 minutes”), and that only a few questions are mandatory.

MRR options distinguish three kinds of answer:

- **MRR policy**: can form part of the official recurring run-rate definition.
- **Separate view**: useful for operating analysis, but must remain labeled separately from official MRR.
- **Not MRR**: captured because teams may use the practice today; it is conflict evidence rather than an acceptable official MRR rule.

## **1\. Team / Department *(context meta)***

1. **Question**: “Which team or department do you represent?”  
   * **Question Type**: Short answer  
   * **Required**: Yes  
   * **Meta**: ✅ (context only)  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – used for labeling rows, not reconciliation or the Definition block.  
   * **Why**: Lets consultants spot whether discrepancies are cross-team (Finance vs RevOps vs Marketing) without impacting discrepancy scoring.

---

## **2\. Core Definition Narrative *(definition)***

2. **Question**: “In a few sentences, please describe how you currently measure MRR each month.”  
   * **Question Type**: Paragraph  
   * **Required**: Yes  
   * **Meta**: ❌ (drives text similarity scoring)  
   * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block, Draft Final Definition, and resolution payload.  
   * **Why**: Captures the prose definition or workflow (data source, timing, manual steps). The discrepancy engine can compare narratives for drift.

---

## **3\. Snapshot Timing + Notes *(governance + context)***

3. **Question**: “When do you capture the official MRR snapshot?”  
   * **Question Type**: Multiple choice  
   * **Options**: `Last calendar day of reporting month` (MRR policy), `First business day of following month` (MRR policy), `Rolling daily active-MRR view` (MRR policy or separate operating view), `Contract-close bookings view` (not MRR), `Other`
   * **Required**: Yes  
   * **Meta**: ❌  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – appears in Governance/Diagnostic and discrepancy support, but not as a separate handbook definition line.  
   * **Why**: Different timing cadences are a major discrepancy source. Short enum values let the engine see disagreements immediately.
4. **Follow-up**: “Explain why you use that snapshot timing (optional).”  
   * **Question Type**: Paragraph  
   * **Required**: No  
   * **Meta**: ✅  
   * **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) – gives context (close calendar, pipeline view) without polluting the core matrix.

---

## **4\. Annual or Multi-Year Deals + Notes *(governance + context)***

5. **Question**: “How do you treat annual or multi-year contracts?”  
   * **Question Type**: Multiple choice  
   * **Options**: `Full invoice amount in invoice month` (billing view, not MRR), `Recurring contract value divided across contracted months` (MRR policy), `Recurring monthly schedule from service start` (MRR policy), `Other`
   * **Required**: Yes  
   * **Meta**: ❌  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – reconciled explicitly, but rolled up into narrative in the Handbook.
6. **Follow-up**: “Add context or exceptions for annual deals.”  
   * **Question Type**: Paragraph  
   * **Meta**: ✅ (use for carve-outs, GAAP requirements, etc.)  
   * **Role / visibility**: `context` – supporting notes only.

---

## **5\. Discounts / Credits + Notes *(governance + diagnostic)***

7. **Question**: “How do discounts, promotions, or credits impact MRR?”  
   * **Question Type**: Multiple choice  
   * **Options**: `Current recurring price net of active discounts and credits` (MRR policy), `Undiscounted list-price view` (separate pricing view), `Scheduled post-promotion price` (separate forward view), `Other`
   * **Required**: Yes  
   * **Meta**: ❌  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – key governance lever in workshop reconciliation.
8. **Follow-up**: “Add context or edge cases for discounts/credits.”  
   * **Question Type**: Paragraph  
   * **Meta**: ✅  
   * **Role / visibility**: `diagnostic` – highlights edge cases and failure paths for facilitators.

---

## **6\. Mid-Month Upgrades/Downgrades + Notes *(governance + context)***

9. **Question**: “How do you reflect mid-month upgrades or downgrades?”  
   * **Question Type**: Multiple choice (`Update immediately`, `Wait for next billing cycle`, `Prorate within month`, `Other`)  
   * **Required**: Yes  
   * **Meta**: ❌  
   * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – shows how quickly changes flow into reported MRR.
10. **Follow-up**: “Explain any mid-month change nuances.”  
    * **Question Type**: Paragraph  
    * **Meta**: ✅ (captures tooling limitations, manual steps)  
    * **Role / visibility**: `context` – recorded as narrative only.

---

## **7\. Churn Timing + Notes *(governance + context)***

11. **Question**: “When do you remove churned customers from MRR?”  
    * **Question Type**: Multiple choice (`Cancellation effective date`, `Paid-through or service-end date`, `Documented billing and service-status rule`, `Retain in pipeline history` (not active MRR), `Other`)
    * **Required**: Yes  
    * **Meta**: ❌  
    * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – critical for understanding when churn hits MRR.
12. **Follow-up**: “Explain churn timing exceptions.”  
    * **Question Type**: Paragraph  
    * **Meta**: ✅ (grace periods, approval flows, etc.)  
    * **Role / visibility**: `context` – captured as additional info.

---

## **8\. Trials / Grace Periods + Notes *(governance + context)***

13. **Question**: “How do you treat trials or grace periods in MRR?”  
    * **Question Type**: Multiple choice (`Exclude until paid recurring service begins` (MRR policy), `Include with payment method on file` (forecast view), `Include trial at list price` (forecast view), `Other`)
    * **Required**: Yes  
    * **Meta**: ❌  
    * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – directly impacts when trial users count toward MRR.
14. **Follow-up**: “Add trial/grace period context.”  
    * **Question Type**: Paragraph  
    * **Meta**: ✅  
    * **Role / visibility**: `context` – used for nuance, not reconciliation.

---

## **9\. Included Charge Types + Notes *(definition + context)***

15. **Question**: “Which charge types roll into MRR?”
    * **Question Type**: Checkbox group (`Recurring subscription charges` and `Contracted recurring usage commitments` as MRR components; `Variable usage charges` as a separate usage view; `One-time setup fees`, `Professional services fees`, and `Internal/comp accounts` as not MRR; `Other`)
    * **Required**: Yes  
    * **Meta**: ❌ (the checkbox set is core data for discrepancy detection)  
    * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – part of the core Definition and the resolution payload.
16. **Follow-up**: “Special cases that impact MRR (optional).”  
    * **Question Type**: Paragraph  
    * **Meta**: ✅ (captures qualifiers like “usage excluded unless contracted”)  
    * **Role / visibility**: `context` – explanatory only.

---

## **10\. Example Calculation *(definition meta)* **

17. **Question**: “Provide an example MRR calculation.”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅ (narrative example)  
    * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block and Draft Final Definition, but is excluded from discrepancy severity.

---

## **11\. Pain Points & Additional Notes *(diagnostic + context meta)***

18. **Question**: “Where do MRR numbers or rules conflict across teams?”  
    * **Question Type**: Paragraph  
    * **Required**: Yes (strongly recommended)  
    * **Meta**: ✅  
    * **Role / visibility**: `diagnostic` – used to spotlight hot spots and failure paths.
19. **Question**: “Additional comments or disclaimers?”  
    * **Question Type**: Paragraph  
    * **Required**: Optional  
    * **Meta**: ✅  
    * **Role / visibility**: `context` – sits in secondary displays only.

These context, governance, and diagnostic fields appear in the Context and Governance/Diagnostic sections on the metric show page so consultants can read nuance without skewing the core Definition or discrepancy counts.
