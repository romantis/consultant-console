# Audience: Agent

# **Domain Modeling — Metric Harmony Console**

**Edition:** Laravel Cloud \+ Serverless Postgres  
 **Version:** v1.7 • 2026-05-07
 **Aligned Specs:**

* 100%: *Spec — Metric Harmony Console (Laravel Cloud Serverless Postgres Edition)*

* 80/20: *80/20 Spec — Metric Harmony Console (Laravel Cloud Serverless Postgres Edition)*

* Staff scope: `specs/023-staff-scope/spec.md`

This document is the upstream **conceptual source of truth**. Specs must conform to it. Migrations/tests should fail if drift is detected.

## Current Stage Posture

Metric Harmony Console is currently an internal-first consultant delivery engine.

- `routes/web.php` is the consultant/control plane by default.
- `routes/client.php` is an optional client read surface and future self-service base.
- Staff authority resolves through one canonical internal organization, not arbitrary client organization membership.
- Staff owners and staff admins run most consultant delivery operations directly across non-internal organizations.
- Internal workspace governance remains a narrow owner-only surface.
- Staff operators receive explicit assignment-backed access to non-internal organizations.
- Non-staff users are deny-by-default on the consultant plane, except for retained narrow submission/self-service paths documented by route family.
- Submission, approval, sign-off, and change-governance concepts remain domain events, but the current product posture treats most of them as consultant-captured events rather than direct client-operated workflow gates.

---

## **1\) Bounded Contexts & Aggregates**

**Contexts**

1. **Console Core** — Organizations, Users, Memberships, Engagements, Metrics, FinalDefinition, AuditLog.

2. **Forms** — Form, FormSubmission, template library.

3. **Discrepancy** — MetricDefinition, Discrepancy, (optional) Embeddings for similarity.

4. **Workshop** — ReconciliationWorkshop (transcripts & notes).

5. **Governance** — ChangeProposal (post‑sign‑off edits, approvals).

6. **Export** — Org-level handbook generation & repository adapters (Notion/Confluence/PDF).

7. **Notification** — Contributor reminder emails + governance/ops notifications.

**Aggregates (Roots)**

* **Organization** (root): owns Memberships, Engagements.

* **Engagement** (root): owns Metrics, Forms, Workshops.

* **Metric** (root): owns MetricDefinitions, Discrepancies, FinalDefinition, ChangeProposals.

---

## **2\) Entities & Relationships (ER quick view)**

