# AI-assisted metric alignment

Workshop participants can use the same metric name while meaning different things. A draft alignment brief makes those disagreements visible to a facilitator before the workshop. The Laravel AI SDK agent receives a bounded context and proposes structured content for review. Approval of the metric definition remains a separate workflow.

## Example

Suppose Finance attributes customer acquisition costs to the month when leads were generated, while Growth groups those costs by the month customers first paid. An illustrative draft would flag the attribution window, point to both submitted answers, and ask the facilitator to confirm which cohort the board should use. The facilitator can revise the wording before using it.

## How the draft is checked

- The [agent](../app/Ai/Agents/MetricAlignmentDraftAgent.php) and [prompt builder](../app/Support/Metrics/Drafting/MetricAlignmentDraftPromptBuilder.php) define what the model receives and how it should respond.
- The [JSON Schema](../specs/033-metric-alignment-brief/evaluation/generated-draft-brief.schema.json) describes the response structure. The [validator](../app/Support/Metrics/Evaluation/MetricAlignmentDraftValidator.php) adds checks for grounded citations, bounded draft content, and unsupported actions.
- The [evaluation dataset](../specs/033-metric-alignment-brief/evaluation/dataset-v1.json) contains cases with conflicting inputs, missing information, and situations where no action is needed.

A recorded evaluation over five cases and three runs per case passed all automated gates in four of fifteen runs. In one passing case, the draft still suggested closing the review for an already-aligned metric. The input never authorized that action, so a human reviewer flagged it. The example shows where schema and citation checks help and where facilitator judgment is still needed.
