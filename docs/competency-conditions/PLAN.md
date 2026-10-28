# Competency and learning plan conditions: plan

Status: decisions recorded 2026-10-27 (end of file); stage S0 in progress.

## Goal

Two new built-in conditions:

- **`competency_proficiency`**: the user is (or is not) proficient in one or more competencies,
  read from the site-wide rating or from the rating given in a course.
- **`learning_plan`**: the user has (or has not) a learning plan created from given templates,
  in given statuses (draft, waiting for review, in review, active, complete).

Both must keep a rule as one SQL statement that scales with the site (scheduled run over every
user) and costs an index probe for one user (the `'*'` observer), and both must work for a rule
written in a course category, where frameworks and templates live in system or category contexts.

## Verified facts (core 5.3, `~/dev/moodle-503/public`)

Tables and indexes (`lib/db/install.xml`):

| Table | What it holds | Indexes |
|---|---|---|
| `competency_usercomp` | Site-wide rating: `userid`, `competencyid`, `proficiency` (NULL/0/1), `grade`, `status` | unique `(userid, competencyid)` |
| `competency_usercompcourse` | Rating in a course: `userid`, `courseid`, `competencyid`, `proficiency`, `grade` | unique `(userid, courseid, competencyid)` |
| `competency_usercompplan` | Rating frozen when a plan is completed | unique `(userid, competencyid, planid)` |
| `competency_plan` | `userid`, `templateid`, `origtemplateid`, `status`, `duedate` | `(userid, status)`, `(templateid)`, `(status, duedate)` |
| `competency_template` | `contextid` (system or category), `visible` | FK `contextid` |
| `competency_framework` | `contextid` (system or category), `visible` | FK `contextid`, unique `idnumber` |
| `competency_templatecohort` | Cohorts whose members get a plan from the template | unique `(templateid, cohortid)` |

- **Every index on a rating table leads with `userid`.** Per-user lookups are index probes; "all
  users with competency C" has no index and reads the table once. A plugin cannot add an index to
  a core table.
- **Every rating goes through `api::add_evidence()`** (`competency/classes/api.php:4285`): manual
  rating (`grade_competency*()` call it at :4872, :4922, :4980), course and activity completion
  rules, rule propagation to parents. It writes `usercomp` and, in a course or module context,
  `usercompcourse`, and fires **`competency_evidence_created`** (:4503) with `relateduserid` = the
  rated user and `other` = `usercompetencyid`, `competencyid`, `action`. Only `ACTION_COMPLETE` (2)
  and `ACTION_OVERRIDE` (3) can change a proficiency; `ACTION_LOG` (0) cannot. The `*_rated*`
  events fire only for manual ratings, so they are not enough on their own.
- **A competency with any rating cannot be deleted** (`competency::can_all_be_deleted()`
  refuses when `usercomp`, `usercompplan`, `plancomp`, `templatecomp` or course links exist), so
  "deleted referent" only happens to unrated competencies.
- Plan statuses (`competency/classes/plan.php`): DRAFT 0, ACTIVE 1, COMPLETE 2,
  WAITING_FOR_REVIEW 3, IN_REVIEW 4. Status changes only through the dedicated API calls, each
  with its own event; `update_plan()` refuses a status or template change (:2749, :2757).
  `complete_plan()` archives the ratings into `usercompplan`; `reopen_plan()` removes them.
  `complete_plans_task` completes overdue plans from cron; `sync_plans_from_template_cohorts_task`
  creates plans for the members of a template's cohorts and **never deletes** one when a member
  leaves.
- `delete_template($id, $deleteplans)` deletes the template's plans or unlinks them (`templateid`
  NULL, `origtemplateid` kept, event `competency_plan_unlinked`).
