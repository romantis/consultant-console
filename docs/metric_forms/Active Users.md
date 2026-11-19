# Active Users

## Overall Form Setup

* **Form Title**: “Active Users Alignment Survey”  
* **Description**:  
  *“Help us capture how your team defines and reports Active Users. We use these answers to compare DAU/WAU/MAU numbers across departments and to align on a single approved definition.”*

The form takes ~5 minutes. Classification roles stay nominal; consensus visibility is include-by-default unless a field sets `exclude_from_consensus=true`.

---

## 1. Team / Department *(context meta)*

* **Question**: “Which team or department do you represent?”
* **Type**: Short answer (required, meta).
* **Reason**: Attribution for answers without impacting discrepancy scoring.
* **Role / visibility**: `context`, excluded from consensus (`exclude_from_consensus=true`) so it remains informational only.

---

## 2. Active User Windows *(definition core + context meta)*

### Checkbox core field

* **Question**: “Which active user windows do you track?”
* **Type**: Checkbox group, required.
* **Canonical options**: `dau`, `wau`, `mau`, `rolling_window`, plus “Other”.
* **Why**: Comparison hinges on whether teams look at daily, weekly, monthly, or rolling counts.
* **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – appears in the Definition block, discrepancy matrix, and Draft Final Definition editor.

### Follow-up meta field

* **Question**: “Add context for the windows you selected (segments, carve-outs).”
* **Type**: Textarea, optional, meta.
* **Why**: Narrative explanations (e.g., “Marketing MAU excludes trials”) stay out of automated comparisons.
* **Role / visibility**: `context` companion (`companion_of=active_metrics_tracked`), included in consensus (`exclude_from_consensus=false`) and rendered under the parent field.

---

## 3. Active Trigger *(definition core + diagnostic meta)*

### Select core field

* **Question**: “What action or threshold marks someone as active?”
* **Type**: Select, required.
* **Canonical options**:
  * `login_event` – at least one login.
  * `key_action` – completes a core workflow.
  * `usage_threshold` – meets a time/volume bar.
  * `multi_condition` – must satisfy multiple signals.
  * `seat_utilization` – billed/assigned seat counts.
  * “Other” option to capture edge cases.
* **Why**: Drives the discrepancy logic about substantive vs. vanity activity.
* **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`) – part of the core Definition and reconciliation payload.

### Follow-up meta field

* **Question**: “Describe the precise rules or thresholds for the trigger you chose.”
* **Type**: Textarea, optional, meta.
* **Why**: Stores nuance (minutes watched, feature names) without diluting canonical comparisons.
* **Role / visibility**: `diagnostic` companion (`companion_of=active_trigger_signal`), included in consensus (`exclude_from_consensus=false`).

---

## 4. Deduplication *(governance core + diagnostic meta)*

* **Select core field**: “How do you deduplicate multi-account users?”
  * Options: `merge_accounts`, `count_accounts`, `hybrid_rules`, plus “Other”.
  * Required, because duplicate handling materially changes active counts.
  * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – appears in Governance/Diagnostic and the resolution payload, not in the short Definition.
* **Meta follow-up**: “Add nuance or known exceptions for deduplication.”
  * Textarea, optional.
  * **Role / visibility**: `diagnostic` companion (`companion_of=deduplication_policy`), included in consensus (`exclude_from_consensus=false`).

---

## 5. Bot Filtering *(governance core + diagnostic meta)*

* **Select core field**: “How do you handle bot or automated traffic?”
  * Options: `exclude_known_bots`, `flag_for_review`, `no_filter`, plus “Other”.
  * Required—bot policies drive whether DAU/WAU comparisons are apples-to-apples.
  * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – reconciled explicitly but not folded into the one-line Definition.
* **Meta follow-up**: Textarea describing detection sources, review cadences, or exceptions.
  * **Role / visibility**: `diagnostic` companion (`companion_of=bot_filtering_method`), included in consensus (`exclude_from_consensus=false`).

---

## 6. Special Segments *(context meta)*

* **Question**: “Special segments (read-only, API-only, free tiers) that change the count.”
* **Type**: Textarea, optional, meta.
* **Why**: Captures carve-outs such as employees or sandbox tenants with direct handbook value.
* **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).

---

## 7. Reporting Cadence *(governance core + context meta)*

* **Select core field**: “How often do you refresh active user metrics?”
  * Options: `daily_snapshot`, `weekly_snapshot`, `monthly_snapshot`, `rolling_window`, plus “Other”.
  * Required—window alignment (calendar vs. rolling) is critical for discrepancy detection.
  * **Role / visibility**: `governance`, included in consensus (`exclude_from_consensus=false`) – reconciled alongside other governance choices.
* **Meta follow-up**: Textarea for timezone, cutoff, or lock-date notes.
  * **Role / visibility**: `context` companion (`companion_of=reporting_frequency`), included in consensus (`exclude_from_consensus=false`).

---

## 8. Example & Divergences *(definition meta + diagnostic meta)*

* **Active example**: “Provide a short example calculation.” (Textarea, optional.)
  * **Role / visibility**: `definition`, included in consensus (`exclude_from_consensus=false`).
* **Pain points**: “Where do definitions of active users diverge today?” (Textarea, required meta so reviewers know what to focus on.)
  * **Role / visibility**: `diagnostic`, included in consensus (`exclude_from_consensus=false`).
* **Additional notes**: Catch-all textarea for disclaimers.
  * **Role / visibility**: `context`, included in consensus (`exclude_from_consensus=false`).

These final meta prompts round out the narrative and governance view, matching the `role + exclude_from_consensus + companion_of` model in `specs/001-metric-meta-visibility/data-model.md`.
