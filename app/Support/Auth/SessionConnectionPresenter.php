<?php

namespace App\Support\Auth;

use App\Models\DatabaseSession;
use App\Support\Http\IpApproximateLocation;
use App\Support\Http\UserAgentSummary;

/**
 * Metadados de ligação (IP, localização, navegador) para a lista de sessões.
 */
final class SessionConnectionPresenter
{
    public function __construct(
        private readonly IpApproximateLocation $geo,
        private readonly UserAgentSummary $userAgent,
    ) {}

    /**
     * @return array{
     *     ip: ?string,
     *     location: ?string,
     *     user_agent_short: ?string,
     *     user_agent_full: ?string
     * }
     */
    public function forSession(DatabaseSession $session): array
    {
        $ip = filled($session->ip_address) ? (string) $session->ip_address : null;
        $uaFull = filled($session->user_agent) ? (string) $session->user_agent : null;

        return [
            'ip' => $ip,
            'location' => $this->geo->label($ip),
            'user_agent_short' => $this->userAgent->short($uaFull),
            'user_agent_full' => $uaFull,
        ];
    }
}
