# Audience: Agent

# Additional Spec - RBAC & Workflow Overlay for Metric Harmony Console

**Version:** v2.0 - 2026-05-07
**Aligned Specs:** `docs/specs/domain-model.md`, `specs/023-staff-scope/spec.md`

**Purpose**: Provide Codex with the current authorization and workflow overlay for assessing implementation drift and proposing reviewable increments. This document supersedes the earlier client-first RBAC overlay. Self-service client approval/sign-off language is now future/deferred unless a route family explicitly retains it.

---

## 1) Core Premise

Metric Harmony Console is currently an internal-first consultant delivery engine.

- `routes/web.php` is the consultant/control plane by default.
- `routes/client.php` is an optional read surface and future self-service plane.
- Staff delivery access should be direct, not primarily impersonation-backed.
- Non-staff access to consultant-plane routes is deny-by-default.
- Submission, approval, sign-off, and change governance remain domain concepts, but the current app usually records them as consultant-captured business events.

### Planes

- **Consultant plane**: staff owner/admin, plus assigned staff operators where explicitly allowed, run delivery across non-internal organizations.
- **Client plane**: non-staff users see narrow read surfaces and retained assignment-scoped exceptions.
- **Support routes**: account settings, auth lifecycle, and active-organization shell state are audited separately and should not be forced into a business capability tier.

### Engagement rails

`Setup -> Forms -> Discrepancy Review -> Workshop -> Finalization/Sign-off -> Governance -> Handbook Export`

---

## 2) Staff Authority Model

Staff authority resolves through exactly one canonical internal organization:

- source: `config('console.internal_org_slug')`
- invariant: exactly one organization exists with that slug and `is_internal=true`
- fail mode: staff authorization fails closed when the internal organization is missing or ambiguous

Internal organization membership maps to staff roles:

| Internal membership role | Staff role | Meaning |
| --- | --- | --- |
| `OWNER` | `staff owner` | internal governance plus broad consultant delivery |
| `ADMIN` | `staff admin` | broad consultant delivery |
| `MEMBER` | `staff operator` | assignment-backed consultant delivery only |
| no internal membership | non-staff | no consultant-plane access by default |

`StaffOperatorAssignment` is the first implementation's delegation model:

- org-level only
- targets a non-internal organization
- targets an internal `staff operator`
- separate from client `Membership`, `PendingMembership`, `EngagementParticipant`, and `FormAssignment`
- one active assignment per `(user_id, organization_id)`
- granted/revoked only by `staff owner` or `staff admin`

Impersonation is reserved for owner/admin preview, QA, and future hybrid validation. It is not the normal authorization path for staff delivery work.

---

## 3) Capability Matrix

| Capability | Staff Owner | Staff Admin | Staff Operator | Non-Staff |
| --- | --- | --- | --- | --- |
| Internal org governance | full | no | no | no |
| Staff role grants/revokes | full | no | no | no |
| Operator assignment grants/revokes | yes | yes | no | no |
| Client org directory / cross-tenant browsing | yes | yes | no | no |
| Assigned org overview | yes | yes | assigned only | no |
| Canonical internal org settings | yes | no | no | no |
| Client org settings and members | yes | yes | assigned only where a route family explicitly allows it | no |
| Engagement launch/status/reminders | yes | yes | assigned only | no |
| Consultant forms and submission review | yes | yes | assigned only | retained assigned exceptions only |
| Workshop/session operations | yes | yes | assigned only | no |
| Discrepancy review/resolution | yes | yes | assigned only | no |
| Metric governance and sign-off capture | yes | yes | assigned only where explicitly allowed | retained assigned exceptions only |
| Handbook export operations | yes | yes | assigned only | client read only where enabled |
| Client preview | impersonation-backed | impersonation-backed | no | own client surface only |

Owner-only power should stay narrow: internal organization governance, staff role governance, ownership transfer, and explicitly marked emergency/destructive cross-tenant operations.

The internal workspace and client-organization staffing must stay separate:

- `staff admin` may manage operator staffing through explicit `StaffOperatorAssignment` surfaces on non-internal organizations.
- `staff admin` does not use `Organizations/Settings` for canonical internal-org governance.
- Internal staff should not be attached to client orgs as ordinary members merely to obtain staff delivery access; the legacy `Add Consultant` shortcut is removed.

