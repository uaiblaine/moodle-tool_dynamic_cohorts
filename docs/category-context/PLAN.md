# Rules in course-category context: plan

Status: **planned, nothing implemented**. Written 2026-10-21 against `main` at c0a41da (5.3,
`2026100500`). Lands on `main` first and is cherry-picked to `MOODLE_502_STABLE` and
`MOODLE_501_STABLE`, as every change on this fork does.

## Goal

A user holding a role in a course category can create, edit, enable, disable and delete rules for
the cohorts that live in that category (and its subcategories), without seeing or touching rules for
other categories or the system, and without the rule's conditions turning into a window onto data
the user could not otherwise see. Site administrators keep exactly what they have today.

## Owner decisions (2026-10-21)

| Id | Decision |
|---|---|
| D1 | The scoping proposed in the feasibility read is accepted as written (table under "Conditions"). |
| D2 | Conditions on site-wide user attributes (`auth_method`, `user_last_login`, `user_created`, `user_profile`, `user_profile_interests`) are **allowed in category context by default**, and the administrator can switch them off. |
| D3 | The conditions available in category context are an **administrator-configurable list**. Its default is conservative in the sense of D1: every built-in condition is on, each one scoped; conditions from other plugins are off until the administrator enables them. |
| D4 | A category user needs **both** `tool/dynamic_cohorts:manage` **and** `moodle/cohort:assign` in the cohort's context. |
| D5 | A category cohort may receive **any user on the site**, as core's `cohort/assign.php` already allows. The rule's user universe does not shrink. |

D3 is our reading of "conservative, configurable list" together with D2; if the owner meant a
narrower default, only the setting's default changes.

## Verified facts this plan rests on

Core (5.3 checkout, `public/`):

- `moodle/cohort:manage` and `moodle/cohort:assign` are declared at `CONTEXT_COURSECAT`
  (`lib/db/access.php`); cohorts live in a system or category context (`cohort/edit.php`).
- `cohort/edit.php` and `cohort/assign.php` redirect away from any cohort with a `component`, and
  core's cohorts system report hides edit/assign actions for them. Claiming a cohort locks it
  against manual edits for everyone, category managers included.
- `cohort_get_available_cohorts($context, ...)` is the core helper for "cohorts usable from this
  context" (own and parent contexts, visible ones, or all with `moodle/cohort:view`).
- `extend_navigation_category_settings` is still a plain callback in 5.3
  (`settings_navigation::load_category_settings()`, not in `lib/db/legacyhooks.php`).
- `profile_field_base::is_visible()`: `PROFILE_VISIBLE_ALL` is visible to everyone;
  `PROFILE_VISIBLE_TEACHERS` needs `moodle/site:viewuseridentity` (outside a user context);
  `PROFILE_VISIBLE_PRIVATE` and `PROFILE_VISIBLE_NONE` need `moodle/user:viewalldetails`, which for
  "all users" can only mean the system context (user contexts are children of the system, never of
  a category).
- `\core_user\fields::get_identity_fields($context)` returns the identity fields a viewer may see in
  a context.
- Category deletion runs `cohort_delete_category()`, which deletes or moves the cohorts.
- `core\persistent::__construct($id, $record)` reads the stored row first, then applies only the
  properties present in `$record`.

Plugin (`main`):

- Everything is pinned to the system context: the capability (`db/access.php`,
  `contextlevel => CONTEXT_SYSTEM`), `index.php` (`admin_externalpage_setup`), `rule_form`
  (`get_context_for_dynamic_submission()`), the four web services, both fragments in `lib.php`, both
  system reports, all seven events.
- The cohort list is every cohort on the site: `cohort_manager::get_cohorts()` wraps
  `cohort_get_all_cohorts()`, used by `rule_form`, by `rule_manager::validate_submitted_data()` and by
  the `cohort_membership` condition.
