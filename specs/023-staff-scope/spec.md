# Feature Specification: Staff Scope & Route Access Model

**Feature Branch**: `023-staff-scope`  
**Created**: 2026-04-29  
**Status**: Draft  
**Input**: User direction: "This app should behave as an internal-first consultant delivery engine. Almost everything in `routes/web.php` should be available to staff, with a narrow owner-only surface, broad staff-admin operational access, assignment-backed staff-operator access, and very limited non-staff access."

## Summary

This spec defines the target authorization model for the current stage of Metric Harmony Console.

Current-stage product identity:

- the app is an **internal-first consultant delivery engine**
- `routes/web.php` is the **consultant/control plane** by default
- `routes/client.php` is an **optional read surface and future self-service plane**, though it currently contains legacy mutating exceptions that must be classified explicitly
- `staff owner`, `staff admin`, and `staff operator` must become explicit, distinct roles
- `non-staff` access must be deny-by-default on the consultant plane

This spec intentionally moves away from a SaaS-first interpretation of the product.

Concepts such as `submission`, `approval`, `sign-off`, and `owner` may remain in the domain model, but at the current stage they should usually be interpreted as:

- business events the consultant captures,
- not actions that the client must perform directly inside the platform.

## Goals

- Make the access model explicit enough to drive a route-by-route audit and implementation plan.
- Align authorization with the internal-first operating model.
- Split `staff owner`, `staff admin`, and `staff operator` instead of treating all staff identically.
- Keep most delivery operations directly available to staff admins and staff owners.
- Keep staff-operator access explicit and assignment-backed rather than global.
- Keep owner-only powers narrow and explicit.
- Treat non-staff write access as exceptional rather than foundational.
- Preserve scope integrity, auditability, and future SaaS migration options.

## Non-Goals

- This spec does not itself implement the policy refactor.
- This spec does not provide the final task breakdown; that belongs in `plan.md` and `tasks.md`.
- This spec does not redesign authentication or onboarding.
- This spec does not commit the app to a permanent non-SaaS future; it only defines the current internal-first stage.
- This spec does not remove domain concepts that may later support self-service.

## Background

The product started as an internal-first consultant tool, but parts of the codebase drifted toward SaaS-style assumptions:

- client-facing roles became more active in policies and route design
- some workflows began assuming that clients would submit, approve, or sign off directly in the system
- authorization logic became mixed between consultant operations and client-style authority flows

At the same time, the real product usage remains consultant-led:

- consultants, delivery managers, and operators run engagements
- consultants manage organizations, members, workshops, metrics, and exports
- clients mostly consume outputs and provide input through consultant-led delivery, not through broad self-service inside the app

This spec restores that product posture without deleting future SaaS options.

## Access Model

### Primary Roles

- **Staff Owner**: internal organization `OWNER`; governs the internal workspace and holds narrow reserved powers.
- **Staff Admin**: internal organization `ADMIN`; broad cross-tenant delivery manager and senior consultant role.
- **Staff Operator**: internal organization `MEMBER`; assignment-backed delivery operator. No global cross-tenant browsing or internal governance by default.
- **Non-Staff**: any authenticated user without staff authority in the internal organization.
- **Impersonated Staff Actor**: a staff user operating as a selected org-scoped role through impersonation for preview, QA, or future hybrid self-service scenarios.

### Internal Staff Authority Invariant

- Staff authority must resolve through one canonical internal organization.
- The canonical internal organization should be resolved through a configured identifier such as `config('console.internal_org_slug')`.
- The expected invariant is: exactly one organization exists with that configured slug and `is_internal = true`.
- The internal organization is the root of trust for distinguishing `staff owner`, `staff admin`, `staff operator`, and `non-staff`.
- Staff authority must not resolve through an arbitrary `first()` match over `is_internal = true`.
- If the canonical internal organization is missing or ambiguous, authorization should fail closed until the invariant is repaired.

### Core Principle

The system must distinguish between:

1. **Internal governance**
2. **Consultant-controlled delivery operations**
3. **Attestation-sensitive workflows**
4. **Optional client read surfaces / future self-service**

Only the first category should be strongly owner-only.

