# Claude instructions for `tool_dynamic_cohorts`

This file is auto-loaded as context whenever Claude works in this plugin's directory
tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding style, CI gates,
lang-string rules, the `mdl` environment, git rules) — do not repeat them here. This
file keeps only what is true for this plugin.

Plugin context: a Moodle **admin tool** ("Dynamic cohorts") that keeps cohort
membership in step with user data. A *rule* names one cohort, a logical operator and a
set of *conditions*; every user matching the conditions is added to the cohort and every
non-matching member is removed. It owns two tables, `tool_dynamic_cohorts` (rules) and
`tool_dynamic_cohorts_c` (conditions), both `core\persistent` models, and it takes
ownership of the cohorts it manages by stamping `{cohort}.component`. Supports Moodle
**5.1 and 5.2** (`$plugin->requires = 2025100600`, `$plugin->supported = [501, 502]`).
CI is the moodle-an-hochschulen reusable workflow, one job per supported branch in
`.github/workflows/ci.yml` — **update those jobs when `supported` changes**. Mounted on
the m501 and m502 stacks at `admin/tool/dynamic_cohorts` (see
`~/dev/moodle-dev/plugins.conf`).

**This is a fork of `catalyst/moodle-tool_dynamic_cohorts`.** Upstream's default branch
is `MOODLE_404_STABLE`; ours is `MOODLE_501_STABLE`. Upstream copyright headers
(`2024 Catalyst IT`) stay on the files they were written for — only files first authored
here carry `2026 Anderson Blaine`. `upstream` is configured as a remote, so
`git log upstream/MOODLE_404_STABLE` is how you check whether a defect is ours.

## Commands

```sh
mdl ci moodle-tool_dynamic_cohorts --matrix   # every leg GitHub runs, before any push
mdl phpunit m502 tool_dynamic_cohorts         # targeted tests
mdl behat m502 @tool_dynamic_cohorts          # Behat smoke tests
mdl grunt m502 admin/tool/dynamic_cohorts     # rebuild amd/build (commit with src)
mdl purge m502                                # after PHP changes that affect output
```

## Code layout

```
classes/
  rule.php, condition.php          persistents; their after_* hooks are the ONLY
                                   cache invalidation points
  rule_manager.php                 rule CRUD, matching, and process_rule() — the
                                   add/remove engine, bulk and non-bulk
  condition_manager.php            condition CRUD + build_sql_data(), which fuses
                                   every condition into ONE statement
  condition_base.php               abstract base every condition extends
  condition_sql.php                the (join, where, params) triple + the alias
                                   generators that keep params from colliding
  cohort_manager.php               claims/releases {cohort}.component
  local/tool_dynamic_cohorts/condition/    the twelve built-in conditions
  external/                        four AJAX web services, all system-context
  reportbuilder/                   the rules table and the matching-users table
  task/process_rules.php           scheduled: queues one ad-hoc task per enabled rule
  task/process_rule.php            ad-hoc: processes one rule
  observer.php                     registered for '*' — every event on the site
amd/src/manage_rules.js            the whole rules UI; every action is a modal
amd/src/condition_form.js          the condition sub-form inside the rule modal
```

## Architecture gotchas

- **There are no page scripts other than `index.php`.** Upstream deleted `edit.php`,
  `delete.php`, `toggle.php` and `users.php` in 94e7a8e when those flows became modals.
  Anything that looks like it wants a URL to one of them is wrong: editing and deleting
  go through the report builder actions in `systemreports\rules::add_actions()`, which
  all point at `index.php` and carry the rule id in a `data-ruleid` attribute for the JS.

- **`observer::process_event` is registered for `'*'`.** It runs on *every* event the
  site fires, so anything it does is on the hot path of everything. It is already
  guarded by the global `realtime` setting and by each rule's own flag; keep it that
  way and keep the work inside it proportional. Core catches `\Exception` from
  observers (`lib/classes/event/manager.php`) but **not `\Error`** — a TypeError here
  escapes into whatever operation fired the event.

- **`condition_manager::build_sql_data()` concatenates every condition's SQL into one
  statement, and merges params with `+=`.** The union operator does not overwrite, so
  two conditions emitting the same parameter name would silently drop the second value
  while its SQL still referenced the name. Uniqueness comes from
  `condition_sql::generate_param_alias()` / `generate_table_alias()`, whose static
  counters start at 1000 and produce the `rbparam`/`rbalias` prefixes report builder
  requires. **A condition must never hardcode a parameter or table alias.**

- **Ad-hoc tasks must not throw on a permanent failure.** `rule::get_record()` returns
  `false` for a missing record — it does not throw — so `process_rule::execute()` checks
  the result rather than wrapping the call in a catch. Passing `false` on to
  `rule_manager::process_rule()` raised a `TypeError`, and core requeued the task
  forever over a rule that no longer existed.

- **Bulk processing bypasses the cohort API on purpose.** `process_rule()` writes
  `{cohort_members}` with `insert_records`/`delete_records_select` when the rule has
  `bulkprocessing` set, which is why it is fast and why no `cohort_member_added` /
  `cohort_member_removed` events fire. Anything downstream that depends on those events
  (enrolment sync, notifications) will not see bulk changes. The setting's help string
  says so; keep it saying so.

