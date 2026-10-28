<a href="https://github.com/uaiblaine/moodle-tool_dynamic_cohorts/actions/workflows/ci.yml?query=branch%3AMOODLE_502_STABLE">
<img src="https://github.com/uaiblaine/moodle-tool_dynamic_cohorts/workflows/ci/badge.svg">
</a>

# Dynamic cohorts #

Dynamic cohorts plugin allows dynamically add and remove users from cohorts based on configured rules, which themselves are composed of conditions.

# Versions and branches

This is a fork of [catalyst/moodle-tool_dynamic_cohorts](https://github.com/catalyst/moodle-tool_dynamic_cohorts),
maintained against the Moodle versions this site runs.

| Moodle version | Branch                                |
|----------------|---------------------------------------|
| Moodle 5.3     | `main` (same commit as `MOODLE_503_STABLE`) |
| Moodle 5.2     | `MOODLE_502_STABLE`                   |
| Moodle 5.1     | `MOODLE_501_STABLE`                   |

Each branch declares only its own Moodle version in `$plugin->supported`, so a branch
refuses to install on any other version.

**Upgrading from the single-branch fork** (`$plugin->version = 2026082801`, Moodle 5.1 and
5.2 only): the 5.2 and 5.1 branches now number their releases in their own Moodle version's
range, which is below that number, and Moodle refuses a lower number as a downgrade. Replace
the plugin files first, then set the stored version just below the branch's first release and
upgrade:

| Branch              | `--set=`     |
|---------------------|--------------|
| `MOODLE_502_STABLE` | `2026041999` |
| `MOODLE_501_STABLE` | `2025100599` |

    php admin/cli/cfg.php --component=tool_dynamic_cohorts --name=version --set=<value above>
    php admin/cli/upgrade.php

For Moodle 4.1 - 4.5, use the corresponding branch of the upstream
repository — this fork does not carry them.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/dynamic_cohorts

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

# Similar plugins
There are similar plugins that can add users to cohorts dynamically based on user profile fields.

* Profile field based cohort membership https://moodle.org/plugins/local_profilecohort
* Smart Cohort https://moodle.org/plugins/local_cnw_smartcohort
* Auto-cohort plugin https://moodle.org/plugins/local_cohortauto

However, "Dynamic cohorts" plugin gets this all to the next level as it provides an API for other plugins to implement pretty much any condition that can limit users from mdl_user table by SQL.

# How does it work?

There are two concepts, rules and conditions.

## Conditions

Conditions are simple predicates which assert something about a user in the system. Any plugin may specify a condition. As an example conditions related to user profile fields. These can be used to match users based on the value of a profile field.

### Built-in conditions

* Authentication method (Manual, SAML and etc)
* Cohort fields (if a user is a member of cohort(s) matching specific cohort fields like cohort name, context, custom fields and etc)
* Cohort membership (if a user is a member of cohort(s)).
* Competency proficiency (if a user is, or is not, proficient in at least one or all of up to 50
  competencies). It reads the site-wide rating, the rating given in one course, or the rating given
  in any course, straight from core's competency tables, so a rating keeps counting after its
  competency is unlinked from the course or competencies are disabled. A rating given in a course
  reaches the site-wide rating only when the course pushes ratings to learning plans (core's
  default). It is processed in real time when a rating of one of its competencies changes. It is
  not offered while competencies are disabled; rules already using it keep working.
* Learning plan (if a user has, or has not, a learning plan created from given templates, in given
  statuses, optionally counting plans since unlinked from the template). Individual plans, which
  have no template, never count. A template whose cohort sync fills the rule's own cohort stops
  the rule, because core never removes a plan when a member leaves. Processed in real time when a
  plan is created, deleted, changes status or is unlinked. Not offered while competencies are
  disabled; rules already using it keep working.
* Course completed (if a user has completed a course).
* Course not completed (if a user has not completed a course).
* User created time (time since a user was created).
* User custom profile fields (text, menu, date and autocomplete types are supported).
* User enrolment (if a user is enrolled into a course).
* User last login (time since a user last logged in).
* User role (if a user has a role in a given context)
* User standard profile fields (e.g. first name, last name, username, auth method and etc).
* User interests (if a user has a specific interest tagged on their profile).

## Rules

Rules are what determine if a user will be added or removed from a cohort. A rule is defined by few things:

1. A cohort
2. A set of conditions
3. A logical operator to be applied for conditions (OR/AND)

For users to be added to the cohort specified by a rule, they must match all of rule's conditions (logical operator AND) or any of rule's conditions (logical operator OR). 

**NB:** A cohort can be managed by _one and only one_ rule. This is to prevent rules competing over users in a cohort (e.g., to avoid situations where Rule A wants users a, b, c to be in a cohort, but Rule B wants to remove user c from the same cohort). 

Rules can be processed by two mechanisms:

1. By cron: When a rule is created or updated, there may be many users that need to be added or removed from a cohort. This process is handled by cron, and depending on how many users are matched by a rule, this process can take some time. For rules matching large sets of users, some [configuration options](#rule-processing-options) are provided which may be useful to server administrators.
2. By event: rules may also listen to certain events. When one of these events triggers, appropriate rules will be checked and the user will be added to the appropriate cohort immediately. For example, the User standard profile field rule listens to the "User created" and "User updated" events.

### Disabling realtime processing (processing on event)

Each rule can be configured to be processed realtime (if any of the related conditions support processing on event).

There is also a global admin setting that allows administrator to enable or disable realtime rule processing globally overriding per rule configuration. 

# Configuration

## Prerequisites
1. At least one manually created cohort (See _Site administration > Users > Cohorts_)

## Creating a rule
1. Navigate to _Site administration > Users > Accounts > Dynamic cohorts > Manage rules_ (or, for
   the cohorts of one course category, to that category's rules page: see
   [Managing rules in a course category](#managing-rules-in-a-course-category))
2. Press the "Add a new rule" button
3. From this form you can specify the cohort users will be added to, and add any conditions available in your system. As metioned above, a user must match **all** conditions to be added to the cohort
4. Press the "Save changes" button

You will be directed to the manage rules page where you can review your rule before enabling it by clicking the eye.

**Note:** It is important to review your new rule to make sure it is configured properly. Consider carefully how many users are affected by the rule (displayed in the table) before enabling it. For rules operating on large sets of users see [rule processing options](#rule-processing-options)

Any subsequent edits to a rule will disable it and require you to review and re-enable it.

## Managing rules in a course category

Rules can be managed by someone who is not a site administrator, for the cohorts of one course
category and its subcategories only. A rule belongs to the context of the cohort it fills, so a role
held in a category reaches the rules of that category's cohorts and nothing else.

### Granting it

1. Create a role (_Site administration > Users > Permissions > Define roles_), or use an existing
   category-level one such as Manager, and allow, in that role:
    - `tool/dynamic_cohorts:manage`, and
    - `moodle/cohort:assign` (a rule changes the cohort's membership, so both are required; the
      core Manager role already has it).
2. Make the role assignable at the _Category_ context level.
3. Assign it to the user in the course category (_the category > More > Assign roles_).

The user then finds **Dynamic cohort rules** in the category's _More_ menu. The page lists the
rules whose cohort lives in the category or below it. A rule in a subcategory where either
capability is prohibited is listed without its actions. Cohorts themselves are created and edited
as usual (_the category > More > Cohorts_, which needs `moodle/cohort:manage`).

### What a category-level user can do

- Pick only cohorts of the category (or its subcategories) that they may assign to.
- Use only the conditions enabled in the setting _Conditions available in course categories_
  (_Site administration > Users > Accounts > Dynamic cohorts > Settings_). Every built-in condition
  is enabled by default; conditions from other plugins stay off until they support categories. A
  site that saved this setting before the competency and learning plan conditions existed has to
  tick them there.
- Refer only to what lies in the category. Course conditions take its courses. The role condition
  takes the category or one of its courses, never the system level. Cohort conditions take the
  category's cohorts and the visible cohorts above it. Profile conditions offer identity and
  hidden fields only where core would show them to that user. Competency conditions take
  competencies of the category's frameworks and of the visible frameworks above it; the site-wide
  rating only for the category's own frameworks, and "any course" counts only the category's
  courses. Learning plan conditions take the category's templates and the visible templates above
  it; a template from above matches its plans whichever category their owners belong to. A rule
  cannot be moved to another cohort while one of its conditions reaches outside that cohort's
  category.
- Not save a rule that holds a condition reaching outside the category (for example, one an
  administrator wrote). They have to remove that condition first. Enabling, disabling and
  deleting the rule still work.

The rules report's Context column shows where each rule was written: the system, or the category
its cohort lives in. On the site page, a Context filter narrows the list to one of them.

A site administrator, or anyone holding both capabilities in the system context, is not limited by
any of this and keeps managing every rule from site administration.

**Note:** the matching-users list still shows each matched user's username, email address and ID
number, as it does for administrators. Grant the role only to people who may see those details for
the users your rules select.

## Rule processing options
Rules are processed regularly by cron; by default cron will add each user to the specified cohort one by one. For large sets of users, this can take a long time and potentially block other cron tasks.

To mitigate this, an option is provided to process "in bulk" instead of adding them to a cohort one at a time. To enable it for a rule:

1. Go to the rule edit table 
2. Check the "Bulk processing" checkbox
3. Save the rule and review it before enabling

### Bulk processing and events
By default, bulk processing does not trigger the `\core\event\cohort_member_added` and
`\core\event\cohort_member_removed` events, because an event per membership change adds load
on large rules. Plugins that observe those events (cohort enrolment sync among them) therefore
do not react to bulk changes.

The site setting _Trigger events during bulk processing_ (`tool_dynamic_cohorts/bulkprocessingevents`)
triggers them for every user bulk processing adds or removes, at the cost of part of the
performance gain.

# Technical details

## Condition implementation

### Quick start

Any plugin can easily implement a condition by adding classes in the `\local\tool_dynamic_cohorts\condition` namespace. Each condition must extend the [base_condition class](classes/condition_base.php). As an example, the itself provides some conditions; the directory structure is as follows:

```
tool_dynamic_cohorts
└── classes
    └── local
       └── tool_dynamic_cohorts
           └── condition
               ├── auth_method.php
               ├── cohort_membership.php
               ├── user_custom_profile.php
               └── user_profile.php
```

See [cohort_membership.php](classes/local/tool_dynamic_cohorts/condition/cohort_membership.php) for an example implementation of a single rule. Any plugin wishing to add a rule must simply add similar class inside its own directory. tool_dynamic_cohorts will automatically discover new rules.  

### Triggering on event

Any condition can specify a list of events to listen to by overriding the `get_events` method. Simply return a list of events. See [user_custom_profile.php](classes/local/tool_dynamic_cohorts/condition/user_custom_profile.php) for an example.

## Task processing
Task processing is orchestrated by a simple mechanism:

1. A scheduled task runs periodically which gets all enabled rules, then queues an ad-hoc task to process the rule (i.e., one ad-hoc task per rule)
2. The ad-hoc task will either:
   a) Iterate over all users, adding them to the cohort one at a time (via the cohort API)
   b) If bulk processing is enabled, users will be inserted directly in to the DB in batches, bypassing the core API


# Requirements

* Moodle 5.2 on this branch (`MOODLE_502_STABLE`); Moodle 5.3 on `main` and Moodle 5.1 on `MOODLE_501_STABLE`.
* At least one manually created cohort that no other plugin manages.

# Capabilities

`tool/dynamic_cohorts:manage` (system or course category context) — create, edit, enable, disable
and delete rules, and view the list of users a rule matches. Each rule is decided in the context of
the cohort it fills, and it also needs `moodle/cohort:assign` there (see
[Managing rules in a course category](#managing-rules-in-a-course-category)). It carries
`RISK_PERSONAL`: the matching users report lists every matched user's username, email address and
ID number, and can be downloaded. No role holds it by default, so it is admin-only until granted
explicitly.

# Privacy

The plugin stores no data about the users a rule matches. It records only which user
created or last updated each rule and each condition (the `usermodified` column on both of
its tables), and the privacy provider exports and deletes that on request.

## Warm thanks ##

Plugin funded by Murdoch University, Perth, Western Australia (https://www.murdoch.edu.au/) 

## License ##

2024 Catalyst IT

Fork maintained by Anderson Blaine. Changes made in this fork are listed in
[CHANGELOG.md](CHANGELOG.md).

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
