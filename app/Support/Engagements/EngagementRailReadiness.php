<?php

namespace App\Support\Engagements;

final readonly class EngagementRailReadiness
{
    /**
     * @param  list<string>  $blockers
     */
    public function __construct(
        public bool $intakeReady,
        public bool $formsReady,
        public bool $reviewReady,
        public bool $signOffReady,
        public bool $handbookReady,
        public array $blockers = [],
    ) {}

    /**
     * @return array{
     *   intake_ready: bool,
     *   forms_ready: bool,
     *   review_ready: bool,
     *   sign_off_ready: bool,
     *   handbook_ready: bool,
     *   blockers: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'intake_ready' => $this->intakeReady,
            'forms_ready' => $this->formsReady,
            'review_ready' => $this->reviewReady,
            'sign_off_ready' => $this->signOffReady,
            'handbook_ready' => $this->handbookReady,
            'blockers' => array_values($this->blockers),
        ];
    }
}
