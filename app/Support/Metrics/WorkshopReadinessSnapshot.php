<?php

namespace App\Support\Metrics;

use Illuminate\Support\Str;

final readonly class WorkshopReadinessSnapshot
{
    /**
     * @param  list<string>  $requiredDepartments
     * @param  list<string>  $representedDepartments
     * @param  list<string>  $missingRequiredDepartments
     */
    public function __construct(
        public string $templateKey,
        public array $requiredDepartments,
        public array $representedDepartments,
        public array $missingRequiredDepartments,
    ) {}

    /**
     * @return array{
     *     required_departments: list<string>,
     *     represented_departments: list<string>,
     *     missing_required_departments: list<string>,
     *     gaps: list<array{
     *         id: string,
     *         scope: 'workshop_readiness',
     *         kind: 'missing_stakeholder_perspective',
     *         label: string,
     *         description: string,
     *         source_item_ids: list<string>,
     *         citation_ids: list<string>
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'required_departments' => array_values($this->requiredDepartments),
            'represented_departments' => array_values($this->representedDepartments),
            'missing_required_departments' => array_values($this->missingRequiredDepartments),
            'gaps' => array_map(
                fn (string $department): array => $this->gapForDepartment($department),
                $this->missingRequiredDepartments,
            ),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     scope: 'workshop_readiness',
     *     kind: 'missing_stakeholder_perspective',
     *     label: string,
     *     description: string,
     *     source_item_ids: list<string>,
     *     citation_ids: list<string>
     * }
     */
    private function gapForDepartment(string $department): array
    {
        $departmentKey = Str::slug($department);
        $templateKey = trim($this->templateKey);

        return [
            'id' => "readiness:required_department:{$departmentKey}",
            'scope' => 'workshop_readiness',
            'kind' => 'missing_stakeholder_perspective',
            'label' => "{$department} perspective",
            'description' => "{$department} is required by this metric template but has no submitted response. Confirm a {$department} participant or explicitly accept the missing perspective before the workshop.",
            'source_item_ids' => ["readiness:required_department:{$departmentKey}"],
            'citation_ids' => ["metric_template:{$templateKey}:required_department:{$departmentKey}"],
        ];
    }
}
