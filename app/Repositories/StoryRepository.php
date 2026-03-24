<?php

declare(strict_types=1);

namespace Club61\Repositories;

use Club61\Infrastructure\Http\SupabaseRestClient;

final class StoryRepository
{
    public function __construct(
        private readonly SupabaseRestClient $http,
    ) {
    }

    /**
     * Perfis com story ativa (mesma ordem que o feed antigo).
     *
     * @return list<array<string, mixed>>
     */
    public function activeStoryProfiles(): array
    {
        if (!\defined('SUPABASE_SERVICE_KEY') || \SUPABASE_SERVICE_KEY === '') {
            return [];
        }

        $nowIso = gmdate('Y-m-d\TH:i:s\Z');
        $stUrl = \SUPABASE_URL . '/rest/v1/stories?select=user_id,expires_at,created_at'
            . '&expires_at=gt.' . rawurlencode($nowIso)
            . '&order=created_at.desc'
            . '&limit=100';
        $headers = [
            'apikey: ' . \SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . \SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ];
        $res = $this->http->jsonGet($stUrl, $headers);
        if ($res === null) {
            return [];
        }
        [, $storyRows] = $res;
        if (!is_array($storyRows)) {
            return [];
        }

        $orderedUserIds = [];
        $seen = [];
        foreach ($storyRows as $sr) {
            $uid = isset($sr['user_id']) ? (string) $sr['user_id'] : '';
            if ($uid !== '' && !isset($seen[$uid])) {
                $seen[$uid] = true;
                $orderedUserIds[] = $uid;
                if (count($orderedUserIds) >= 20) {
                    break;
                }
            }
        }

        if ($orderedUserIds === []) {
            return [];
        }

        $inList = implode(',', $orderedUserIds);
        $spUrl = \SUPABASE_URL . '/rest/v1/profiles?select=id,username,display_id,avatar_url,last_seen&id=in.(' . $inList . ')';
        $resP = $this->http->jsonGet($spUrl, $headers);
        if ($resP === null) {
            return [];
        }
        [, $decodedP] = $resP;
        if (!is_array($decodedP)) {
            return [];
        }
        $byId = [];
        foreach ($decodedP as $p) {
            if (isset($p['id'])) {
                $byId[(string) $p['id']] = $p;
            }
        }
        $storyProfiles = [];
        foreach ($orderedUserIds as $oid) {
            if (isset($byId[$oid])) {
                $storyProfiles[] = $byId[$oid];
            }
        }

        return $storyProfiles;
    }
}
