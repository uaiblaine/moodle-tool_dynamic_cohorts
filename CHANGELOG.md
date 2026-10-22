# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

This is a fork of [catalyst/moodle-tool_dynamic_cohorts](https://github.com/catalyst/moodle-tool_dynamic_cohorts).
Entries below cover the fork only; upstream history lives in that repository.

## [Unreleased]

### Changed

- A condition written by a category-level user records the category it was written in, every
  condition and not only the cohort field one (`scopecontextid`). Course, enrolment, role and
  cohort membership conditions check their course, category or cohort against it again whenever
  the rule is processed. If an administrator has since moved the course or category out, or hidden
  the cohort, the condition is broken: the rule is marked broken and stops, keeping its members,
  and the condition says why. A condition written site-wide records nothing and is not affected.
  Profile-field conditions are checked on save only, since a field's visibility depends on the
  capabilities of whoever wrote the rule. A category-level user who saves a rule, moved to
  another cohort or not, records the rule's current category on every condition it keeps,
  including one an administrator wrote; a refused save records nothing.

## [v5.2-r2] - 2026-10-22

Second release of this fork for Moodle 5.2 ($plugin->version = 2026042003). Rules can now be managed
in a course category: see "Managing rules in a course category" in the README.

Stages S0 to S3 of the category-context plan (`docs/category-context/PLAN.md` on `main`). S0
fixed three defects that were harmless while the capability could only be granted site-wide and
that become privilege-boundary breaches once rules are managed per category. S1 decides every
rule in its cohort's context. S2 makes the rules page reachable from a course category. S3 keeps
the conditions a category-level user writes inside that category.

### Added

- Conditions written by a user who manages rules in a course category, rather than site-wide,
  stay inside that category. A condition opts in (`condition_base::supports_category_scope()`)
  and checks its referents (`validate_scope()`): the modal offers only what lies in the category,
  and saving the rule checks again against the cohort the rule fills. That check includes the
  stored conditions sent back unchanged and the conditions of a rule saved without touching them,
  so a condition an administrator wrote outside the category blocks a category manager's save
  until it is removed. Per condition:
    - course completed, not completed and user enrolment take only courses of the category;
    - user role takes a category or a course of the category, never the system level (a role held
      higher up still counts, as for any role condition);
    - cohort membership takes the cohorts usable from the category: its own and its
      subcategories', and the visible ones of the contexts above it;
    - cohort field records the category on save (`scopecontextid`) and, while the rule is
      processed, matches only the cohorts usable from it;
    - user profile offers username, email and idnumber only where core would show that identity
      field to the user in the category, and a field the site hides (`hiddenuserfields`) only with
      `moodle/user:viewhiddendetails` site-wide; custom profile fields follow their visibility;
    - auth method, last login, account creation and interests refer to nothing outside the user.
  An administrator, and any rule of a system cohort, writes conditions as before.
- Setting "Conditions available in course categories" (`categoryconditions`): the conditions a
  category-level user may use. Every built-in condition by default; conditions from other plugins
  stay off until they support a category scope and are ticked. A condition switched off keeps
  working in existing rules, but a category-level user can no longer save a rule that uses it.

- The rules page in a course category: `index.php?contextid=<category context>`, offered as
  "Dynamic cohort rules" in the category's navigation to whoever may manage rules there. It lists
  the rules whose cohort lives in the category or below it; each row's actions are decided in that
  rule's own cohort context, so a subcategory where the capability is prohibited is listed without
  them. The page set-up and the listed scope live in `local\rules_page`. The rules report opens
  only in the system or a course category context and, below the system level, lists that
  context's subtree whatever context core's report services are asked for.
- Test data generators (`tests/generator/`), with a Behat entity for rules.
- README: managing rules in a course category.
- `mutations/gates.conf`: the mutation spec for these guards (`mdl mutate`), gates A-Z, AA-AZ and BA.

### Changed

- `tool/dynamic_cohorts:manage` is a course category capability now, and every rule is decided
  in its cohort's context by the new `local\rule_access`, which requires both that capability
  and `moodle/cohort:assign` there. This covers the rule form (its cohort list keeps the cohorts
  at or below the page that the user may attach a rule to), saving a rule (the cohort it fills
  and the cohort it is pointed at are both checked), the four web services (a rule id that does
  not exist is decided in the system context), both fragments, and the matching-users report,
  which is decided on its rule rather than on the context it was created in.
- Rule and condition events are raised in the rule's cohort context instead of the system
  context, also when the rule is being deleted.
- `tool_dynamic_cohorts_submit_condition_form` takes an optional `contextid`, the page the rule
  is edited from (system or course category).
- `cohort_manager::get_cohorts()` reads `{cohort}` directly. It used to go through
  `cohort_get_all_cohorts()`, which drops the cohorts of every context where the current user
  lacks `moodle/cohort:view` or `:manage`: a category manager holding `moodle/cohort:assign`
  would have got an empty list, and claiming or releasing a cohort would silently have done
  nothing.

### Fixed

- A rule whose cohort was deleted is now listed on the system rules page: the report joined the cohort
  with an INNER JOIN, so such a rule was listed nowhere and could not be deleted.
- Saving a rule rewrote any condition whose id the form sent, including another rule's: the
  submitted record has its `ruleid` stripped, so the stored row kept its own rule and was
  updated in place. A submitted condition id must now belong to the rule being saved, and the
  persistent is built from that checked integer id, not from the raw submitted value.
- The rule form stored its `conditionjson` field without checking it; only the condition modal
  validated. Each new or changed condition is now validated on save: its class must be a
  discovered condition, its config data must pass the condition's own validation, and a
  condition that is not broken must build its SQL without throwing (a `TypeError` there would
  escape the `'*'` observer on every event). A stored condition sent back unchanged is not
  revalidated, so a rule whose condition class was uninstalled can still be saved and is
  marked broken as before. A refused condition rolls the whole save back.
- `tool_dynamic_cohorts_delete_rules` checked the capability once, in the system context. Each
  rule is now also checked in its cohort's context (`rule::get_context()`, the system context
  for a rule whose cohort is gone), before anything is deleted.

