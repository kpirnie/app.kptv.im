<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Parsers\ProviderFactory;

class SyncEngine
{
    private FilterManager $filterManager;
    private array $ignoreFields;
    private array $fieldMapping = [
        'tvg_id' => 's_tvg_id',
        'logo' => 's_tvg_logo',
        'tvg_group' => 's_tvg_group'
    ];

    public function __construct(
        private readonly KpDb $db,
        array $ignoreFields = []
    ) {
        $this->filterManager = new FilterManager($db);
        $this->ignoreFields = $ignoreFields;
    }

    private function shouldSyncField(string $fieldName): bool
    {
        foreach ($this->ignoreFields as $ignoreField) {
            if ($fieldName === ($this->fieldMapping[$ignoreField] ?? null)) {
                return false;
            }
        }
        return true;
    }

    public function syncProvider(array $provider): int
    {
        $providerId = $provider['id'];
        $userId = $provider['u_id'];
        $shouldFilter = $provider['sp_should_filter'] === 1;

        echo sprintf("Provider: %s (ID: %d)\n", $provider['sp_name'], $providerId);

        // Get provider parser
        $parser = ProviderFactory::create($provider);

        // Fetch streams from provider
        echo "Fetching streams from provider...\n";
        $rawStreams = $parser->fetchStreams();
        echo sprintf("Retrieved %s streams from provider\n", number_format(count($rawStreams)));

        // Apply filters if needed
        if ($shouldFilter) {
            echo "Applying filters...\n";
            $filters = $this->filterManager->getFilters($userId);
            echo sprintf("Found %s active filters for user %d\n", number_format(count($filters)), $userId);
            
            if (!empty($filters)) {
                $beforeCount = count($rawStreams);
                $rawStreams = $this->filterManager->applyFilters($rawStreams, $filters);
                $afterCount = count($rawStreams);
                $filtered = $beforeCount - $afterCount;
                
                echo sprintf(
                    "Filter results: %s streams kept, %s filtered out (%.1f%% filtered)\n",
                    number_format($afterCount),
                    number_format($filtered),
                    ($beforeCount > 0 ? ($filtered / $beforeCount * 100) : 0)
                );
            } else {
                echo "No filters configured - all streams will be processed\n";
            }
        } else {
            echo "Filtering disabled for this provider\n";
        }

        if (empty($rawStreams)) {
            echo "No streams to sync after filtering\n";
            return 0;
        }

        // Clear temp table for this provider
        echo "Clearing temporary table...\n";
        $this->db->delete(
            table: 'stream_temp',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        // Insert into temp table
        if (!empty($rawStreams)) {
            echo "Inserting streams into temporary table...\n";
            $tempRecords = [];
            foreach ($rawStreams as $stream) {
                $tempRecords[] = [
                    'u_id' => $userId,
                    'p_id' => $providerId,
                    's_type_id' => $stream['s_type_id'],
                    's_orig_name' => $stream['s_orig_name'],
                    's_stream_uri' => $stream['s_stream_uri'],
                    's_tvg_id' => $stream['s_tvg_id'] ?? null,
                    's_tvg_logo' => $stream['s_tvg_logo'] ?? null,
                    's_extras' => $stream['s_extras'] ?? null,
                    's_group' => $stream['s_tvg_group'] ?? null
                ];
            }

            // Insert in batches
            $this->db->insert_many(
                table: 'stream_temp',
                data: $tempRecords,
                ignore_duplicates: true,
                batch_size: 1000
            );
            echo sprintf("Inserted %s records into temporary table\n", number_format(count($tempRecords)));
        }

        // Sync from temp to main streams table
        echo "Syncing to main streams table...\n";
        $syncedCount = $this->syncTempToStreams($userId, $providerId);

        echo sprintf("Sync complete: %s streams processed\n", number_format($syncedCount));
        return $syncedCount;
    }

    private function syncTempToStreams(int $userId, int $providerId): int
    {
        $existingStreams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_orig_name', 's_stream_uri', 's_tvg_id', 's_tvg_group', 's_tvg_logo', 's_extras'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        // Match by name + normalized uri
        $existingLookup = [];
        foreach ($existingStreams ?? [] as $s) {
            $normalizedUri = $this->normalizeUri($s['s_stream_uri']);
            $key = strtolower($s['s_orig_name']) . '||' . $normalizedUri;
            $existingLookup[$key] ??= $s;
        }

        $tempStreams = $this->db->get_all(
            table: 'stream_temp',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        if (empty($tempStreams)) {
            echo "No streams in temporary table\n";
            return 0;
        }

        $updates = [];
        $inserts = [];
        $unchanged = 0;

        foreach ($tempStreams as $temp) {
            $normalizedUri = $this->normalizeUri($temp['s_stream_uri']);
            $key = strtolower($temp['s_orig_name']) . '||' . $normalizedUri;

            if (isset($existingLookup[$key])) {
                $existing = $existingLookup[$key];

                if ($this->hasChanges($existing, $temp)) {
                    $updates[] = [$existing['id'], $temp];
                } else {
                    $unchanged++;
                }
            } else {
                $inserts[] = $temp;
            }
        }

        echo sprintf(
            "Analysis: %s to update, %s to insert, %s unchanged\n",
            number_format(count($updates)),
            number_format(count($inserts)),
            number_format($unchanged)
        );

        // Batch updates
        if (!empty($updates)) {
            echo "Updating existing streams...\n";
            $updateCount = 0;
            foreach ($updates as [$streamId, $data]) {
                $updateData = [
                    's_orig_name' => $data['s_orig_name'],
                    's_stream_uri' => $data['s_stream_uri'],
                    's_updated' => null
                ];

                if ($this->shouldSyncField('s_tvg_id')) {
                    $updateData['s_tvg_id'] = $data['s_tvg_id'];
                }
                if ($this->shouldSyncField('s_tvg_logo')) {
                    $updateData['s_tvg_logo'] = $data['s_tvg_logo'];
                }
                if ($this->shouldSyncField('s_tvg_group')) {
                    $updateData['s_tvg_group'] = $data['s_group'];
                }

                $updateData['s_extras'] = $data['s_extras'];

                $this->db->update(
                    table: 'streams',
                    where: [new WhereClause('id', $streamId, ComparisonOperator::EQ)],
                    data: $updateData
                );
                $updateCount++;
                if ($updateCount % 500 === 0) {
                    echo sprintf("  Updated %s streams...\n", number_format($updateCount));
                }
            }
            echo sprintf("Updated %s streams\n", number_format(count($updates)));
        }

        // Batch inserts
        if (!empty($inserts)) {
            echo "Inserting new streams...\n";
            $insertRecords = [];
            foreach ($inserts as $data) {
                
                $insertRecord = [
                    'u_id' => $userId,
                    'p_id' => $providerId,
                    's_type_id' => $data['s_type_id'],
                    's_active' => 0,
                    's_channel' => '0',
                    's_name' => $data['s_orig_name'],
                    's_orig_name' => $data['s_orig_name'],
                    's_stream_uri' => $data['s_stream_uri']
                ];

                if ($this->shouldSyncField('s_tvg_id')) {
                    $insertRecord['s_tvg_id'] = $data['s_tvg_id'];
                }
                if ($this->shouldSyncField('s_tvg_logo')) {
                    $insertRecord['s_tvg_logo'] = $data['s_tvg_logo'];
                }
                if ($this->shouldSyncField('s_tvg_group')) {
                    $insertRecord['s_tvg_group'] = $data['s_group'];
                }

                $insertRecord['s_extras'] = $data['s_extras'];

                $insertRecords[] = $insertRecord;
            }

            $this->db->insert_many(
                table: 'streams',
                data: $insertRecords,
                ignore_duplicates: true,
                batch_size: 500
            );
            echo sprintf("Inserted %s new streams\n", number_format(count($inserts)));
        }

        // Clear temp table after sync
        echo "Cleaning up temporary table...\n";
        $this->db->delete(
            table: 'stream_temp',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        return count($updates) + count($inserts);
    }

    private function normalizeUri(string $uri): string
    {
        $fastDomains = ['pluto.tv', 'plex.tv', 'tubi.io', 'xumo.com', 'samsung.tv'];

        $parsed = parse_url($uri);
        $isFast = false;

        if (isset($parsed['host'])) {
            foreach ($fastDomains as $domain) {
                if (str_contains($parsed['host'], $domain)) {
                    $isFast = true;
                    break;
                }
            }
        }

        if ($isFast) {
            return ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '');
        }

        return $uri;
    }

    private function hasChanges(array $existing, array $temp): bool
    {
        $normalize = fn($val) => $val ?: null;

        $fieldsToCheck = [
            ['s_orig_name', 's_orig_name'],
            ['s_stream_uri', 's_stream_uri'],
            ['s_tvg_id', 's_tvg_id'],
            ['s_tvg_group', 's_group'],
            ['s_tvg_logo', 's_tvg_logo'],
            ['s_extras', 's_extras']
        ];

        foreach ($fieldsToCheck as [$existingField, $tempField]) {
            $existingVal = $normalize($existing[$existingField] ?? null);
            $tempVal = $normalize($temp[$tempField] ?? null);

            if ($existingVal !== $tempVal) {
                return true;
            }
        }

        return false;
    }
}