- The JS already passes `M.cfg.contextid` (the page's context) to both fragments; the web services
  receive only a rule id, or nothing (`submit_condition_form`).
- The rules report joins `{cohort}` with an INNER JOIN, so a rule whose cohort was deleted is
  listed nowhere, not even to an administrator.
- Conditions from any component are discovered
  (`core_component::get_component_classes_in_namespace(null, '\local\tool_dynamic_cohorts\condition')`).

## Three latent defects (stage S0, before any scoping)

They are harmless while the only capability is site-wide, because whoever holds it already controls
every rule. Each becomes a privilege boundary breach the moment the capability is granted in a
category, so they ship first, on their own pull request, with tests.

1. **Condition taken over from another rule.** `condition_manager::process_condition_json()` builds
   `new condition($submitted['id'], $record)` with `ruleid` stripped from `$record`. A submitted id
   that belongs to rule B is read with B's `ruleid` and then updated in place from rule A's form.
   Fix: a submitted id must be one of `$rule->get_condition_records()`; anything else is refused
   (not silently created as new, which would hide the attack).
2. **`configdata` never revalidated on save.** `conditionjson` is client-controlled. Only the
   condition modal's web service runs `config_form_validate()`; the rule save stores whatever
   arrives. Fix: `condition_manager::process_form()` instantiates each submitted condition, checks the
   class is a discovered condition and runs its validation again before anything is written, inside
   the existing transaction.
3. **`delete_rules` takes any list of ids.** Fix: per-rule access check (S1's `rule_access`), so one
   inaccessible id refuses the whole call before any deletion.

Done when: one test per defect reddens if its fix is removed (`mdl mutate --fast`, both DB
families).

## Design

### Authority model

- **A rule's context is its cohort's context**, read from `{cohort}.contextid` at the moment of each
  check. No new column: a stored copy could drift when an administrator moves a cohort, and the
  cohort is the thing whose membership the rule changes.
- One class, `classes/local/rule_access.php`, is the only place that decides:
  - `context_for_rule(rule $rule): context` returns the cohort's context, or the system context when
    the cohort no longer exists (an orphan rule is administrators' business only).
  - `require_manage(context $context)` requires `tool/dynamic_cohorts:manage` **and**
    `moodle/cohort:assign` in that context (D4).
  - `has_site_scope(): bool` is `tool/dynamic_cohorts:manage` in the system context. A site-scope
    user is never subject to condition scoping (administrators keep today's behaviour).
- `db/access.php`: `contextlevel => CONTEXT_COURSECAT`, `RISK_PERSONAL` kept, archetypes still
  empty. Existing system-level grants keep working because category contexts inherit them. The
  README tells administrators to add the capability (and `moodle/cohort:assign`, which the core
  Manager role already has) to a category-level role.
- **Saving a rule** checks the old cohort's context (when editing) **and** the new cohort's context.
  Moving a rule from a cohort the user controls to one they do not, or the reverse, is refused.

### Pages and navigation

- `index.php` takes an optional `contextid`. Without it, or with the system context, it behaves as
  today (`admin_externalpage_setup`). With a category context it does `require_login()`,
  `rule_access::require_manage($context)`, sets `$PAGE` context, URL and `admin` layout, and stays
  the only page script (the repo `CLAUDE.md` invariant). Report actions and the no-JS fallback carry
  the `contextid`.
- `lib.php` gains `tool_dynamic_cohorts_extend_navigation_category_settings()`, adding a "Dynamic
  cohort rules" node when `rule_access` allows the category.
- The broken-rules and realtime warnings on `index.php` iterate only the rules the page lists.

### Rules report

- Category page: base condition `ctx.path = :path OR ctx.path LIKE :pathchildren` on the cohort's
  context (subcategories included, matching how category capabilities inherit).
- System page: the cohort join becomes a LEFT JOIN so orphan rules are listed (to site scope only),
  with the cohort column showing that the cohort is gone.
- Each row's actions are shown only when `rule_access` allows that row's context (a subcategory may
  override the capability away).

### Rule form, web services and fragments

- `rule_form::get_context_for_dynamic_submission()`: the existing rule's context, or for a new rule
  the `contextid` from the form data, validated as a system or category context.
  `check_access_for_dynamic_submission()` calls `rule_access::require_manage()`.
- Cohort options: cohorts whose context is the page context or below it, unclaimed, and for which
  `rule_access` allows the cohort's own context. `cohort_manager::get_cohorts()` takes the context
  and the same filter applies in `rule_manager::validate_submitted_data()` (the server-side twin of
  the option list).
- `get_conditions`, `get_total_matching_users_for_rule`, `toggle_rule_status` and `delete_rules`
  derive the context from the rule id, `validate_context()` it and call
  `rule_access::require_manage()`.
- `submit_condition_form` gains a `contextid` parameter (the JS passes `M.cfg.contextid`), validated
  the same way, and hands the context to the condition so its validation is scoped. New parameter
  means `db/services.php` and a `version.php` bump.
- The two fragments: `matching_users` derives the context from `ruleid`; `condition_form` uses the
  fragment's context argument, then `rule_access`. Both keep their own explicit check (repo
  invariant).

### Conditions

New contract on `condition_base`:

- `set_scope(?context $context)`: the context the rule is being written for; `null` or site scope
  means unscoped. `condition_form` sets it before `config_form_add()`, the web service before
  validation, `condition_manager::process_form()` before revalidation.
- `supports_category_scope(): bool`, default **false**, so a condition from another plugin is not
  available in category context until its author implements scoping **and** the administrator
  enables it (D3). Built-ins return true.
- `validate_scope(): array` returns errors for referents outside the scope. Called from
  `config_form_validate()` and from the save-time revalidation, so the option list and the server
  check cannot disagree.

Admin setting `tool_dynamic_cohorts/categoryconditions`
(`admin_setting_configmulticheckbox`, one box per discovered condition, default: every built-in).
It is read wherever the condition list is built and again at save time. A condition the
administrator later switches off keeps working in existing rules (processing runs as the system),
but a category user can no longer save a rule that contains it; the error names the condition.

Per condition, applied only when scoped:

| Condition | Scoping |
|---|---|
| `course_completed`, `course_not_completed`, `user_enrolment` | Course must sit under the scope context; course selector and `validate_scope()` both check it. |
| `user_role` | Context level offers category and course only (no system); the category or course must sit under the scope context. See O1. |
| `cohort_membership` | Options and validation from `cohort_get_available_cohorts($scope)`. |
| `cohort_field` | Matches only cohorts whose context is available from the scope (same helper, as an SQL filter on `{cohort}.contextid`). |
| `user_custom_profile` | Fields offered only when visible to the actor per `is_visible()`'s rules: `ALL` always; `TEACHERS` with `moodle/site:viewuseridentity` in the scope; `PRIVATE`/`NONE` with `moodle/user:viewalldetails` in the system context. |
| `user_profile` | On by default (D2). Its identity fields (`username`, `email`, `idnumber`) offered only when in `get_identity_fields($scope)`; the other fields always. |
| `auth_method`, `user_last_login`, `user_created`, `user_profile_interests` | On by default (D2); no extra scoping. |

Descriptions already read names straight from the tables (repo invariant), so a rule written by an
administrator for a category cohort, with a referent outside the category, still lists correctly.
A category user can view, enable, disable and delete such a rule, but saving it is refused until the
out-of-scope condition is removed.

### Matching users report

In category context the columns and filters come from `get_identity_fields($context)` plus the full
name. The system context keeps today's fixed username, email and idnumber, so nothing changes for
administrators. The download follows the same columns.

### Events, privacy, processing

- The seven events take the rule's context (`rule_access::context_for_rule()`), so category logs
  show them. `matching_failed` keeps working for orphan rules (system fallback).
- The privacy provider is unchanged: the plugin's own rows carry only `usermodified` and are not
  per-category data; it keeps reporting them in the system context.
- Scheduled and ad-hoc tasks and the `'*'` observer run as the system and change nothing.

## Stages

Each stage is one pull request on `main`, with its version bump and `CHANGELOG.md` entry, then
cherry-picked down (`2026100501`, `2026042001`, `2025100601` for the first; each later stage the
next counter on each branch).

| Stage | Scope | Done when |
|---|---|---|
| S0 | The three latent defects. | One reddening test each; mutation sweep clean on pgsql and MariaDB. |
| S1 | `rule_access`, capability `contextlevel`, contexts in the form, four web services, two fragments, events; `contextid` on `submit_condition_form`. Still only reachable from the system page. | Capability matrix tests (below) green; existing suite unchanged. |
| S2 | `index.php?contextid=`, navigation callback, rules report scoping and orphan LEFT JOIN, cohort option scoping, warnings scoped. | A category manager reaches the page from the category's "More" menu and sees only that subtree's rules (Behat). |
| S3 | Condition contract, the twelve built-ins, `categoryconditions` setting, save-time scope check. | One test per scoped condition refusing an out-of-scope referent at save, not only in the modal. |
| S4 | Matching users report columns by context; README (how to grant), lang strings in `en` and `pt_br`. | Report test asserting a hidden identity field is absent in category context and present at system. |

## Tests

PHPUnit, with a category manager on category A, a sibling category B and a subcategory A1:

- Can list, create, edit, toggle and delete rules for cohorts in A and A1; none of these for B or
  system cohorts (each operation, through its web service or dynamic form, `require_capability`
  exception asserted).
- Without `moodle/cohort:assign`, with the plugin capability alone: everything refused (the control
  for D4).
- A capability prohibited on A1 hides A1's rules from the A page.
- Editing a rule in A to point at a cohort in B is refused; the reverse too.
- A crafted `conditionjson` with a condition id from another rule is refused (S0).
- A crafted `conditionjson` with a course in B is refused at save (S3).
- A site-scope user can still do everything, including out-of-scope referents (no regression).
- Orphan rule: listed to site scope only.

Behat (thin): a category manager adds a rule from the category navigation, picks a cohort, adds one
condition, saves; the rule appears on the category page and not on a sibling category's page.

## Gates

- `security-diff-read` skill over every stage's diff (capabilities, web services, state changes).
- MDL Shield `review full` on S0+S1 and on S3 at least. Credits are exhausted since 2026-10-15: post
  only when the owner says so.
- `mdl ci moodle-tool_dynamic_cohorts-503 --matrix --behat` before each merge, and on each
  backport branch; `mdl mutate --fast` on the access guards, both DB families.

## Open points

- **O1. Role holders in parent contexts.** `user_role` matches a role held in the chosen context or
  any of its parents, so a scoped rule "manager in course X (in A)" also matches users holding it at
  the system level, which tells a category manager who the site managers are. Recommendation: keep
  the semantics (the same condition must not mean different things depending on who wrote it) and
  accept the exposure; the alternative is clipping parents to the scope when scoped.
- **O2. Claiming a cohort is more than assigning.** With D4, a user with `moodle/cohort:assign` but
  not `moodle/cohort:manage` can attach a rule and thereby lock the cohort against manual edits
  (core hides edit actions for cohorts with a `component`). Requiring `moodle/cohort:manage` too
  would close that; D4 as decided does not.
- **O3. D3 default**, as noted under the decisions table.
