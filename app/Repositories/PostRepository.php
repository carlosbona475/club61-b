<?php

declare(strict_types=1);

namespace Club61\Repositories;

use Club61\Infrastructure\Http\SupabaseRestClient;

final class PostRepository
{
    public function __construct(
        private readonly SupabaseRestClient $http,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFeedPage(int $limit, int $offset, string $accessToken): array
    {
        $url = \SUPABASE_URL . '/rest/v1/posts?select=id,user_id,image_url,caption,created_at&order=created_at.desc'
            . '&limit=' . $limit . '&offset=' . $offset;
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

        return $body;
    }
}
