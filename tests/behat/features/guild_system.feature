@api @avc_guild
Feature: Guild System
  As a member of AV Commons
  I want to participate in guilds with mentorship and scoring
  So that I can develop skills and contribute to the community

  Background:
    Given I am logged in as a user with the "authenticated" role
    And a guild group type exists with the following roles:
      | junior   |
      | endorsed |
      | mentor   |
      | admin    |

  Scenario: Create a guild group
    Given I am logged in as a user with the "administrator" role
    When I create a group of type "guild" named "Translation Guild"
    Then the group "Translation Guild" should exist
    And the group type should be "guild"

  Scenario: Guild has skills assigned
    Given a guild "Editorial Guild" exists
    When I configure the guild with the following skills:
      | Technical Writing/Editing |
      | Pedagogy                 |
      | Theology                 |
    Then the guild "Editorial Guild" should have 3 skills

  Scenario: New member joins guild as junior
    Given a guild "Translation Guild" exists
    When I join the guild "Translation Guild"
    Then I should be a member of "Translation Guild"
    And my guild role should be "junior"

  Scenario: Junior member needs ratification for completed work
    Given I am a member of a guild "Translation Guild" with role "junior"
    And the guild has ratification enabled
    When I complete a workflow task in "Translation Guild"
    Then a ratification request should be created
    And the ratification status should be "pending"
    And I should be listed as the junior on the ratification

  Scenario: Mentor can view ratification queue
    Given I am a member of a guild "Editorial Guild" with role "mentor"
    And there are 3 pending ratification requests in "Editorial Guild"
    When I visit the ratification queue for "Editorial Guild"
    Then I should see "Ratification Queue"
    And I should see 3 pending items
    And each item should show the junior member name
    And each item should show the task title

  Scenario: Mentor can approve ratification
    Given I am a member of a guild "Translation Guild" with role "mentor"
    And there is a pending ratification from junior "testjunior"
    When I visit the ratification review page
    And I select "Approved" for the ratification status
    And I enter feedback "Great work! Very accurate translation."
    And I submit the ratification review
    Then the ratification status should be "approved"
    And the junior should receive a notification
    And the mentor should receive guild points

  Scenario: Mentor can request changes on ratification
    Given I am a member of a guild "Proofreading Guild" with role "mentor"
    And there is a pending ratification from junior "newmember"
    When I visit the ratification review page
    And I select "Changes Requested" for the ratification status
    And I enter feedback "Please review the punctuation in paragraphs 2-4."
    And I submit the ratification review
    Then the ratification status should be "changes_requested"
    And the junior should receive a notification with the feedback
    And the workflow task should not advance

  Scenario: Endorsed member can work without ratification
    Given I am a member of a guild "Editorial Guild" with role "endorsed"
    When I complete a workflow task in "Editorial Guild"
    Then no ratification request should be created
    And the workflow should advance normally

  Scenario: Admin has full guild permissions
    Given I am a member of a guild "Technical Guild" with role "admin"
    Then I should be able to:
      | Manage guild members   |
      | Ratify junior work     |
      | Give endorsements      |
      | Configure guild settings |

  Scenario: Create a guild score entry
    Given I am a member of a guild "Translation Guild"
    When I complete a workflow task in "Translation Guild"
    Then a guild score entry should be created
    And the score entry should reference the current user
    And the score entry should reference "Translation Guild"
    And the action type should be "task_completed"
    And I should receive 10 points

  Scenario: Approved ratification awards points
    Given I am a member of a guild "Editorial Guild" with role "junior"
    And my task is ratified by a mentor
    When the ratification is approved
    Then I should receive 15 points for "task_ratified"
    And the mentor should receive 5 points for "ratification_given"

  Scenario: View guild leaderboard
    Given a guild "Translation Guild" exists with 5 members
    And the members have earned various points
    When I visit the guild leaderboard for "Translation Guild"
    Then I should see "Guild Leaderboard"
    And I should see members ranked by points
    And the leaderboard should show top 10 members

  Scenario: View my guild member profile
    Given I am a member of a guild "Editorial Guild" with role "endorsed"
    And I have earned 150 points
    And I have 3 skill endorsements
    When I visit my guild profile for "Editorial Guild"
    Then I should see "Guild Member Profile"
    And I should see my total points: 150
    And I should see my guild role: "endorsed"
    And I should see my 3 endorsements

  Scenario: Create a skill endorsement
    Given I am a member of a guild "Technical Guild" with role "endorsed"
    And "testuser" is a member of "Technical Guild"
    When I endorse "testuser" for skill "Technical Skills"
    And I add comment "Excellent debugging and problem solving"
    And I submit the endorsement
    Then a skill endorsement entity should be created
    And the endorser should be me
    And the endorsed user should be "testuser"
    And the skill should be "Technical Skills"
    And "testuser" should receive 20 points for "endorsement_received"
    And I should receive 5 points for "endorsement_given"

  Scenario: Junior members cannot give endorsements
    Given I am a member of a guild "Translation Guild" with role "junior"
    And "anotheruser" is a member of "Translation Guild"
    When I attempt to endorse "anotheruser" for a skill
    Then I should not have permission
    And I should see "Only endorsed, mentor, or admin members can give endorsements"

  Scenario: Cannot endorse the same skill twice
    Given I am a member of a guild "Editorial Guild" with role "endorsed"
    And I have already endorsed "testuser" for skill "Theology"
    When I attempt to endorse "testuser" for skill "Theology" again
    Then the endorsement should be rejected
    And I should see "You have already endorsed this user for this skill"

  Scenario: View skill endorsements for a user
    Given I am a member of a guild "Translation Guild"
    And "skillfuluser" has 5 endorsements for various skills
    When I view the skill endorsements for "skillfuluser"
    Then I should see all 5 endorsements
    And each endorsement should show the endorser name
    And each endorsement should show the skill name
    And each endorsement should show the optional comment

  Scenario: Guild scoring can be enabled or disabled
    Given a guild "Experimental Guild" exists
    And scoring is disabled for "Experimental Guild"
    When members complete tasks in "Experimental Guild"
    Then no guild scores should be recorded

  Scenario: Promotion threshold is configurable
    Given a guild "Translation Guild" exists
    And the promotion threshold is set to 100 points
    When a junior member earns 100 points
    Then they should be eligible for promotion to "endorsed"

  Scenario: Guild dashboard shows member activity
    Given I am a member of a guild "Editorial Guild" with role "mentor"
    When I visit the guild dashboard for "Editorial Guild"
    Then I should see "Guild Dashboard"
    And I should see recent member activity
    And I should see the ratification queue
    And I should see the guild leaderboard

  Scenario: Track points by skill
    Given I am a member of a guild "Translation Guild"
    And the guild has skill "Technical Writing/Editing"
    When I complete tasks for "Technical Writing/Editing"
    Then my guild scores should reference the skill
    And I should be able to see my points per skill

  Scenario: Guild settings are configurable
    Given I am a member of a guild "Technical Guild" with role "admin"
    When I visit the guild settings page
    Then I should be able to configure:
      | Scoring enabled           |
      | Promotion threshold       |
      | Ratification required     |
      | Guild skills list         |

  Scenario: Ratification is optional per guild
    Given a guild "Open Guild" exists
    And ratification is not required for "Open Guild"
    When a junior member completes a task in "Open Guild"
    Then no ratification request should be created

  Scenario: View all guild scores
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/avc/guild/scores"
    Then I should see "Guild Scores"
    And I should see a list of all score entries
    And I can filter by user, guild, and action type

  Scenario: View all skill endorsements
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/avc/guild/endorsements"
    Then I should see "Skill Endorsements"
    And I should see a list of all endorsements

  Scenario: View all ratifications
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/avc/guild/ratifications"
    Then I should see "Ratifications"
    And I should see all ratification requests
    And I can filter by status and guild

  Scenario: Ratification integrates with workflow
    Given I am a member of a guild "Translation Guild" with role "junior"
    And I have a workflow task assigned to me
    When I mark the task as completed
    Then a ratification should be automatically created
    And the workflow should pause waiting for ratification
    When a mentor approves the ratification
    Then the workflow should advance to the next stage

  Scenario: Multiple skills tracked in single guild
    Given a guild "Multi-Skill Guild" exists with skills:
      | Computer Skills           |
      | Video Editing            |
      | Prayer Warrior           |
    When different members complete tasks for different skills
    Then each score should reference the appropriate skill
    And the leaderboard can be filtered by skill

  Scenario: Guild role visibility and permissions
    Given a guild "Hierarchy Guild" exists
    And the guild has members with all four roles
    Then the following permission matrix should apply:
      | Role     | Can Ratify | Can Endorse | Needs Ratification |
      | junior   | No         | No          | Yes                |
      | endorsed | No         | Yes         | No                 |
      | mentor   | Yes        | Yes         | No                 |
      | admin    | Yes        | Yes         | No                 |