## [v5.2-r1] - 2026-10-18

First release of this fork for Moodle 5.2 ($plugin->version = 2026042000). The 5.1, 5.2 and 5.3
branches share the entries below; each carries only its own version.

Synced with upstream `MOODLE_404_STABLE` at 5e01837 (catalyst#185). Upstream's open pull
requests were reviewed on 2026-10-18: #175 is adopted below; #178 (observer tests) and #180
(eslint) were already covered by this fork; #183 (count cache invalidation), #176 (new course
completion condition, which deletes the two conditions existing rules reference) and the
`sql-perf` branch were not taken.

### Added

- The Moodle 5.2 branch of the fleet's branch-per-version layout: `MOODLE_502_STABLE` declares only
  Moodle 5.2 in `$plugin->supported` and numbers its releases in that version's range
  (`2026042000` = v5.2-r1). That is below the `2026082801` this fork shipped before; the README
  gives the version reset a site needs to upgrade. Moodle 5.3 lives on `main`.
- Site setting _Trigger events during bulk processing_ (`bulkprocessingevents`, off by
  default), ported from upstream catalyst#185: bulk processing then triggers
  `cohort_member_added` / `cohort_member_removed` for every row it writes or deletes, so
  cohort enrolment sync and other observers see bulk changes. Unlike upstream, a chunk whose
  batch insert failed is replayed through `cohort_add_member()` (which triggers its own
  events) and is not announced twice; rows a failed chunk had already committed still get
  theirs.
- `lang/pt_br` language pack, kept in lockstep with `lang/en`.
- Behat smoke coverage: the rules page loads from site administration, and a rule can be
  created through the modal.