The second and third categories should usually be directly available to staff admins and staff owners in the current internal-first stage.

Staff operators should receive access only where the route family is explicitly assignment-backed and stays inside the operator's delegated portfolio.

### Route Tiers

| Tier | Intended Actors | Meaning |
| --- | --- | --- |
| A. Internal governance | Staff Owner | Internal org governance, staff roster/role control, reserved emergency powers |
| B. Staff delivery control plane | Staff Owner, Staff Admin, assigned Staff Operator where explicitly granted | Day-to-day consultant operations |
| C. Staff read-only cross-tenant | Staff Owner, Staff Admin, assigned Staff Operator where explicitly granted | Overview and discovery surfaces without mutation |
| D. Attestation-sensitive workflows | Staff Owner, Staff Admin, assigned Staff Operator where explicitly granted; explicit retained hybrid exceptions where documented | Staff-recorded submissions, approvals, sign-off, and similar business-attestation events |
| E. Optional client read / future self-service | Non-Staff, impersonated staff when applicable | Read-only client pages today, possible self-service expansion later |

### Support-Route Exemptions

Some authenticated routes do not represent consultant-plane or client-plane business capabilities and should not be force-fit into Tiers A-E.

These routes may be classified as explicit support-route exemptions when they are:

- account settings routes
- auth lifecycle support routes
- shell-state routes such as active-organization selection

This is not a sixth business tier. It is an explicit audit classification for authenticated support routes that still need to be inventoried and tested appropriately.

### Default Mapping

- `routes/web.php` should mostly contain Tiers A, B, C, and D.
- `routes/client.php` should mostly contain Tier E.
- Non-staff access on `web.php` should be exceptional and explicitly justified.
- Impersonation is not the primary authorization mechanism for current delivery operations; it is mainly a preview, QA, and future hybrid mechanism.
- Staff access to `routes/client.php` should usually remain impersonation-backed preview access; if staff needs direct operational access, the route family should usually live in the consultant plane instead.

## Capability Matrix By Bounded Context

This matrix is the working domain-oriented view of the current target model.

| Bounded Context / Capability | Staff Owner | Staff Admin | Staff Operator | Non-Staff | Notes |
| --- | --- | --- | --- | --- | --- |
| Console Core: internal org governance | Full | No by default | No | No | internal settings, staff workspace governance, staff authority management |
| Console Core: staff role grants / revokes | Full | No | No | No | narrow owner-only capability |
| Console Core: client org directory / cross-tenant browsing | Full | Full | No by default | No | operators do not get global consultant browsing |
| Console Core: assigned org overview | Full | Full | Assigned only | No | operator may open explicitly assigned orgs |
| Console Core: client org settings / memberships | Full | Full | Delegated only | No | operator only where explicit org-level delegation exists |
| Engagement Ops: launch / status / reminders / timeline operations | Full | Full | Assigned only | No | delivery engine core |
| Forms: consultant form access and consultant-captured submission recording | Full | Full | Assigned only | No | consultant plane form work |
| Forms: retained hybrid self-service submission exceptions | Observer / capture when acting as consultant | Observer / capture when acting as consultant | Observer / capture when acting as consultant | Assigned contributor only where explicitly retained | applies to explicit retained paths such as `forms.submit` and `api.form-assignments.submit` |
| Workshop: scheduling / facilitation / transitions | Full | Full | Assigned only | No | consultant-led operations |
| Discrepancy: review / triage / resolution operations | Full | Full | Assigned only | No | consultant plane |
| Governance: sign-off / approval / attestation capture | Full | Full | Assigned only | No | attestation-sensitive, but still staff-operated today |
| Global Metrics: metric library / templates | Full | Full | No by default | No | global consultant knowledge surface |
| Org Metrics: signals / participants / org-scoped metric operations | Full | Full | Assigned only | No | operator stays within delegated org or engagement scope |
| Export: handbook export / publish / delivery ops | Full | Full | Assigned only | No | consultant delivery operation |
| Client Read Surface: handbook / read-only client pages | Impersonation-backed preview by default | Impersonation-backed preview by default | No by default | View-only if enabled | optional today, future self-service base |
| Emergency global override / destructive cross-tenant powers | Full | Limited / No | No | No | explicit owner-only area |

