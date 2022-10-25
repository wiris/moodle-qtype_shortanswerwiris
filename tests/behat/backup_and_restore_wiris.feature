@qtype @qtype_wq @qtype_shortanswerwiris
Feature: Test duplicating a quiz containing a Short Answer Wiris question
  As a teacher
  In order re-use my courses containing Short Answer Wiris questions
  I need to be able to backup and restore them

  Background:
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype            | name                  | template     |
      | Test questions   | shortanswerwiris | Short answer question | algorithmsaw |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |
    And quiz "Test quiz" contains the following questions:
      | Short answer question | 1 |

  @javascript
  Scenario: Backup and restore a course containing a Short Answer Wiris question
    When I am on the "Course 1" course page logged in as admin
    And I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name       | Course 2 |
      | Schema | Course short name | C2       |
    And I am on the "Course 2" "core_question > course question bank" page
    And I choose "Edit question" action for "Short answer question" in the question bank
    Then the following fields match these values:
      | Question name    | Short answer question                         |
      | Question text    | Just write x + #a:                            |
      | General feedback | Generalfeedback: You should have said x + #a. |
    And I open Wiris Quizzes Studio
    And I should see "math"

  @javascript
  Scenario: A teacher executes a restored shortanswer wiris question formulas
    When I am on the "Short answer question" "core_question > preview" page logged in as teacher
    Then I should see "Just write"
    And I wait "10" seconds
    And I click on "Fill in correct responses" "button"
    And I click on "Submit and finish" "button"
    Then Generalfeedback should exist
    And Wirisformula should exist