- **Every plan event names the plan owner as its related user.** `competency_plan_created`,
  `_deleted`, `_updated` and `_viewed` pass none (`lib/classes/event/competency_plan_*.php`), but
  `\core\event\base::create()` fills `relateduserid` from a user context when none is passed
  (`lib/classes/event/base.php:237`), and a plan's context is its owner's. Corrected in S1: this
  plan first read the four `create_from_plan()` methods only and concluded the opposite; the S1
  test of a plan created by another user proved the owner is processed.

In this plugin (`main`, 2026100503):

- **Latent defect: an event with neither user processes the whole rule inside the request that
  fired it.** `get_userid_from_event()` returns 0, `process_rule($rule, 0)` treats 0 as "no user"
  (`get_matching_users()` and `build_sql_data()` test `if ($userid)`), so every user is matched and
  the cohort rewritten in the triggering request. Reachable today by any subscribed event fired by
  a CLI script with no user set that carries no related user.
- `build_sql_data()` concatenates every condition's JOIN and combines only the WHERE fragments with
  the rule's operator (repo `CLAUDE.md`: joins must be neutral). `user_enrolment` already filters
  with `EXISTS` / `NOT EXISTS` in WHERE; `cohort_membership` with a LEFT JOIN on a derived table
  and `IS [NOT] NULL` in WHERE. Both shapes are neutral.

## Learnings carried over from availability_competency

From `~/dev/moodle-availability_competency` (`classes/condition.php`, `CLAUDE.md`, CHANGELOG 1.2.0):