---

## 4) Permission Contract

Shared auth payload should expose staff authority explicitly:

```ts
auth: {
  user: {
    id: string
    is_internal_staff: boolean
    staff_role: 'owner' | 'admin' | 'operator' | null
    can_operate_delivery: boolean
    has_operator_assignments: boolean
    can_govern_internal: boolean
    can_impersonate: boolean
  }
}
```

Route-family pages should receive server-computed page/action abilities such as:

- `can_view`
- `can_update`
- `can_manage_members`
- `can_launch`
- `can_send_reminders`
- `can_submit_retained_assignment`
- `can_capture_sign_off`
- `can_publish`

`is_staff` is not a sufficient permission model because operators are internal staff without global consultant-plane authority. UI navigation and controls should branch on `staff_role`, shared capability flags, and page-level abilities.

---

## 5) Workflow Guards

### Forms

Consultant-side form access is available to staff owners/admins and assigned operators where the route family allows it. Contributor self-service submission remains a retained, assignment-scoped exception and must not become broad client-plane mutation authority.

### Discrepancy and Workshop

Staff owners/admins may review and resolve discrepancies and operate workshops across non-internal organizations. Staff operators may do so only inside assigned organizations and only on explicitly allowed route families.

### Sign-off and Attestation

Sign-off, approval, submission, and review request events are attestation-sensitive. In the current stage they are usually staff-captured consultant actions. The audit shape should record:

- acting user
- staff role when applicable
- `recorded_by_staff_user_id` when staff records a business event
- `capture_context`
- organization, engagement, metric, and related entity ids
- optional client originator, reason, and notes

Retained client-originated exceptions must be route-scoped and tested.

### Change Governance

Post-final edits open a `ChangeProposal`, route approver notifications where applicable, and merge only through audited workflow actions. Client approver decision paths are future/deferred unless the specific retained route exception is documented and tested.

---

## 6) Route Families

Consultant-plane route families live primarily in `routes/web.php`:

- organization directory, overview, settings, and members
- staff operator assignment workflow
- engagement shell, launch, status, reminder, and lifecycle operations
- forms, form submissions, and retained consultant-side form work
- workshops and sessions
- discrepancies and metric governance
- sign-off and change proposals
- handbook exports and publish controls
- metric library and templates
- owner-only internal governance

Client-plane route families live primarily in `routes/client.php`:

- read-only handbook and status surfaces
- retained contributor submission paths
- retained client mutation exceptions explicitly documented by route family
- owner/admin impersonation-backed preview

Staff operators do not receive direct client-plane preview access in the current implementation.

---

## 7) Assessment Rubric for Future Work

Score each area from 0-2 when assessing drift:

1. Canonical internal-org authority resolves fail-closed.
2. Owner-only internal governance is narrow and enforced.
3. Staff admin has broad consultant-plane delivery access without impersonation.
4. Staff operator access is assignment-backed and route-family explicit.
5. Client membership does not grant consultant-plane access by itself.
6. Retained client submission/self-service exceptions are narrow and tested.
7. Page/action abilities are server-computed and do not rely on `is_staff`.
8. Attestation-sensitive workflows include consultant-capture audit metadata.
9. Client preview remains owner/admin impersonation-backed.
10. Scope mismatch tests cover org/engagement/metric boundaries.

---

## 8) Test Plan

- **Policy tests**: owner/admin/operator/non-staff matrix per route family.
- **Feature tests**: happy path plus denied-role or denied-scope assertion for every new scoped endpoint.
- **Frontend tests**: navigation and page controls based on `staff_role`, shared flags, and page-level abilities.
- **Audit tests**: operator assignment grant/revoke and attestation-sensitive actions record expected metadata.
- **Scope tests**: organization, engagement, metric, participant, and assignment mismatches return `403` or `404`.

---

## 9) Codex Task Guidance

For future increments:

- Prefer small vertical route-family slices.
- Keep ordinary client membership separate from internal staff authority.
- Use action classes for staffing and attestation workflows.
- Do not add a blanket staff `Gate::before` bypass.
- Update mapped operations/spec docs in the same slice when access behavior changes.
- Treat broad client submit/approve/sign-off authority as future/deferred unless explicitly retained by the current route audit.

---

**End of overlay spec (v2).**
