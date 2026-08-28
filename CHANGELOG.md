# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

This is a fork of [catalyst/moodle-tool_dynamic_cohorts](https://github.com/catalyst/moodle-tool_dynamic_cohorts).
Entries below cover the fork only; upstream history lives in that repository.

## Unreleased

### Added

- `lang/pt_br` language pack, kept in lockstep with `lang/en`.
- Behat smoke coverage: the rules page loads from site administration, and a rule can be
  created through the modal.
- `CHANGELOG.md`, `CLAUDE.md`, `phpcs.xml`, `.gitignore`, `.moodle-plugin-ci.yml`,
  `.phpcsignore`, `.stylelintrc.json`, `.github/PULL_REQUEST_TEMPLATE.md` and
  `.github/workflows/moodle-release.yml`, matching the fleet scaffolding.
- `templates/condition_count.mustache`, so the rules table's condition-count control is
  rendered from a template rather than assembled with `html_writer`.
- `task_process_rule` and `missingtag` strings (both language packs).

### Changed

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
