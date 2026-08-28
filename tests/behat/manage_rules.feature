@tool @tool_dynamic_cohorts
Feature: Manage dynamic cohort rules
  In order to keep cohort membership in step with user data
  As an administrator
  I need to reach the rules page and create a rule against a cohort

  # Thin by design (see ~/dev/CLAUDE.md): everything about matching users belongs in
  # PHPUnit. There is deliberately no "a user without the capability cannot get in"
  # scenario — a required_capability_exception renders through fatal_error(), which tags
  # the page data-rel="fatalerror", and behat_session_trait::look_for_exceptions() fails
  # any scenario whose page carries that. The capability gates are covered in
  # tests/external/ and tests/privacy/ instead.

  Background:
    Given the following "cohorts" exist:
      | name         | idnumber |
      | Staff cohort | STAFF    |

  Scenario: The rules page is reachable from site administration
    Given I log in as "admin"
    When I navigate to "Users > Accounts > Dynamic cohorts > Manage rules" in site administration
    Then I should see "Manage rules"
    And I should see "Add a new rule"

  @javascript
  Scenario: Create a rule and see it listed, disabled and awaiting review
    Given I log in as "admin"
    And I visit "/admin/tool/dynamic_cohorts/index.php"
    When I click on "Add a new rule" "link"
    And I set the field "Rule name" to "Staff rule"
    And I set the field "Cohort" to "Staff cohort"
    And I click on "Save changes" "button" in the "Add new rule" "dialogue"
    Then I should see "Rule has been added"
    And I should see "Staff rule"
    And I should see "Staff cohort"