## Owner-Only Capability Baseline

This closes the current ambiguity around owner-only scope.

Current-stage owner-only capabilities are:

- internal organization governance and internal workspace settings
- staff role grants, revokes, promotions, and demotions within the internal organization
- assignment or transfer of internal-organization ownership
- reserved emergency, compliance-sensitive, or destructive cross-tenant operations explicitly marked as owner-only

Everything else should default to staff-owner plus staff-admin access unless a route family is explicitly classified otherwise. Staff-operator access must always be explicit and assignment-backed.

## Consultant Plane vs Attestation-Sensitive Workflows

### Consultant-Controlled Operations

These should usually be directly accessible to both staff owners and staff admins:

- organization directory and cross-tenant browsing
- organization creation
- client organization settings and membership management
- engagement launch and operational management
- workshop planning and execution orchestration
- discrepancy review and consultant diagnostic surfaces
- org metric management and consultant signal maintenance
- metric library and template management
- handbook export operations

Staff operators should only receive the subset of these capabilities that are explicitly delegated within an assigned portfolio.

### Attestation-Sensitive Workflows

These workflows are more sensitive than ordinary consultant operations because they record business decisions or client-originated facts:

- submissions
- approvals
- sign-off
- review requests
- certain ownership or stewardship mutations

However, in the current internal-first stage these should usually still be **direct staff actions**, not client-only actions.

That means:

- staff should usually perform them directly
- non-staff should usually not perform them
- the system should record that the consultant captured or recorded the event

This is not a reason to deny staff access. It is a reason to require stronger audit semantics.

Explicit retained hybrid exceptions may still exist for selected form-submission paths such as `forms.submit` and `api.form-assignments.submit`. Those paths should remain narrow, assignment-scoped exceptions rather than the default authorization posture for the product.

### Optional Client Read Surface

Non-staff users may still have a useful read-only surface:

- handbook views
- progress or status pages
- other narrow informational pages

But those surfaces are not the product center of gravity in the current stage.

## Current Drift To Resolve

- `App\Support\Staff::isStaff()` currently collapses internal `OWNER` and `ADMIN` into one boolean, and does not yet model internal `MEMBER` as an explicit `staff operator` tier.
- Some policies already behave like a consultant plane, while others still encode SaaS-leaning assumptions.
- Some UI surfaces expose actions based on incomplete permission signals.
- `routes/web.php` currently mixes internal governance, delivery operations, and attestation-sensitive workflows without a formal route taxonomy.
- The app still contains route and policy semantics that imply clients are primary actors for operations the consultants actually perform.

### Concrete Current Mismatches

- The current owner middleware admits any staff user, so owner-only semantics are not actually enforced today.
- Staff authority currently resolves from the first internal organization found, instead of from a canonical internal-org root of trust.
- `OrganizationPolicy::update()` and `EngagementPolicy::view()/update()` still depend on org membership or impersonation in places where the target model expects direct cross-tenant staff delivery access.
- `MetricPolicy::signOff()` still models sign-off primarily as participant or approver authority with a feature-flagged override, rather than as a first-class staff-captured attestation workflow.
- `routes/client.php` already contains mutating routes such as handbook export creation and metric-owner reassignment, so route-file placement alone cannot be treated as the access tier.
- The frontend permission contract still centers on `is_staff` and scattered booleans; it does not yet expose `staff_role` plus consistent page and action abilities.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staff Owner governs the internal workspace and retains full delivery visibility (Priority: P1)

As a staff owner, I can manage the internal workspace and also access the same delivery plane as staff admins across client organizations.

**Independent Test**: Authenticate as an internal `OWNER`, confirm internal-governance capabilities succeed, and verify cross-tenant consultant routes remain accessible.

**Acceptance Scenarios**:

1. **Given** a staff owner, **When** they manage internal-org governance capabilities, **Then** those owner-only operations succeed.
2. **Given** the same staff owner, **When** they navigate consultant delivery routes for a client org with no direct membership, **Then** the routes load successfully.
3. **Given** an explicitly owner-only route or capability, **When** a staff admin or staff operator attempts it, **Then** access is denied.

