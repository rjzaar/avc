# Workflow Assignment - Improvements and Feature Suggestions

## Bug Fixes / Issues

1. **JavaScript edit doesn't persist** - The double-click edit in `workflow-tab.js` shows a save button but doesn't actually save to the backend. Either implement AJAX save or remove the misleading edit functionality.

2. **Permission logic inconsistency** - The workflow tab route requires `view workflow list assignments+assign workflow lists to content` (both), but logically viewing should only require the view permission.

3. **Unused entities** - `WorkflowAssignment` and `WorkflowTemplate` content entities appear unused in the current simplified model. Consider removing or implementing them.

## UI/UX Improvements

4. **Add drag-and-drop reordering** - Allow ordering of workflows on a node to indicate priority or sequence.

5. **Add workflow status/completion tracking** - The `WorkflowAssignment` entity has a `completion` field (proposed/accepted/completed) but it's not used. Implement status tracking on the workflow tab.

6. **Add bulk operations** - Allow bulk assignment of workflows to multiple nodes from the workflow list admin page.

7. **Add filtering/search on workflow tab** - For nodes with many workflows, add filtering by assignment type or search by name.

8. **Improve empty state** - When no workflows exist, show a helpful message with a link to create workflows (for admins).

## New Features

9. **Workflow due dates** - Add optional due date field to workflows with calendar integration and overdue indicators.

10. **Email notifications** - Notify assigned users/groups when a workflow is assigned to content they're responsible for.

11. **Workflow history/audit log** - Track when workflows were assigned/unassigned and by whom.

12. **Workflow templates** - Implement the existing `WorkflowTemplate` entity to allow applying preset workflow bundles to content.

13. **Views integration** - Create default Views for listing content by assigned workflow, or content assigned to current user.

14. **REST API** - Add REST endpoints for workflow assignment (useful for headless/decoupled sites).

15. **Workflow cloning** - Add ability to duplicate an existing workflow with a new name.

## Architecture Improvements

16. **Use events/hooks for extensibility** - Dispatch events when workflows are assigned/unassigned so other modules can react.

17. **Add caching** - Cache workflow lookups in `NodeWorkflowController` for better performance.

18. **Remove debug logging** - The controller has extensive `\Drupal::logger()` debug calls that should be removed for production.

19. **Add config schema** - Create proper config schema validation for `workflow_assignment.settings`.

20. **Dependency injection** - The `WorkflowList` entity uses `\Drupal::` static calls. Refactor to use dependency injection where possible.

## Accessibility

21. **Add ARIA labels** - The expandable cells need proper ARIA attributes for screen readers.

22. **Keyboard navigation** - Ensure expand/collapse and edit functions work with keyboard (Enter/Space keys).

## Testing

23. **Add PHPUnit tests** - No tests exist. Add unit tests for entity methods and functional tests for forms.

24. **Add JavaScript tests** - Add Nightwatch or similar tests for the expandable cell behaviors.
