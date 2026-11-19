<?php

namespace App\Support\Handbook;

interface HandbookPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = []): string;
}