### User Story 2 - Staff Admin runs the delivery engine directly (Priority: P1)

As a staff admin, I can perform almost all consultant delivery work across client organizations without relying on direct client memberships.

**Independent Test**: Authenticate as an internal `ADMIN`, then exercise representative organization, engagement, workshop, metrics, and export routes for a client org with no direct membership.

**Acceptance Scenarios**:

1. **Given** a staff admin, **When** they use consultant-plane routes for organizations, engagements, workshops, metrics, and exports, **Then** those routes succeed unless explicitly owner-only.
2. **Given** the same staff admin, **When** they execute attestation-sensitive workflows such as recording a submission or sign-off, **Then** the action succeeds and emits the stronger audit metadata.
3. **Given** a truly owner-only capability, **When** the staff admin attempts it, **Then** the request is denied.

### User Story 3 - Staff Operator stays inside the assigned portfolio (Priority: P1)

As a staff operator, I can do delivery work inside the organizations and engagements assigned to me, but I cannot browse unrelated client work or internal-governance surfaces.

**Independent Test**: Authenticate as an internal `MEMBER` treated as `staff operator`, then verify allowed access inside an assigned org or engagement and denied access outside that assigned portfolio.

**Acceptance Scenarios**:

1. **Given** a staff operator with an assigned organization or engagement, **When** they open explicitly delegated delivery routes inside that assignment, **Then** those routes succeed.
2. **Given** the same staff operator, **When** they try to browse an unrelated client organization or engagement, **Then** the request is denied.
3. **Given** a staff operator, **When** they attempt owner-only or admin-broad governance capabilities, **Then** the request is denied.

### User Story 4 - Non-staff users do not inherit the delivery plane (Priority: P1)

As a non-staff user, I should not receive consultant-plane operational power just because I belong to an organization.

**Independent Test**: Authenticate as non-staff users with viewer/member/admin roles and verify denial across consultant-plane routes, while allowed read-only client surfaces continue to work when present.

**Acceptance Scenarios**:

1. **Given** a non-staff user, **When** they browse `routes/web.php` consultant-plane routes, **Then** they are denied except for narrow explicit exceptions.
2. **Given** the same user, **When** they open allowed read-only client surfaces, **Then** those pages work.
3. **Given** a non-staff user, **When** they attempt consultant-operated write flows such as engagement operations or sign-off capture outside retained hybrid exceptions, **Then** access is denied.

### User Story 5 - Staff can record client-originated business events without pretending to be the client (Priority: P2)

As a consultant operator, I can record decisions and milestones such as submission, approval, and sign-off directly in the system without being forced into a fake self-service model.

**Independent Test**: Execute representative attestation-sensitive actions as staff, then verify the audit trail records that staff captured the event.

**Acceptance Scenarios**:

1. **Given** a staff admin, staff owner, or explicitly delegated staff operator, **When** they perform a sign-off or similar attestation-sensitive action, **Then** the action succeeds directly.
2. **Given** the same action, **When** the audit log is inspected, **Then** it identifies the acting consultant and the capture context.
3. **Given** retained hybrid paths such as `forms.submit` or `api.form-assignments.submit`, **When** an assigned non-staff contributor uses them, **Then** the action remains explicit, narrow, and assignment-scoped rather than becoming the default product posture.

## Edge Cases