- **`releasemembers` deletes rows directly too.** `cohort_manager::unmanage_cohort()`
  empties `{cohort_members}` with no events when the setting is on. Deleting a rule
  therefore empties its cohort silently.

- **Fragments carry their own access control.** `core_get_fragment` validates the
  context and nothing else — its own docblock says the callback is responsible for the
  security checks. Both callbacks in `lib.php` call `require_capability()` explicitly;
  do not remove those on the grounds that the system report also checks, because only
  one of the two fragments renders a system report.

- **A condition's `is_broken()` is what keeps a rule from producing garbage.** When a
  condition's referent disappears (course, role, cohort, tag, profile field),
  `is_broken()` must return true — `build_sql_data()` then short-circuits the whole
  rule to `1=0` rather than matching everyone. When adding a condition, write the broken
  case first.

- **A condition's JOIN must be neutral; only its WHERE may filter.** `build_sql_data()`
  concatenates every join unconditionally and combines only the WHERE fragments with the
  rule's operator, so an INNER JOIN filters the whole result set whatever the operator is.
  Under OR that silently drops users who match a different condition. Always LEFT JOIN,
  with the discriminating predicate in the ON clause — `course_completed` shipped as an
  INNER JOIN for its whole life and nothing caught it.

- **Anything appended to a condition's WHERE with AND must be parenthesised.** Several
  WHERE bodies carry a top-level OR ("include missing data", and `get_date_sql()`'s
  "is empty"). AND binds tighter, so `A OR B AND guard` applies the guard to B alone. That
  is how `cohort_field`'s self-exclusion — the guard that stops a rule re-affirming the
  members it added itself — was defeated.

- **`{{{description}}}` is a raw sink, `{{name}}` is not.** Condition descriptions carry
  deliberate markup (badges, line breaks), so `conditions.mustache` triple-stashes them.
  Every value entering a description therefore needs the ESCAPED spelling: call
  `format_string()` WITHOUT `'escape' => false`, and never hand `html_writer::tag()` a
  raw user string — it does not escape its contents.

- **Descriptions must not call capability-aware core APIs.** `get_config_description()`
  runs wherever a rule is listed, and `core_course_category::get()` throws for a category
  the viewer cannot see. Read names from the table, as the course branch does. The broken
  case is already handled separately by `get_broken_description()`.

- **`simpledata => true` on `rulesconditions` / `conditionrecords` is deliberate**, even
  though they hold persistent objects rather than scalars. Dropping it was tried and
  reverted: it makes static acceleration clone on every get — on the wildcard observer's
  hot path — and fixes nothing, because the only mutation of a cached rule
  (`mark_broken()`) writes through to the database in the same call. Two tests pin the
  identity semantics the flag provides.

## Testing notes

- Conditions are tested one file per condition under
  `tests/local/tool_dynamic_cohorts/condition/`. The pattern is: build the instance with
  `condition_base::get_instance(0, (object)['classname' => …])`, `set_config_data()`,
  then assert on `get_sql()` by actually running it through `$DB` — asserting on the SQL
  string alone proves nothing about whether it runs on both drivers.
- PHPUnit metadata is **attributes**, not doc-comments (`#[CoversClass]`,
  `#[DataProvider]`). The doc-comment form used to be mandatory here because moodle-cs
  on the 4.05 leg cannot see attributes and reported `TestCaseCovers.Missing` for every
  method in the file; 405 is out of the supported range now, so that constraint is gone.
  Do not convert back.
- **`advanced_testcase` runs every test inside a transaction**, and the observer is
  registered non-internal, so the callback is buffered until a commit that never comes.
  Any test that needs the observer to fire calls `$this->preventResetByRollback()` first
  — `observer_test` does. Without it the assertion measures nothing and passes.
- **Tests that render a condition description need `setAdminUser()`.** The context and
  profile-field option lists are built from capability-aware core APIs, so with no user
  they come back empty and the description is compared against something the condition
  could never have produced.
- `mdl ci` needs to clone core from GitHub. When the Docker VM has no egress, the usable
  substitutes are: `php -l` over the tree in a stack container, `npx eslint
  --max-warnings 0` and `npx grunt amd` against the mounted `moodle-502` checkout with the
  `node:22-bookworm` image, `vendor/bin/phpunit --testsuite tool_dynamic_cohorts_testsuite`
  inside the webserver container, and rendering every template against its own example
  context. That covers phplint, grunt and phpunit; phpcs, phpdoc, validate and savepoints
  still need the network.
- Behat is thin and deliberately so: the rules page loads, a non-privileged user does
  not see the controls, and one rule can be created through the modal. Everything about
  matching belongs in PHPUnit.

## When in doubt

Follow the patterns in existing files. Where this fork and upstream disagree, the
divergence is deliberate and recorded in `CHANGELOG.md` — read that before "restoring"
anything to the upstream shape.