- `CHANGELOG.md`, `CLAUDE.md`, `phpcs.xml`, `.gitignore`, `.moodle-plugin-ci.yml`,
  `.phpcsignore` and `.github/PULL_REQUEST_TEMPLATE.md`, matching the fleet scaffolding.
- `templates/condition_count.mustache`, so the rules table's condition-count control is
  rendered from a template rather than assembled with `html_writer`.
- `task_process_rule` and `missingtag` strings (both language packs).

### Changed

- No `moodle-release.yml` workflow: `tool_dynamic_cohorts` on moodle.org belongs to Catalyst, so
  this fork never publishes there. It tags its own releases instead (`v5.3-r1`, `v5.2-r1`,
  `v5.1-r1`, ...), each a GitHub release carrying a `git archive` zip of the tagged commit.
- Target Moodle 5.1 and 5.2 (`$plugin->supported = [501, 502]`,
  `$plugin->requires = 2025100600`). Support for 4.4/4.5 is dropped.
- CI moved from the Catalyst reusable workflow to the moodle-an-hochschulen one, with a
  job per supported branch. The Catalyst workflow gates every job behind a `pre_job` step
  requiring a protected ref on push, so pushes to this fork ran **no jobs at all** and
  still reported success.
- PHPUnit metadata moved from doc-comment annotations to PHP attributes
  (`#[CoversClass]`, `#[DataProvider]`). PHPUnit 11.5 raises a deprecation per
  doc-comment annotation and PHPUnit 12 drops them; the moodle-cs constraint that forced
  doc-comments applied only to the 4.05 leg, now out of range.
- The wildcard event observer is registered `'internal' => false`, so rule processing runs
  after the triggering transaction commits instead of inside it. Core's own wildcard
  observer (`tool_log`) does the same, for the same reason.
- `condition_manager::get_all_conditions()` memoises per request and no longer builds each
  condition twice. It is called for every event the site fires, so it reflected and
  instantiated all thirteen condition classes on each one.
- The bulk-processing insert replays a failed chunk member by member through
  `cohort_add_member()`, re-checking membership per row — the fleet's chunk-then-replay
  rule for mass membership writes.
- `tool/dynamic_cohorts:manage` declares `RISK_PERSONAL`. It is the only gate on a
  downloadable report of every matching user's username, email and idnumber.
- `styles.css` reads the theme's own link colour instead of a hardcoded `#0036ae`, so a
  site's configured palette and dark mode both apply.
- The interests condition renders its badges with the Bootstrap 5 spelling
  (`badge bg-secondary text-dark`); `badge-secondary` resolved only through
  `bs4-compat.scss`, which flags it deprecated and which Moodle 6.0 removes.
- Condition edit/delete/view controls are `<button>` elements with accessible names. They
  were `<span>`s with click handlers: unreachable by keyboard, announced as nothing.
- `conditionchnagesnotapplied` renamed to `conditionchangesnotapplied`.

### Fixed