- Staff can read a client overview but must not see write controls they cannot use.
- Internal organizations remain special and should not be treated as ordinary cross-tenant client targets.
- Some domain events may preserve SaaS-like names (`submit`, `approve`, `sign-off`) while still being staff-operated today.
- Staff owner/admin/operator split must not break existing impersonation flows.
- Operator assignment boundaries must not silently collapse into global consultant browsing.
- Scope mismatches between `{organization}`, `{engagement}`, and `{metric}` must still fail with the current guardrails.
- Any access expansion must avoid hidden write paths through background jobs, POST endpoints, or secondary controllers.
- A route may live in `web.php` for historical reasons while semantically belonging to the optional client read surface; the plan must classify it before moving it.
- A route may live in `client.php` for historical reasons while semantically belonging to the consultant plane; the plan must classify it before keeping, mirroring, or moving it.
- If multiple internal organizations exist, or none exists, staff authorization must fail closed rather than silently binding to an arbitrary org.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST explicitly distinguish `staff owner`, `staff admin`, and `staff operator` based on the user’s membership role in the internal organization.
- **FR-002**: A single boolean `isStaff()` MUST NOT be the final access model for consultant-plane authorization decisions.
- **FR-003**: Every authenticated route in `routes/web.php` and `routes/client.php` MUST be classified into one of the route tiers defined in this spec or into an explicit support-route exemption.
- **FR-004**: `routes/web.php` MUST be treated as the consultant/control plane by default.
- **FR-005**: `routes/client.php` MUST be treated as an optional client read surface and future self-service plane by default.
- **FR-006**: Non-staff access to consultant-plane routes MUST be deny-by-default and granted only through narrow, explicit exceptions.
- **FR-007**: Staff admins SHOULD have direct access to almost all consultant-controlled delivery workflows across non-internal organizations.
- **FR-008**: Staff owners SHOULD have the same delivery-plane access as staff admins, plus narrow reserved internal-governance powers.
- **FR-009**: Staff operators SHOULD receive only assignment-backed consultant-plane access and SHOULD NOT gain global cross-tenant browsing or governance by default.
- **FR-010**: Owner-only capabilities MUST remain explicit and narrow.
- **FR-011**: Internal-org governance SHOULD be owner-only unless a later spec explicitly widens a capability.
- **FR-012**: Client organization settings and membership management SHOULD be treated as consultant-controlled in the current internal-first stage.
- **FR-013**: Attestation-sensitive workflows SHOULD usually be directly executable by staff owners and staff admins in the current stage, and by staff operators only where the route family is explicitly delegated within the assigned portfolio.
- **FR-014**: Attestation-sensitive workflows MUST emit stronger audit metadata that makes it clear a consultant recorded or captured the event.
- **FR-015**: Retained hybrid self-service exceptions such as `forms.submit` and `api.form-assignments.submit` MAY remain in the current stage, but they MUST be explicitly classified as narrow assignment-scoped exceptions rather than assumed product defaults.
- **FR-016**: Current-stage non-staff write access for submission, approval, sign-off, or similar flows SHOULD NOT be treated as a required product path outside those explicit retained hybrid exceptions.
- **FR-017**: Future SaaS or self-service evolution MUST be additive and MUST NOT be assumed as the current default access model.
- **FR-018**: The system MUST NOT introduce a blanket global superuser bypass such as an unconditional `Gate::before` that grants staff owners every ability.
- **FR-019**: `owner.console` MUST NOT be treated as a required long-term architectural boundary; owner-only capabilities may move into internal-org and organizations surfaces.
- **FR-020**: Route middleware, policies, controllers, and frontend permission payloads MUST agree on the effective access model for each route family.
- **FR-021**: Frontend screens MUST hide or disable actions the current actor cannot perform.
- **FR-022**: Classification and policy refactor MUST happen before any large route relocation between `web.php` and `client.php`.
- **FR-023**: Each route family changed by this initiative MUST receive happy-path coverage for intended staff access and at least one sad-path assertion for a denied role or scope.
- **FR-024**: Flow-related docs and release notes MUST be updated whenever the access model changes for organizations, engagements, impersonation, or consultant/client boundaries.
- **FR-025**: The implementation plan MUST produce a route audit matrix for all authenticated routes in `routes/web.php` and `routes/client.php`.
- **FR-026**: Staff authority MUST resolve against one canonical internal organization rather than an arbitrary internal-org lookup.
- **FR-027**: If the canonical internal organization is missing or ambiguous, staff authorization MUST fail closed.
- **FR-028**: The frontend permission contract MUST evolve beyond `is_staff` to include `staff_role` and route-family-specific page or action abilities where UI branching depends on access level.
- **FR-029**: Any mutating route that remains in `routes/client.php` during the transition MUST be explicitly classified as a preview exception, a retained hybrid exception, or a route family that should move or be mirrored into the consultant plane.
- **FR-030**: The canonical internal organization MUST be resolved through an explicit configured identifier rather than through an unordered internal-org query.
- **FR-031**: Route audit and implementation slices MUST preserve scope integrity checks such as `{engagement}` belonging to `{organization}` and `{metric}` belonging to the expected organization or engagement scope.
- **FR-032**: Staff-operator access MUST resolve from an explicit operator-assignment or portfolio model and MUST NOT be inferred accidentally from ordinary client-organization membership without a documented model decision.
- **FR-033**: Operator-assignment grant and revoke capabilities MUST be modeled as authorization-relevant actions with explicit audit logging and test coverage.
- **FR-034**: The first implementation of staff-operator access MUST use explicit organization-level assignments for non-internal organizations rather than engagement-level-only assignments.
- **FR-035**: Staff-operator assignments MUST be grantable and revocable by `staff owner` and `staff admin` as a delivery-staffing capability, with explicit audit logging.
- **FR-036**: Client-context impersonation for non-internal organizations MUST be available to `staff owner` and `staff admin`, and MUST NOT be available to `staff operator` in the first implementation.

