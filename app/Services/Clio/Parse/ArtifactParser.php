<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaignArtifact;

interface ArtifactParser
{
    public function supports(string $kind): bool;

    public function parse(string $absolutePath, ClioCampaignArtifact $artifact): ParseResult;
}
