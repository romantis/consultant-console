Audience: Human (Operator)

# Memberships & Invites (Plain-English)

## Who is this for
- People who add or manage members and invites during an engagement.
- Use this when you want a quick, non-technical reference.

## When to use it
- Setting up a new organization and deciding who should be included.
- Adding contributors, approvers, or observers to an existing org.
- Transferring ownership or clearing out old invites.

## Steps
1. Create the organization
   - Internal staff creators do not become client members automatically. Staff owners/admins keep access through staff authority.
   - You can pass a `members[]` list of client collaborator emails. Staff admins can seed admin/member/viewer roles; staff owners can also seed a client owner.
   - If an email already belongs to a user, they join immediately. New emails become “pending invites.”
   - The overview shows only active members so invites don’t clutter the dashboard.
2. Manage people in Settings → Members/Invites
   - Add by email or user ID. Change roles as needed. The system prevents duplicate invites and memberships.
   - You can’t remove the last owner, and only owners can promote someone else to owner.
3. How invites work
   - Each invite stores the org, email, name (optional), role, and inviter. We prevent duplicate invites for the same email in the same org.
   - Invites use the same flow no matter where you create them (wizard, Settings, API).
4. Accepting an invite
   - The invitee clicks a secure link and signs in with the same email. We convert the invite into a real member and log the change.
   - For ownership transfers, accepting the invite makes the new owner and demotes the previous one so only one owner remains.
   - Internal staff operator invites land on Profile settings after acceptance; operators do not enter the client handbook until operator assignments are available.
5. Engagement participant selection
   - Only active members can be picked as participants. Pending invites aren’t selectable until acceptance.
6. Internal bootstraps
   - Internal commands (like `php artisan console:bootstrap`) add members directly without invites to speed up staging and demos.

## Troubleshooting
- The email invited already belongs to a user: They’re added immediately as a member with the selected role (no pending invite).
- The invitee used a different email: They must sign in with the exact same email the invite was sent to.
- Transfer ownership: Send an owner-level invite. When it’s accepted, the new owner replaces the previous one.
- Invites seem stuck: Resend the invite link and check the activity feed; the invite will show as pending until accepted.

## Glossary
- Membership: An active member of the organization. Drives permissions and visibility.
- Pending membership: A staged invite that turns into a membership after acceptance.
- Owner: The only role that can promote other owners or remove the last owner.

## Cross-links
- Engineer version: [docs/engineer/memberships-and-invites.md](../engineer/memberships-and-invites.md)