## Permission Contract Guidance

The target access model needs a stronger UI contract than the current shared `is_staff` boolean.

Minimum direction for planning:

- shared auth state should expose `is_internal_staff`, `staff_role`, `can_operate_delivery`, and `has_operator_assignments` in addition to any transitional `is_staff` boolean
- shared auth state should expose global shell capabilities such as `can_impersonate` and `can_govern_internal` when shell navigation or global tools branch on them
- page props should expose explicit abilities such as `can_view`, `can_update`, `can_manage_members`, `can_publish`, or other route-family-specific permission keys
- frontend components should not infer owner/admin/operator distinctions from route placement or from `is_staff` alone
- transitional `is_staff` should not be treated as sufficient for consultant-plane UI branching once `staff operator` exists
- the route audit matrix should name the UI permission key or page-level ability expected for each route family

## Route Audit Matrix Contract

The route audit matrix in `research.md` should include, at minimum, these columns or equivalent explicit companion fields:

- route name
- HTTP method
- URI pattern
- controller or action
- route tier
- intended actors
- operator access in first implementation
- grant source for operator access, if any
- current middleware or policy source
- target middleware or policy source, if changing
- scope parameters
- UI permission key or page ability
- scope integrity assertion
- happy-path test to add or update
- denied-role or denied-scope test to add or update

For routes in `client.php`, the matrix should also note whether the route is:

- true client read surface
- impersonation-backed staff preview
- legacy exception to be retained temporarily
- candidate to move or be mirrored into the consultant plane

## Route Family Guidance

This section is directional and intended to drive planning, not to lock every individual endpoint before the audit.

### Usually Tier A - Internal governance

- internal-org governance surfaces
- staff roster and staff role management
- reserved emergency or compliance-sensitive powers
- any future owner-only cross-tenant destructive action

### Usually Tier B - Staff delivery control plane

- metric library
- metric templates
- organization directory and organization creation
- client organization settings and membership operations
- engagement launch and lifecycle operations
- workshop planning and facilitation operations
- discrepancy review and related consultant diagnostics
- org metric management
- handbook export operations

### Usually Tier C - Staff read-only cross-tenant

- organization overview
- engagement overview
- metric overview
- other consultant discovery screens that do not mutate data

### Usually Tier D - Attestation-sensitive workflows

- submission recording
- approval recording
- sign-off capture
- review request capture
- similar business-attestation or milestone workflows

### Usually Tier E - Optional client read / future self-service

- handbook consumption
- read-only client progress pages
- future self-service forms, approvals, and task surfaces if the product moves in that direction
- retained hybrid submission exceptions that stay assignment-scoped in the current stage

## Assumptions

- The internal-first operating model remains the primary product direction for the near term.
- Consultant-led delivery remains the core usage model.
- Internal facilitators or assistants may map to `staff admin` or `staff operator` depending on how broad their delivery scope should be.
- Clients mostly observe outputs and provide input through consultant-led delivery rather than broad self-service in the app.
- The domain model may preserve concepts that later support SaaS or hybrid self-service.
- Backward compatibility with today’s mixed authorization behavior is not required if the target model becomes clearer and more consistent.

