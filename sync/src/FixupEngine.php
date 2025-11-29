<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Database\OrderByClause;

class FixupEngine
{
    private int $batchSize = 500;

    public function __construct(
        private readonly KpDb $db
    ) {
    }

    public function fixupProvider(array $provider): int
    {
        $providerId = $provider['id'];
        $userId = $provider['u_id'];

        $total = 0;
        $total += $this->fixupNames($userId, $providerId) ?? 0;
        $total += $this->fixupChannels($userId, $providerId) ?? 0;
        $total += $this->fixupMetadata($userId, $providerId) ?? 0;

        return $total;
    }

    private function fixupNames(int $userId, int $providerId): int
    {
        $streams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_orig_name', 's_name', 's_type_id', 's_active', 's_updated'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        if (empty($streams)) {
            return 0;
        }

        $customNames = [];
        foreach ($streams as $s) {
            $key = strtolower($s['s_orig_name']) . '||' . $s['s_type_id'];
            $customName = trim($s['s_name'] ?? '');
            $origName = trim($s['s_orig_name']);
            $updated = $s['s_updated'] ?? null;

            if ($customName !== '' && $customName !== $origName) {
                if (!isset($customNames[$key])) {
                    $customNames[$key] = ['name' => $customName, 'active' => $s['s_active'], 'updated' => $updated];
                } else {
                    $existing = $customNames[$key];
                    if (($s['s_active'] === 1 && $existing['active'] !== 1) ||
                        ($s['s_active'] === $existing['active'] && $updated && (!$existing['updated'] || $updated > $existing['updated']))) {
                        $customNames[$key] = ['name' => $customName, 'active' => $s['s_active'], 'updated' => $updated];
                    }
                }
            }
        }

        $updates = [];
        foreach ($streams as $s) {
            $key = strtolower($s['s_orig_name']) . '||' . $s['s_type_id'];
            $currentName = trim($s['s_name'] ?? '');
            $origName = trim($s['s_orig_name']);
            $sUpdated = $s['s_updated'] ?? null;

            if (isset($customNames[$key])) {
                $bestName = $customNames[$key]['name'];
                $nameUpdated = $customNames[$key]['updated'];

                if ($currentName === '' || $currentName === $origName || ($nameUpdated && (!$sUpdated || $nameUpdated > $sUpdated))) {
                    if ($currentName !== $bestName) {
                        $updates[] = [$s['id'], $bestName];
                    }
                }
            }
        }

        return $this->chunkedUpdate($updates, 's_name');
    }

    private function fixupChannels(int $userId, int $providerId): int
    {
        $streams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_name', 's_channel', 's_updated'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        if (empty($streams)) {
            return 0;
        }

        $channelMap = [];
        foreach ($streams as $s) {
            $customName = trim($s['s_name'] ?? '');
            $channel = $s['s_channel'] ?? '0';
            $updated = $s['s_updated'] ?? null;

            if ($customName !== '' && $channel !== '' && $channel !== '0') {
                $key = strtolower($customName);
                if (!isset($channelMap[$key])) {
                    $channelMap[$key] = ['channel' => $channel, 'updated' => $updated];
                } else {
                    if ($updated && (!$channelMap[$key]['updated'] || $updated > $channelMap[$key]['updated'])) {
                        $channelMap[$key] = ['channel' => $channel, 'updated' => $updated];
                    }
                }
            }
        }

        $updates = [];
        foreach ($streams as $s) {
            $customName = trim($s['s_name'] ?? '');
            $currentChannel = $s['s_channel'] ?? '0';
            $sUpdated = $s['s_updated'] ?? null;

            if ($customName !== '') {
                $key = strtolower($customName);
                if (isset($channelMap[$key])) {
                    $bestChannel = $channelMap[$key]['channel'];
                    $channelUpdated = $channelMap[$key]['updated'];

                    if (($currentChannel === '' || $currentChannel === '0') || ($channelUpdated && (!$sUpdated || $channelUpdated > $sUpdated))) {
                        if ($currentChannel !== $bestChannel) {
                            $updates[] = [$s['id'], $bestChannel];
                        }
                    }
                }
            }
        }

        return $this->chunkedUpdate($updates, 's_channel');
    }

    private function fixupMetadata(int $userId, int $providerId): int
    {
        $streams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_name', 's_tvg_logo', 's_tvg_id'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ],
            order_by: [new OrderByClause('s_updated', 'DESC')]
        );

        if (empty($streams)) {
            return 0;
        }

        $metadataMap = [];
        foreach ($streams as $s) {
            $name = trim($s['s_name'] ?? '');

            if ($name === '') {
                continue;
            }

            if (!isset($metadataMap[$name])) {
                $metadataMap[$name] = [
                    'logo' => trim($s['s_tvg_logo'] ?? ''),
                    'tvg_id' => trim($s['s_tvg_id'] ?? '')
                ];
            }
        }

        $logoUpdates = [];
        $tvgUpdates = [];

        foreach ($streams as $s) {
            $name = trim($s['s_name'] ?? '');

            if ($name === '' || !isset($metadataMap[$name])) {
                continue;
            }

            $best = $metadataMap[$name];
            $currentLogo = trim($s['s_tvg_logo'] ?? '');
            $currentTvg = trim($s['s_tvg_id'] ?? '');

            if ($best['logo'] !== '' && $currentLogo !== $best['logo']) {
                $logoUpdates[] = [$s['id'], $best['logo']];
            }

            if ($best['tvg_id'] !== '' && $currentTvg !== $best['tvg_id']) {
                $tvgUpdates[] = [$s['id'], $best['tvg_id']];
            }
        }

        $fixed = 0;
        $fixed += $this->chunkedUpdate($logoUpdates, 's_tvg_logo');
        $fixed += $this->chunkedUpdate($tvgUpdates, 's_tvg_id');
        return $fixed;
    }

    private function chunkedUpdate(array $updates, string $field): int
    {
        if (empty($updates)) {
            return 0;
        }

        $total = 0;
        $chunks = array_chunk($updates, $this->batchSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as [$streamId, $value]) {
                $this->db->update(
                    table: 'streams',
                    where: [new WhereClause('id', $streamId, ComparisonOperator::EQ)],
                    data: [$field => $value]
                );
            }

            $total += count($chunk);
            if ($total % 1000 === 0) {
            }
        }

        return $total;
    }
}