- `course_completed` stored the hidden completion date (today's midnight) when the operator
  was "any" (upstream issue #174, PR #175). The SQL already ignored it, so no rule matched
  differently; the saved configuration now carries 0.
- **Deleting a rule never worked.** `amd/src/manage_rules.js` called the web service
  `tool_dynamic_cohorts_delete_rules` with a stray backtick inside the string literal, so
  every delete failed with an "invalid parameter" exception. The typo shipped in
  `amd/build` too.
- **`course_completed` used an INNER JOIN**, which filters the whole result set regardless
  of the rule's logical operator. Under OR, a user matching a different condition was
  dropped for having no `{course_completions}` row at all. Now a LEFT JOIN with the course
  predicate in the ON clause, matching `course_not_completed`.
- **The cohort_field self-exclusion guard was defeated by SQL operator precedence.** `AND
  c.id <> :own` was appended to a WHERE that can carry a top-level `OR`, so the rule's own
  managed cohort was excluded from only one disjunct and the rule re-affirmed the members
  it had added itself. Both WHERE bodies are now parenthesised.
- **Date "is not empty" compared the field against the configured date instead of 0**, so
  users whose date field was empty were included and users whose date matched that
  timestamp were excluded.
- **`user_role` "include children" matched fewer users, not more.** It replaced the
  ancestor contexts with the descendants, so a user holding the role by a system-level
  assignment matched with the box clear and stopped matching once it was ticked — and
  `process_rule()` then removed them from the cohort. The set is now ancestors + self +
  descendants.
- **The multiselect "is equal to" pattern was an unanchored prefix match**: with options
  "Option 1" and "Option 10", selecting "Option 1" matched a user storing "Option 10".
- **A deleted rule wedged the ad-hoc task queue.** `process_rule::execute()` caught
  `\Exception` around `rule::get_record()`, which returns `false` rather than throwing.
  The `false` reached `rule_manager::process_rule()` and raised a `TypeError` — an
  `\Error`, which that catch could not have seen either — so the task failed and was
  requeued forever over a rule that no longer exists.
- **The privacy export dropped the rules.** `export_user_data()` called `export_data()`
  twice on the same subcontext, and the writer overwrites rather than merges, so a user
  with both a rule and a condition got an export containing only the conditions.
- **`rule_form::get_default_cohort()` fatalled the rule edit modal** when a rule outlived
  its cohort: `get_record()` returns `false`, which does not satisfy the `?stdClass`
  return type. The modal was the one screen that could have repointed the rule.
- `tool_dynamic_cohorts_output_fragment_condition_form()` performed no capability check.
  `core_get_fragment` does none of its own beyond `validate_context()`, so any
  authenticated user could render a condition form and read the cohort, role, course,
  enrolment-method and profile-field values it lists.
- `rule_created` and `condition_created` logged `crud = 'u'`, and `rule_deleted` logged
  `'u'` too, so creations and deletions were indistinguishable from updates in the log.
- Tag names reached two raw HTML sinks unescaped (`html_writer::tag()` does not escape its
  contents, and select option labels render through a triple stash). Escaped in the getter.
- Course and category names were formatted with `'escape' => false` but rendered through a
  triple stash, which needs the escaped spelling.
- `user_role`'s category description used the capability-aware `core_course_category::get()`,
  which throws for a category the viewer cannot see; it now reads the name from the table,
  as the sibling course branch already did.
- A tag removed after a condition was saved produced an undefined-array-key warning and a
  blank badge. Same class of bug in the shared menu/select description helper.
- `rule_manager::build_edit_url()` and `build_delete_url()` returned URLs to `edit.php` and
  `delete.php`, deleted upstream when those flows moved into modals. Both removed; the "add
  a rule" button's no-JS fallback no longer 404s.
- `rule_form` called `setDefault('isstepschanged', …)` for an element named
  `isconditionschanged`, so the default was never applied.
- `index.php` broke out of its warning loop on the first hit, so a site with broken rules
  never saw the realtime warning.
- `process_form()` re-threw a freshly constructed, argument-less copy of the exception
  after a rollback that already re-throws the original; it now catches `\Throwable`.
- 30 eslint warnings across both AMD modules. CI runs `grunt --max-lint-warnings 0`, so
  each of them would have failed the build; a plain local `grunt` prints them and exits 0.
- Dynamic lang-string ids in `manage_rules.js` and in two condition classes, which hid
  eleven live keys from every static sweep and from translators.
- The ad-hoc task had no `get_name()`, so its admin-visible name was untranslated English.
- Transposed `coding_exception` messages in three event classes named the wrong key.
- The "a broken rule can't be enabled" message the delete/enable modal shows was a
  hardcoded English literal while its lang string sat unused; the string is now used.
- Four lang strings superseded by the `completed:*` family (`ruledeleted`, `ruledisabled`,
  `ruleenabled`, `usersforrule`) removed from both packs.
