<?php

declare(strict_types=1);

namespace Club61\Repositories;

use Club61\Infrastructure\Http\SupabaseRestClient;

final class ProfileRepository
{
    public function __construct(
        private readonly SupabaseRestClient $http,
    ) {
    }

    public function countMembers(string $accessToken): int
    {
        if (\supabase_service_role_available()) {
            return \countProfilesTotalUsingServiceRole();
        }
        if ($accessToken === '') {
            return 0;
        }

        return \countProfilesTotal($accessToken);
    }

    /**
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    public function fetchByIds(array $ids, string $accessToken): array
    {
        if ($ids === []) {
            return [];
        }
        $inList = implode(',', $ids);
        $url = \SUPABASE_URL . '/rest/v1/profiles?select=id,username,display_id,avatar_url,last_seen&id=in.(' . $inList . ')';
        if (\supabase_service_role_available()) {
            $headers = array_merge(\supabase_service_rest_headers(false), ['Accept: application/json']);
        } else {
            $headers = [
                'apikey: ' . \SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . $accessToken,
            ];
        }
        $res = $this->http->jsonGet($url, $headers);
        if ($res === null) {
            return [];
        }
        [, $body] = $res;
        if (!is_array($body)) {
            return [];
        }
        $byId = [];
        foreach ($body as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }

        return $byId;
    }
}