1. **Read the rating tables directly, never `\core_competency\api` getters.** They check the current
   user's capabilities and throw (cron and observers run without the right user), throw when
   competencies are disabled, and `get_user_competency_in_course()` inserts a record on read. The
   same rule already binds descriptions here ("descriptions must not call capability-aware core
   APIs").
2. **Two sources, chosen per condition**: site-wide (`usercomp`) or in a course (`usercompcourse`).
   Proficiency in a course and site-wide differ; both are wanted.
3. **Stored ratings keep counting** after the competency is unlinked from a course, the course
   moves, or competencies are disabled site-wide: these stop new ratings only.
4. **`competency_evidence_created` is the one event that sees every rating change**; refreshing on it
   follows ssystems' availability_competencies.
5. **A competency picker must not list everything**: a site can hold thousands of competencies.

## Measured: candidate SQL shapes

Probe on the m503 database, PostgreSQL 18.6, real `m_user` (103,128 rows) against temporary tables
shaped like `competency_usercomp` (1,650,301 rows, 20 competencies, unique `(userid, competencyid)`)
and `competency_plan` (123,571 rows, indexes as core). Script: `EXPLAIN (ANALYZE)` of each shape
wrapped as `SELECT DISTINCT u.id FROM {user} u ... WHERE (...) AND u.deleted = 0`, as
`get_matching_users()` builds it.

| Shape | Bulk (every user) | One user |
|---|---|---|
| ANY of 3 proficient: `EXISTS (... competencyid IN (...) AND proficiency = 1)` | 115 ms, hash semi-join, one scan | index probe |
| ALL of 3: LEFT JOIN derived `GROUP BY userid HAVING COUNT(*) = 3`, `IS NOT NULL` | 115 ms, one scan | **0.06 ms** (predicate pushed into the derived table) |
| ALL of 3: three `EXISTS` | 163 ms, grows with N | index probes |
| ALL of 3: correlated `(SELECT COUNT(*) ...) = 3` | **324 ms**, one subplan per user | 0.02 ms |
| NOT proficient in any of 3: `NOT EXISTS` | 112 ms, hash anti-join | index probe |
| Plan from templates (2, 5), status complete: `EXISTS` | 13 ms, bitmap on `templateid` | index probe |
| Competency ANY `OR` plan (rule operator OR) | 181 ms, two hashed subplans | index probes |

First conclusions (PostgreSQL only): `EXISTS` / `NOT EXISTS` for "any", a grouped derived table
for "all". Stage S0 overturned the second.

### S0: both database families (2026-10-27)

The same shapes as a throwaway PHPUnit test, run through `mdl ci --only phpunit` on the 5.3 branch
with PHP 8.4 (Mac mini), on temporary tables: 100,000 users, ~1.6 M ratings, ~120,000 plans. Best
of three runs, in milliseconds; "one user" adds `u.id = :id`, as the observer path does. Every
derived-table and `OR` shape was also measured negated (`IS NULL`) and under OR, because a
condition cannot know which of those the rule will put it in.

| Shape | PostgreSQL 18.6 bulk | one user | MariaDB 12.3 bulk | one user |
|---|---|---|---|---|
| ANY: `EXISTS` | 90 | 0.09 | 137 | 0.11 |
| NONE: `NOT EXISTS` | 79 | 0.09 | 111 | 0.07 |
| ALL: derived, `IS NOT NULL` | 75 | 0.09 | 123 | 0.08 |
| NOT ALL: derived, `IS NULL` | 93 | 0.11 | 137 | **107** |
| ALL derived `OR` plan `EXISTS` | 86 | 0.12 | 140 | **108** |
| ALL: correlated `COUNT(...) = n` | 130 | 0.08 | 177 | 0.06 |
| Plan `EXISTS` | 8 | 0.07 | 22 | 0.11 |
| ANY `OR` plan | 120 | 0.11 | 131 | 0.09 |

**MariaDB only pushes the user predicate into the grouped derived table when it can turn the LEFT
JOIN into an inner join** (`IS NOT NULL` under AND). Negated, or under OR, it materialises the whole
derived table (2.25 M index entries read) for every single-user evaluation: ~107 ms on each
relevant evaluation in the observer, where every other shape costs an index probe. PostgreSQL pushes
it down in every case.

**Decision: "all" uses the correlated count**, `(SELECT COUNT(DISTINCT a.competencyid) ...
WHERE a.userid = u.id ...) = :n` (or `<> :n`), in WHERE with no JOIN. It costs an index probe for
one user on both families whatever the operator and negation, and 130-177 ms in bulk instead of
75-137 ms: the bulk path runs once per rule per scheduled run, the one-user path once per rating.
"Any" keeps `EXISTS` / `NOT EXISTS`, which probe the `(userid, competencyid)` index on MariaDB
(`FirstMatch`, `index_subquery`) and hash on PostgreSQL.

## Design

### Condition A: `competency_proficiency`

Config (`configdata`):

| Key | Values |
|---|---|
| `competencyids` | 1..50 competency ids (the cap bounds the `IN` list and the description) |
| `match` | `any` / `all` |
| `state` | `proficient` / `notproficient` |
| `source` | `global` (`usercomp`), `course` (`usercompcourse` for `courseid`), `anycourse` (`usercompcourse`, any course; under the scope when scoped) |
| `courseid` | for `source = course` |

Semantics: "not proficient" is the negation of "proficient" with the same match, so no row, a
NULL proficiency and `proficiency = 0` all count as not proficient (as in availability_competency).
`notproficient + any` = lacks at least one; `notproficient + all` = lacks every one.

SQL (aliases and parameters from `condition_sql::generate_*()`, never literal):

```sql
-- proficient + any  /  notproficient + all
[NOT] EXISTS (SELECT 1 FROM {competency_usercomp} a
               WHERE a.userid = u.id AND a.competencyid IN (:c...) AND a.proficiency = 1)

-- proficient + all  /  notproficient + any   (no JOIN; see "S0: both database families")
(SELECT COUNT(DISTINCT a.competencyid) FROM {competency_usercomp} a
  WHERE a.userid = u.id AND a.competencyid IN (:c...) AND a.proficiency = 1) [= | <>] :n
```

`COUNT(DISTINCT)` because the `anycourse` source can hold the same competency rated in several
courses. Source `course` adds `a.courseid = :courseid`; `anycourse` under a scope adds a semi-join
on the course context path.

- **Broken** when competencies are configured and any of them no longer exists (one `IN` query,
  memoised on the instance: `build_sql_data()` and `get_sql()` both call `is_broken()`), when the
  course of `source = course` is gone, and when outside the recorded scope (Q5). The broken
  description names what is missing, says that the rule stopped and kept its members, and that
  removing the competency from the condition (or choosing another) resumes it.
- **Not offered while competencies are disabled** (`get_config('core_competency', 'enabled')`):
  `is_broken()` returns true only for the unconfigured instance, the trick `course_completed` uses,
  so `get_all_conditions(true)` drops it from the list while saved conditions keep evaluating
  stored ratings (learning 3).
- **Events**: `\core\event\competency_evidence_created`. Relevance (F2): `other['action']` is not
  `ACTION_LOG` and `other['competencyid']` is configured. A course completion that logs evidence on
  fifteen linked competencies then processes the rule once per relevant rating, not fifteen times.
- **Description** from the tables: competency `shortname` with its framework `shortname`,
  `format_string()` escaped (triple-stash sink), course `fullname` as `course_completed` reads it.
- **Picker**: an autocomplete over competencies labelled "Framework › Competency (idnumber)",
  limited to the frameworks usable from the scope (unscoped: every framework). See Q3 for static
  options versus an AJAX search.

### Condition B: `learning_plan`

Config:

| Key | Values |
|---|---|
| `operator` | `has` / `hasnot` |
| `templateids` | 1..50 template ids, required: only plans created from a template count, never individual plans (Q2) |
| `includeunlinked` | also plans unlinked from those templates (`origtemplateid`) |
| `statuses` | subset of draft, waiting for review, in review, active, complete; empty = any |

```sql
[NOT] EXISTS (SELECT 1 FROM {competency_plan} p
               WHERE p.userid = u.id
                 AND (p.templateid IN (:t...) [OR p.origtemplateid IN (:t...)])
                 AND p.status IN (:s...))
```

- **Self-reference guard.** A template whose `competency_templatecohort` lists the rule's own cohort
  latches membership: the sync task gives every new member a plan and never removes it, so with
  `has` nobody ever leaves, and with `hasnot` nobody who joined can come back. Same shape as
  `cohort_membership::is_using_rule_cohort()`: broken (Q4). The broken description explains the
  latch (the sync task gives a plan to every member and never takes it back) and the fix (remove
  the cohort from the template, or pick another template).
- **Broken** when a configured template is gone (with `deleteplans = false` its plans survive as
  unlinked; the description says so), and when outside the recorded scope.
- **Events**: the twelve plan events that change existence, template link or status:
  `competency_plan_created`, `_deleted`, `_completed`, `_reopened`, `_approved`, `_unapproved`,
  `_review_requested`, `_review_request_cancelled`, `_review_started`, `_review_stopped`,
  `_unlinked`, and `_updated` only if an overdue state is added (Q6). User: the related user, which
  core fills in with the owner;
  relevance from the plan snapshot's template (F2), true when there is no snapshot.

### Framework changes (no behaviour change for the twelve conditions)

- **F0, the latent defect.** The observer never calls `process_rule()` with a user id below 1; an
  event that names nobody is left to the scheduled run. One test fires an event with no user and
  asserts the cohort of a rule matching two other users is unchanged.
- **F1, dropped in S1.** A per-condition `get_event_userid()` was planned for plan events thought
  to name only the actor; core names the owner (verified facts), so the observer keeps one user per
  event. A test pins the behaviour the plan condition relies on.
- **F2, `condition_base::is_relevant_event(base $event): bool`, default true.** Called on the
  rule's configured condition instances of the subscribing class (rules and condition records come
  from the existing caches). The rule is processed when any of them is relevant. Sound because an
  event that no subscribed condition of a rule finds relevant cannot change that rule's result.
  A class that keeps the default is answered without building its instances (one reflection per
  class and process), so the twelve existing conditions cost nothing more. The same change
  processes a rule **once per event**: before it, a rule holding two classes subscribed to the same
  event (`user_profile` and `user_profile_interests` both take `user_updated`) was evaluated twice.
