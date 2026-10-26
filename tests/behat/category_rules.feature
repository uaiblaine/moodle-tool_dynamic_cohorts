@tool @tool_dynamic_cohorts
Feature: Manage dynamic cohort rules in a course category
  In order to keep the cohorts of my category in step with user data
  As a category manager
  I need to reach my category's rules page and see only its rules

  # A role in the category holds both capabilities rule_access requires; nothing at the
  # system level. Access refusals are PHPUnit's job (tests/local/rules_page_test.php).

  Background:
    Given the following "categories" exist:
      | name       | category | idnumber |
      | Category A | 0        | CATA     |
      | Category B | 0        | CATB     |
    And the following "cohorts" exist:
      | name     | idnumber | contextlevel | reference |
      | Cohort A | COHA     | Category     | CATA      |
      | Cohort B | COHB     | Category     | CATB      |
    And the following "tool_dynamic_cohorts > rules" exist:
      | name        | cohort |
      | Rule for A  | COHA   |
      | Rule for B  | COHB   |
    And the following "users" exist:
      | username | firstname | lastname |
      | catman   | Cat       | Manager  |
    And the following "roles" exist:
      | shortname   | name         |
      | rulemanager | Rule manager |
    And the following "permission overrides" exist:
      | capability                  | permission | role        | contextlevel | reference |
      | tool/dynamic_cohorts:manage | Allow      | rulemanager | Category     | CATA      |
      | moodle/cohort:assign        | Allow      | rulemanager | Category     | CATA      |
    And the following "role assigns" exist:
      | user   | role        | contextlevel | reference |
      | catman | rulemanager | Category     | CATA      |

  Scenario: A category manager reaches the rules page from the category and sees only its rules
    Given I am on the "CATA" "Category" page logged in as "catman"
    When I navigate to "Dynamic cohort rules" in current page administration
    Then I should see "Manage rules"
    And I should see "Rule for A"
    And I should not see "Rule for B"