## Working Decisions

These decisions are treated as current direction and should guide `plan.md`.

- The app is an internal-first consultant delivery engine, not a self-service-first SaaS product.
- `routes/web.php` is the consultant/control plane by default.
- `routes/client.php` is optional read-only/future self-service surface by default.
- Internal `MEMBER` should be treated as `staff operator` in the authorization model.
- Staff access to `routes/client.php` should remain impersonation-backed preview access by default.
- `staff admin` should usually be able to do nearly everything needed to run delivery.
- `staff operator` should usually be limited to explicitly assigned delivery work and should not get global consultant browsing by default.
- staff-operator assignment should not be inferred from ordinary client membership without an explicit documented mapping.
- first-implementation staff-operator access should use explicit org-level assignments for non-internal orgs.
- `staff owner` and `staff admin` should grant and revoke staff-operator assignments as a delivery-staffing capability with audit logging.
- `staff owner` and `staff admin` should be able to impersonate non-internal orgs; `staff operator` should not.
- `staff owner` should usually do the same delivery work as `staff admin`, plus internal governance and narrow reserved powers.
- Client organization settings and membership operations are part of the consultant plane in the current stage.
- Attestation-sensitive workflows should usually be direct staff actions today, not forced impersonation flows.
- `forms.submit` and `api.form-assignments.submit` remain explicit retained hybrid self-service exceptions.
- Classification and policy refactor should happen before route movement between `web.php` and `client.php`.
- The staff-role technical model should begin with `staffRole()` plus small helper methods, not a large abstraction.
- `owner.console` may be retired or reduced; future owner-only powers do not need to depend on a dedicated route namespace.

## Open Decisions

These are the remaining questions that should be resolved before `tasks.md` and implementation slices are finalized.

### OD-002 - Which internal-org capabilities, if any, can be delegated to staff admin?

**Question**: Does `staff admin` get any partial authority in the internal org, or is internal governance entirely owner-only?

**Recommended default**: Keep internal governance owner-only by default and allow only future explicit delegated capabilities.

### OD-004 - How much non-staff read surface do we want right now?

**Question**: Should non-staff keep only handbook-style read surfaces, or do we also want a broader read-only client navigation shell before self-service exists?

**Recommended default**: Keep the non-staff surface minimal and read-focused.

### OD-005 - Which routes should eventually move after the classification pass?

**Question**: Once route families are classified, which routes truly belong in `client.php` rather than `web.php`?

**Recommended default**: Decide this only after the classification matrix exists, not before.

## Attestation Audit Shape

Attestation-sensitive workflows should use a reusable audit shape so staff-captured business events are explicit and reviewable.

Minimum fields:

- `recorded_by_staff_user_id`
- `staff_role`
- `capture_context`
- optional `client_originator`
- optional `notes`
- optional `reason`

The implementation plan may extend this shape per workflow, but should not weaken it.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of authenticated routes in `routes/web.php` and `routes/client.php` are classified into the access tiers from this spec or into explicit support-route exemptions before implementation is considered complete.
- **SC-002**: Staff admins can navigate representative consultant-plane route families without unexpected `403` responses from visible actions.
- **SC-003**: Staff owners can perform internal governance capabilities while retaining the same operational delivery access as staff admins.
- **SC-004**: Staff operators can navigate only the explicitly assigned consultant-plane route families and are denied from unrelated client work and owner/admin-only governance surfaces.
- **SC-005**: Non-staff users lose incidental access to consultant-plane routes and retain only the documented read-focused client surface plus any retained hybrid exceptions explicitly documented for them.
- **SC-006**: Automated tests cover at least one representative route from each major route family changed by the implementation plan.
- **SC-007**: Attestation-sensitive workflows are directly executable by intended staff actors and always emit the documented stronger audit semantics.
- **SC-008**: Owner-only routes deny `staff admin` and `staff operator`, while staff-delivery routes allow `staff owner` and `staff admin` globally and `staff operator` only inside assigned scope.
- **SC-009**: The canonical internal-org invariant is enforced, and staff authority does not silently bind to an arbitrary internal organization.