- **Generalised scope helper.** `scoped_cohorts_sql()` becomes a wrapper over
  `scoped_contexts_sql(string $contextcol, string $visiblecol, context $scope)`, reused for
  `{competency_framework}` and `{competency_template}`: the scope and its subcategories whatever the
  visibility, plus visible items of the parent contexts (core's own rule for cohorts and frameworks).

### Category scope

| | Competency | Plan |
|---|---|---|
| Referent picker | Competencies of frameworks usable from the scope (`scoped_contexts_sql`) | Templates usable from the scope |
| Course source | `course`: course under the scope; `anycourse`: courses under the scope only | n/a |
| Global source | Q1 | n/a |
| `rechecks_scope_when_processed()` | true: a framework's context can change | true: a template's context can change |

Both return `supports_category_scope() = true`. As built-ins they are on for category users while
the `categoryconditions` setting has never been saved (D3); a site that saved it must tick them.

## Brainstorm: rejected or deferred

- **Rejected: an own table of proficiencies kept by the observer.** Duplicates core state, drifts
  on any missed event, makes the plugin a personal-data plugin (privacy provider, compliance test),
  and buys little: one scan of 1.65 M rows took 115 ms.
- **Rejected: going through `\core_competency\api`** (learning 1).
- **Rejected: one combined condition.** Different referents, events and broken cases; two
  conditions compose under AND/OR.
- **Rejected: N-way EXISTS for "all"** (grows with N), and **the grouped derived table** (a full
  materialisation per one-user evaluation on MariaDB when negated or under OR; measured above).
- **Deferred: grade threshold** ("rated at least *Good*"): scales differ per competency, so only for
  a single competency or one framework scale.
- **Deferred: plan progress** ("every competency of the plan proficient"): a double `NOT EXISTS`
  over `templatecomp`/`plancomp` and `usercomp`; probes are indexed, but it needs its own
  measurement.
- **Deferred: frozen plan ratings** (`usercompplan`, "proficient when plan X was completed") and
  **evidence-based conditions** (`competency_evidence`, "evidence since a date").
- **Deferred: overdue plans** (active with a past `duedate`): time-relative, so real time cannot see
  it become true; only the scheduled run would (Q6).

## Owner decisions (2026-10-27)

| # | Question | Decision |
|---|---|---|
| Q1 | Site-wide proficiency for a category user | **As recommended**: only for competencies whose framework lives in the scope or below; frameworks from parent contexts offer the course sources under the scope only. |
| Q2 | Which plans count | **Only plans created from a template**, for every author: the template list is required and individual plans never match. Templates offered from the scope helper. |
| Q3 | Competency picker | **As recommended**: AJAX search through `tool_dynamic_cohorts_search_competencies`. |
| Q4 | Template synced from the rule's cohort | **Broken, with an explanatory description** (cause and fix). |
| Q5 | Competency deleted | **Broken, with an explanatory description** (what is missing, rule stopped with its members kept, how to resume). |
| Q6 | Overdue plans in the first version | **As recommended**: no. |

The questions as they were put, with the recommendations:

| # | Question | Recommendation |
|---|---|---|
| Q1 | May a category user read **site-wide** proficiency? It is academic data earned anywhere on the site. | Only for competencies whose framework lives in the scope or below; frameworks from parent contexts offer the course sources under the scope only. Alternative: allow it as D2 does for profile data, with the `categoryconditions` switch as the control. |
| Q2 | May a category user match **any plan** (no template), or templates from parent contexts? | Templates usable from the scope only (same helper); "any plan" unscoped only. |
| Q3 | Competency picker: static autocomplete options, or an AJAX search through a new web service? | AJAX through `tool_dynamic_cohorts_search_competencies` (scope-aware, fleet WS checklist), because static options render every competency of every usable framework into the modal. Static is enough if the sites stay under ~2,000 competencies. |
| Q4 | Template synced from the rule's own cohort: broken, or only a warning? | Broken, as `cohort_membership` does with its own cohort. |
| Q5 | Competency deleted: broken, or "counts as not proficient" (availability_competency)? | Broken: the repo's rule is to stop and keep the members when a referent disappears; and core only lets unrated competencies be deleted. |
| Q6 | Include overdue plans in the first version? | No; add with `_updated` subscribed if wanted. |

## Stages

Each stage is one pull request on `main` (5.3) with its version bump and `CHANGELOG.md` entry, then
cherry-picked to `MOODLE_502_STABLE` and `MOODLE_501_STABLE` (the competency API is the same on all
three).

| Stage | Scope | Done when |
|---|---|---|
| S0 | F0 with its test; the probe above repeated on MariaDB 12.3 (a throwaway PHPUnit test with `EXPLAIN`/timings, through `mdl ci --db mariadb`). | **Done 2026-10-27**: F0 test reddens with the guard removed; numbers above; "all" moved to the correlated count. |
| S1 | F2 and once-per-event processing, `scoped_contexts_sql()`; F1 dropped. | **Done 2026-10-28**: existing suite green unchanged; fixture conditions drive the observer (irrelevant event, two classes, plan owner); gates BO-BR and AS swept on both families. |
| S2 | `competency_proficiency`, all sources, unscoped; picker per Q3. | **Done 2026-10-28**: condition, `tool_dynamic_cohorts_search_competencies` and `competency_selector` AMD; gates BS-CC swept on both families; version 2026100505. Not offered in categories until S4; the modal's autocomplete is not exercised by Behat until S4. |
| S3 | `learning_plan`, unscoped, self-reference guard. | Condition tests below. |
| S4 | Category scope for both (Q1, Q2), README (conditions, what they read), Behat smoke (add one competency condition through the modal). | Scope refusal tested at save, not only in the modal; the search's scoped branch (`scoped_contexts_sql` on frameworks, dead until then) tested from a category. |

## Tests

Pattern of `tests/local/tool_dynamic_cohorts/condition/`: build through `get_instance()`, set the
config, run `get_sql()` through `$DB` on both drivers. Data through core's competency generator
(`core_competency`'s `create_framework`, `create_competency`, `create_user_competency`,
`create_user_competency_course`, `create_template`, `create_plan`).

- Each of the four state × match combinations against users with no row, NULL, 0 and 1, and a user
  proficient in only some of the competencies; each source; the same competency rated in two
  courses under `anycourse` + `all` (the `COUNT(DISTINCT)` guard).
- Under rule operator OR with another condition: a user matching only the other condition is still
  matched (neutral JOIN guard).
- One-user processing equals the bulk result for that user.
- Broken: missing competency, missing course, missing template, template synced from the rule's
  cohort, framework or template moved out of the recorded scope.
- Observer: a rating raised through `api::add_evidence()` in a real-time rule adds the user; an
  `ACTION_LOG` evidence or another competency does not process the rule (F2, with the control that
  a relevant one does); a plan created by another user processes the owner, not the actor (pinned
  in S1).
- Description: names with `&` and `<` escaped once.
- Mutation-check every guard (`mdl mutate --fast`, pgsql and MariaDB): the relevance filter, the
  self-reference guard, `COUNT(DISTINCT)`, the scope helper, F0.

## Gates

- `mdl ci moodle-tool_dynamic_cohorts-503 --matrix --behat` on each head before merge, then on each
  backport branch; `[skip ci]` on every commit.
- `security-diff-read` over S1 (observer hot path), S4 (scope) and the web service of Q3.
- MDL Shield `review full` on S4 if the owner releases credits.