erDiagram  
  Organization ||--o{ Membership : has  
  Organization ||--o{ StaffOperatorAssignment : delegates
  Organization ||--o{ Engagement : has  
  User ||--o{ Membership : joins  
  User ||--o{ StaffOperatorAssignment : receives
  Engagement ||--o{ Metric : has  
  Engagement ||--o{ Form : templates
  Metric ||--o{ MetricParticipant : involves
  MetricParticipant ||--o{ Form : configures
  MetricParticipant ||--o{ FormAssignment : routes
  Form ||--o{ FormAssignment : assigns
  Form ||--o{ FormSubmission : collects
  Metric ||--o{ MetricDefinition : collects
  Metric ||--o{ Discrepancy : flags
  Metric ||--|| FinalDefinition : results
  Engagement ||--o{ ReconciliationWorkshop : hosts
  Metric ||--o{ ChangeProposal : governs
  Organization ||--o{ AuditLog : records  
  Metric ||--o{ Embedding : optional

**Canonical Fields (superset; 80/20 may use a subset)**

* **Organization**: `id, name, slug, is_internal, settings_json, created_at`

* **User**: `id, name, email, avatar_url?, created_at`

* **Membership**: `organization_id, user_id, role {owner, admin, member, viewer}, created_at`

* **StaffOperatorAssignment**: `id, user_id, organization_id, granted_by_user_id, revoked_by_user_id?, granted_at, revoked_at?, reason?, notes?, created_at, updated_at` — delivery-staffing delegation for internal `staff operator` users. This is separate from client `Membership`, `PendingMembership`, `EngagementParticipant`, and contributor-facing form assignments.

* **Engagement**: `id, organization_id, title, status {Draft, AwaitingForms, InReview, InWorkshop, Finalized, Archived}, start_date?, end_date?, notes_md?`

* **EngagementParticipant**: `id, engagement_id, participant_type {membership, pending_membership}, membership_id?, pending_membership_id?, membership_role, stakeholder_role, active` — canonical placeholder for stakeholders so `MetricParticipant` and form flows work even before an invite is accepted.

* **Metric**: `id, engagement_id, name, description, status {AwaitingForms, InReview, InWorkshop, Finalized, Deprecated}, required_teams_json ["Finance","Product","Marketing"], due_date?`

* **MetricParticipant**: `id, metric_id, user_id, responsibility {Contributor, Approver, Observer}, team_tag?`

* **Form**: `id, engagement_id, metric_id?, key, schema_json, help_md?, is_active, primary_participant_id?`

* **FormAssignment**: `id, form_id, metric_id, participant_id, responsibility {Contributor, Approver, Observer}, status {Open, InProgress, Reopened, Completed}, due_at?`

* **FormSubmission**: `id, metric_id, team, submitter_id, participant_id?, responses_json, submitted_at`

* **MetricDefinition**: `id, metric_id, team, responses_json, submitter_id, submitted_at`

* **Discrepancy**: `id, metric_id, field, values_by_team_json, severity {low,med,high}, confidence_score?, suggested_resolution?, agreed_resolution?`

* **FinalDefinition**: `id, metric_id, version (unique per metric), definition_json (summary/formula/data sources/owner), notes_md?, owner_user_id, signed_off_at, signed_off_by`

* **ReconciliationWorkshop**: `id, engagement_id, workshop_date, participants_json, transcript_url?, notes_md`

* **ChangeProposal**: `id, organization_id, engagement_id, metric_id, initiated_by_user_id, initiated_by_staff_role?, capture_context?, rationale_md, payload_json (diff vs FinalDefinition + optional notes), approver_ids_json (MetricParticipant ids w/ responsibility=Approver), status {Open, Approved, Rejected, Merged}, approved_at?, merged_at?`

* **AuditLog**: `id, organization_id, actor_id, actor_type {user,ai,system}, entity_type, entity_id, action, before_json?, after_json?, created_at` plus action-specific metadata. Attestation-sensitive consultant-captured actions should include `recorded_by_staff_user_id`, `staff_role`, and `capture_context` where applicable.

* **Embedding (optional)**: `id, organization_id, embeddable_type, embeddable_id, embedding(vector), created_at`

**Scoping Rule:** Every entity is **org‑scoped** directly or via its parent (Organization → Engagement → Metric → …).

---

## **3\) Invariants & Policies**

**Org & Access**

* I-1: A `Membership` is unique per `(organization_id, user_id)`.

* I-2: `Membership.role` determines ordinary organization membership permissions (see §7). It does not determine internal staff authority.

* I-2a: `EngagementParticipant` references exactly one of `membership_id` or `pending_membership_id` based on `participant_type`; invite acceptance rewires the record and downstream `MetricParticipant`/form assignments (see `docs/engineer/memberships-and-invites.md`).

* I-2b: Internal staff authority resolves only through the canonical internal organization identified by `config('console.internal_org_slug')`, with `is_internal=true`. If that organization is missing or ambiguous, staff authorization fails closed.

* I-2c: `StaffOperatorAssignment` grants delivery staffing access only for non-internal organizations. It must target an internal `staff operator`, may have only one active assignment per `(user_id, organization_id)`, and does not imply ordinary client membership. Only `staff owner` and `staff admin` may grant or revoke assignments.

* I-2d: Canonical internal-organization settings and internal staff governance are owner-only. `staff admin` does not manage the internal workspace through `Organizations/Settings`; admin staffing work happens through explicit operator-assignment surfaces against non-internal organizations.

**Engagement & Metric lifecycle**

* I‑3: `Engagement.status` transitions only as defined in §4.

* I‑4: `Metric.status=Finalized` implies `FinalDefinition.sign_off_date IS NOT NULL`.

* I‑5: A team may have **at most one** active `MetricDefinition` per Metric; re‑submission supersedes previous.

* I‑6: A `FinalDefinition` is **immutable** after sign‑off; edits require an **Approved** `ChangeProposal` (which records diff in `AuditLog`).

* I‑6a: Each Metric must maintain **≥1** `MetricParticipant` with `responsibility=Contributor` before it can progress to `status ∈ {InReview, InWorkshop, Finalized}`.

**Discrepancies & Workshops**

* I‑7: A `Discrepancy` must reference a specific `field` within the Metric’s schema.

* I‑8: Staff owners/admins, and assigned staff operators where explicitly allowed, can mark `agreed_resolution` in the consultant plane. AI suggestions are stored as `suggested_resolution` and must be approved by an authorized human actor.

**Export & Sign‑off**

* I‑9: Exported documents (Notion/Confluence/PDF) reference the canonical `FinalDefinition.id` and version timestamp.

* I‑10: An external sign-off webhook, retained client exception, or consultant-captured in-app sign-off must set `sign_off_date` atomically and record the capture context.

* I‑10a: Reminder workflows target only `Contributor` form assignments with `due_at` while the engagement is `AwaitingForms`; schedules run at `T-48h`, `T-24h`, `T-4h`, and `T+24h` and deliver email to the assigned contributor.
* I-10b: Consultant-plane operations (`index`, `show`, `launch`, status updates, reminder passes, workshops, forms review, discrepancies, governance actions, and handbook export operations) are deny-by-default for non-staff. `staff owner` and `staff admin` may operate any non-internal organization directly; `staff operator` may operate only explicitly allowed route families inside assigned non-internal organizations.

**Auditability**

* I‑11: All AI‑authored content changes are captured in `AuditLog` with `actor_type=ai`, original text, new text, and approver.

* I‑12: `ChangeProposal` can only open when its `Metric.status=Finalized` **and** the latest `FinalDefinition` is signed off; at least one approver must be supplied and each id must reference a `MetricParticipant` with `responsibility=Approver`.

* I‑13: Opening a change proposal captures the current `FinalDefinition.version` in the audit log, snapshots the diff payload, and immediately queues approver notifications; missing approvers forces a `notifications.skipped` audit entry.

* I‑14: Only `Approved` (or explicitly overridden `Rejected`) proposals can merge. Merging clones the signed-off definition into `version+1`, preserves `signed_off_by`, updates `signed_off_at`, creates the next signed `OrgFinalDefinition` revision for the same `org_metric_id`, and records `merged_at` plus the new version inside `AuditLog`.

* I‑15: Impersonation is a preview, QA, and future hybrid self-service mechanism, not the primary staff delivery authorization path. Only `staff owner` and `staff admin` may impersonate non-internal organizations, and impersonation start/stop must emit `AuditLog::ACTION_IMPERSONATION_STARTED`/`STOPPED` with justification.
* I-16: Internal staff must not gain delivery access to a client organization by being attached as an ordinary client member from org settings. The preferred staff-delivery access path is direct staff authority (`staff owner` / `staff admin`) or explicit `StaffOperatorAssignment`.

### Governance Flow (post‑sign‑off change management)

1. **Initiation** — Staff owners/admins, or assigned staff operators where the route family explicitly allows it, open a change proposal once a metric is Finalized. Retained client-originated exceptions must be explicitly route-scoped. The actor must supply a rationale, a structured payload describing the diffs against the current `FinalDefinition`, and ≥1 approver drawn from `MetricParticipant` records with `responsibility=Approver`. The system logs `metric.change-proposal.opened` with the captured `FinalDefinition.version` and consultant-capture metadata when staff records the event.
2. **Notification** — `NotifyChangeProposalApprovers` resolves the approver roster into `User` recipients, sends review requests (email and optional Slack for governance), and records either `metric.change-proposal.notified` or `metric.change-proposal.notifications.skipped` (missing roster). Assignment reminders remain email-only per I‑10a.
3. **Decision** — Staff owners/admins, assigned staff operators where explicitly allowed, or retained assigned approver exceptions respond with `approve`/`reject`, optionally adding commentary. Status transitions obey `Open → Approved/Rejected`, stamp `approved_at` for approvals, and log `metric.change-proposal.decision` including actor, staff role when applicable, capture context, and comment metadata.
4. **Merge & Versioning** — Only approved proposals (or deliberate overrides of rejected proposals) may merge. `MergeChangeProposal` clones the last signed-off definition into `version+1`, applies the payload diffs (preserving labels/units), carries forward `signed_off_by`, stamps `signed_off_at`, and creates the next official `OrgFinalDefinition` revision for handbook/client surfaces. On that org-level revision, `alignment_summary` and operational-status signals reset to `null`, while `implementation_gaps` carry forward unless the merge payload explicitly replaces them. The merge logs `metric.change-proposal.merged` with the new `final_definition_id`.
5. **Audit & Access** — Every step stores `organization_id`, `engagement_id`, and `metric_id` inside `AuditLog`. Staff delivery actions should be authorized directly through the consultant plane. If an owner/admin uses impersonation for preview or retained hybrid validation, `AuditLog::ACTION_IMPERSONATION_STARTED/STOPPED` events wrap the session with the justification so downstream reviews tie edits to the acting consultant.

---

## **4\) State Machines**

**Engagement**

Draft → AwaitingForms → InReview → InWorkshop → Finalized → Archived  
                        ↘──────────────↗

* Guard to **AwaitingForms**: at least one Metric exists.

* Guard to **Finalized**: all Metrics `Finalized` **or** admin override with rationale.

* **Archived**: read‑only snapshot.

**Metric**

AwaitingForms → InReview → InWorkshop → Finalized  
         ↘────────────↗

* Guard to **InReview**: all `required_teams` submitted (or deadline override).

* Guard to **InWorkshop**: discrepancies remain `med|high` after review.

* Guard to **Finalized**: `sign_off_date` set.

* Optional **Deprecated**: superseded by a new Metric; cannot accept submissions.

**Change Proposal**

Open → Approved → Merged  
Open → Rejected → Merged (override only)

* Guard to **Open**: metric is Finalized, last `FinalDefinition` has `signed_off_at`, at least one approver is supplied, and payload contains ≥1 field diff.

* Guard to **Approved/Rejected**: staff owners/admins, assigned staff operators where explicitly allowed, or retained assigned approver exceptions may decide; approvals stamp `approved_at`.

* Guard to **Merged**: status must be `Approved` (default) or `Rejected` with an explicit override, and merge actions must clone the prior definition into `version+1`.

---

## **5\) Domain Events (canonical names)**

* `OrganizationCreated(organization_id)`

* `EngagementCreated(engagement_id)` / `EngagementStatusChanged(engagement_id, from, to)`

* `MetricAdded(metric_id)` / `MetricFinalized(metric_id)`

* `FormSubmitted(metric_id, team, submission_id)`

* `AllRequiredSubmissionsReceived(metric_id)`

* `DiscrepancyRaised(metric_id, field, severity)` / `DiscrepancyResolved(metric_id, field)`

* `WorkshopNotesUpdated(workshop_id)`

* `FinalDefinitionPublished(metric_id)`

* `SignOffRecorded(metric_id, actor_id)`

* `ChangeProposalOpened(change_proposal_id)` / `ChangeProposalMerged(change_proposal_id)`

* `ChangeProposalNotified(change_proposal_id, approver_user_ids)` / `ChangeProposalDecisioned(change_proposal_id, decision, actor_id)`

* `ImpersonationStarted(organization_id, actor_id)` / `ImpersonationStopped(organization_id, actor_id)`

* `HandbookExported(organization_id, export_id)`

Events are internal messages for jobs/notifications; they are not necessarily public APIs.

---

## **6\) Ubiquitous Language (Glossary — living)**

* **Metric** — A measurable business concept (e.g., **MRR**, **Churn**, **Active Users**) with a **definition**, **formula**, and **data sources**.

* **Team** — Functional group providing a submission (Finance, Product, Marketing; extendable).

* **Discrepancy** — A structured disagreement detected between team submissions for the same `field`.

* **Final Definition** — The single, signed‑off source of truth for how the metric is calculated and governed.

* **Change Proposal** — A controlled request to modify a signed‑off definition.

* **Workshop** — A time‑boxed reconciliation session producing decisions/agreements.

Glossary entries for MRR, Churn, and Active Users live as **templates** with example formulas and source hints. These are referenced from forms and handbooks.

---

## **7\) Permissions Matrix (current baseline)**

### Internal staff authority

| Action | Staff Owner | Staff Admin | Staff Operator | Non-Staff |
| ----- | ----- | ----- | ----- | ----- |
| Internal organization governance | yes | no | no | no |
| Staff role grants/revokes | yes | no | no | no |
| Client organization directory / cross-tenant browsing | yes | yes | no | no |
| Assigned organization overview | yes | yes | assigned only | no |
| Client organization settings / members | yes | yes | assigned where delegated | no |
| Engagement launch/status/reminders | yes | yes | assigned only | no |
| Consultant-side forms and submissions review | yes | yes | assigned only | retained assigned exceptions only |
| Workshop/session operations | yes | yes | assigned only | no |
| Discrepancy review/resolution | yes | yes | assigned only | no |
| Sign-off / approval / attestation capture | yes | yes | assigned only where explicitly allowed | retained assigned exceptions only |
| Handbook export operations | yes | yes | assigned only | read-only client surfaces only |
| Client read surface preview | impersonation-backed | impersonation-backed | no | view-only where enabled |

### Ordinary organization membership

Ordinary organization membership remains important for client read surfaces, retained contributor submission exceptions, and future self-service. It does not grant consultant-plane access by itself.

| Action | Org Owner | Org Admin | Member | Viewer |
| ----- | ----- | ----- | ----- | ----- |
| View enabled client read surfaces | yes | yes | yes | yes |
| Submit retained assigned forms | assigned only | assigned only | assigned only | no |
| Request retained changes / decisions | assigned exception only | assigned exception only | assigned exception only | no |

Fine-grained exceptions may be added per engagement, but they must be route-scoped and audited.

---

## **8\) AI & Vector Usage (optional)**

* When enabled (`FEATURE_VECTOR=true`) and **pgvector** is available in Cloud DB:

  * store embeddings in `Embedding` with `(embeddable_type, embeddable_id)` pointing to `MetricDefinition` or long‑form answers.

  * ivfflat index tuned via `lists` parameter; cosine distance.

  * expose similarity score badge in Discrepancy UI (advisory for 80/20; decisive thresholds in 100%).

* All AI writes require **explicit human approval**; approvals recorded in `AuditLog`.

---

## **9\) Consistency Contract with Specs**

* **Models & fields** used by specs must exist here **verbatim**.

* The 80/20 spec uses a **subset** of states and features; removing a field/state in the spec requires updating this doc first.

* CI **drift check** suggestion: generate a JSON schema from this doc and test migrations against it.

---

## **10\) Seeds & Factories (reference)**

* Seed 3 templates (MRR, Churn, Active Users) as `Form.schema_json + help_md`.

* Factories for Organization, User, Membership, Engagement, Metric, MetricDefinition, Discrepancy, FinalDefinition, ChangeProposal.

* Demo dataset: 1 org, 1 engagement, 3 metrics, 3 users (Finance/Product/Marketing), with conflicting submissions to exercise the matrix.

---

## **11\) Data Retention & Privacy (baseline)**

* PII limited to `User` profile and `AuditLog.actor_id`.

* Delete/Export on request at **Organization** scope.

* Transcripts/media (Workshop) stored in S3 with pre‑signed, time‑boxed access.

---

## **12\) Versioning & Change Log**

* **v1.7 (2026-05-07)** — Reconciled the domain model with `specs/023-staff-scope/spec.md`: canonical internal staff authority, owner/admin/operator distinctions, assignment-backed staff-operator access, consultant-captured attestation semantics, and impersonation as preview/QA rather than primary staff delivery authorization.

* **v1.6 (2025‑11‑11)** — Captured the end-to-end change governance flow: expanded FinalDefinition/ChangeProposal fields, new invariants (I‑12…I‑15), dedicated state machine, domain events, and impersonation audit requirements.

* **v1.5 (2025‑09‑27)** — Align with Laravel Cloud Serverless Postgres; optional `Embedding` entity; clarified invariants I‑3… I‑11; state machines updated; permissions table added.

* v1.4 — Added Governance context and ChangeProposal entity.

* v1.3 — Introduced Discrepancy context and advisory similarity.

* v1.2 — Initial split of Forms vs Console Core.

* v1.1 — Org scoping rules.

* v1.0 — First consolidated domain model.
