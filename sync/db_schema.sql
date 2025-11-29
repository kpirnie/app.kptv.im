--
-- Database: `kptv-db`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `CleanupStreams`$$
CREATE PROCEDURE `CleanupStreams` ()   BEGIN
    START TRANSACTION;
    
    -- Delete streams with invalid provider IDs
    DELETE FROM kptv_streams 
    WHERE NOT EXISTS (
        SELECT 1 
        FROM kptv_stream_providers 
        WHERE kptv_stream_providers.id = kptv_streams.p_id
    );
    
    -- Delete duplicate streams - keep only the one with MAX(id)
    DELETE s1 
    FROM kptv_streams s1
    LEFT JOIN (
        SELECT MAX(id) as max_id, s_stream_uri
        FROM kptv_streams
        GROUP BY s_stream_uri
    ) s2 ON s1.id = s2.max_id
    WHERE s2.max_id IS NULL;
    
    -- Clear temporary table
    TRUNCATE TABLE kptv_stream_temp;
    
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `UpdateStreamMetadata`$$
CREATE PROCEDURE `UpdateStreamMetadata` ()   BEGIN
    START TRANSACTION;
    
    -- Copy channel numbers from matching stream names
    UPDATE `kptv_streams` a
    INNER JOIN `kptv_streams` b ON b.`s_name` = a.`s_name`
    SET a.`s_channel` = b.`s_channel`
    WHERE a.`s_channel` = '0'
    AND b.`s_channel` != '0'
    AND a.`s_type_id` = 0;
    
    -- Copy stream names from matching original names for inactive streams
    UPDATE `kptv_streams` a
    INNER JOIN `kptv_streams` b ON b.`s_orig_name` = a.`s_orig_name`
    SET a.`s_name` = b.`s_name`
    WHERE a.`s_active` = 0 
    AND a.`s_type_id` IN (0, 4, 5)
    AND b.`s_name` != a.`s_name`
    AND b.`s_active` = 1;
    
    -- Update TVG IDs from most recently updated stream with same name
    UPDATE kptv_streams a
    JOIN (
        SELECT s_name, s_tvg_id
        FROM (
            SELECT s_name, s_tvg_id, s_updated,
                   ROW_NUMBER() OVER (PARTITION BY s_name ORDER BY s_updated DESC) as rn
            FROM kptv_streams
            WHERE s_tvg_id IS NOT NULL AND s_tvg_id <> ''
        ) ranked
        WHERE rn = 1
    ) b ON a.s_name = b.s_name
    SET a.s_tvg_id = b.s_tvg_id
    WHERE a.s_tvg_id IS NULL OR a.s_tvg_id = '' OR a.s_tvg_id != b.s_tvg_id;
    
    -- Update TVG logos from most recently updated stream with same name
    UPDATE kptv_streams a
    JOIN (
        SELECT s_name, s_tvg_logo
        FROM (
            SELECT s_name, s_tvg_logo, s_updated,
                   ROW_NUMBER() OVER (PARTITION BY s_name ORDER BY s_updated DESC) as rn
            FROM kptv_streams
            WHERE s_tvg_logo IS NOT NULL AND s_tvg_logo <> ''
        ) ranked
        WHERE rn = 1
    ) b ON a.s_name = b.s_name
    SET a.s_tvg_logo = b.s_tvg_logo
    WHERE a.s_tvg_logo IS NULL OR a.s_tvg_logo = '' OR a.s_tvg_logo != b.s_tvg_logo;
    
    COMMIT;
END$$

DELIMITER ;

--
-- Table structure for table `kptv_streams`
--
DROP TABLE IF EXISTS `kptv_streams`;
CREATE TABLE `kptv_streams` (
  `id` bigint NOT NULL,
  `u_id` bigint NOT NULL,
  `p_id` bigint NOT NULL DEFAULT '0',
  `s_type_id` tinyint NOT NULL DEFAULT '0',
  `s_active` tinyint(1) NOT NULL DEFAULT '0',
  `s_channel` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `s_name` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `s_orig_name` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `s_stream_uri` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `s_tvg_id` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_tvg_group` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_tvg_logo` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_extras` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `s_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Table structure for table `kptv_stream_filters`
--
DROP TABLE IF EXISTS `kptv_stream_filters`;
CREATE TABLE `kptv_stream_filters` (
  `id` bigint NOT NULL,
  `u_id` bigint NOT NULL,
  `sf_active` tinyint(1) NOT NULL DEFAULT '1',
  `sf_type_id` tinyint NOT NULL DEFAULT '0',
  `sf_filter` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sf_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sf_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Table structure for table `kptv_stream_missing`
--
DROP TABLE IF EXISTS `kptv_stream_missing`;
CREATE TABLE `kptv_stream_missing` (
  `id` bigint UNSIGNED NOT NULL,
  `u_id` bigint NOT NULL,
  `p_id` bigint NOT NULL,
  `stream_id` bigint NOT NULL DEFAULT '0',
  `other_id` bigint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Table structure for table `kptv_stream_providers`
--
DROP TABLE IF EXISTS `kptv_stream_providers`;
CREATE TABLE `kptv_stream_providers` (
  `id` bigint NOT NULL,
  `u_id` bigint NOT NULL,
  `sp_should_filter` tinyint(1) NOT NULL DEFAULT '1',
  `sp_priority` int NOT NULL DEFAULT '99',
  `sp_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sp_cnx_limit` int NOT NULL DEFAULT '1',
  `sp_type` tinyint(1) NOT NULL DEFAULT '0',
  `sp_domain` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sp_username` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sp_password` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sp_stream_type` tinyint(1) NOT NULL DEFAULT '0',
  `sp_refresh_period` int NOT NULL DEFAULT '3',
  `sp_last_synced` datetime DEFAULT NULL,
  `sp_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sp_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Table structure for table `kptv_stream_temp`
--
DROP TABLE IF EXISTS `kptv_stream_temp`;
CREATE TABLE `kptv_stream_temp` (
  `id` bigint UNSIGNED NOT NULL,
  `u_id` bigint NOT NULL,
  `p_id` bigint NOT NULL,
  `s_type_id` tinyint NOT NULL DEFAULT '0',
  `s_orig_name` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `s_stream_uri` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `s_tvg_id` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_tvg_logo` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_extras` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_group` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `s_orig_name_lower` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(`s_orig_name`)) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Indexes for table `kptv_streams`
--
ALTER TABLE `kptv_streams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `u_id` (`u_id`),
  ADD KEY `s_type_id` (`s_type_id`),
  ADD KEY `p_id` (`p_id`),
  ADD KEY `s_active` (`s_active`),
  ADD KEY `idx_s_name` (`s_name`(255)),
  ADD KEY `idx_s_orig_name` (`s_orig_name`(255)),
  ADD KEY `idx_active_tvgid` (`s_active`,`s_tvg_id`(255)),
  ADD KEY `idx_active_tvglogo` (`s_active`,`s_tvg_logo`(255)),
  ADD KEY `idx_channel` (`s_channel`),
  ADD KEY `idx_name_updated` (`s_name`(255),`s_updated`);
ALTER TABLE `kptv_streams` ADD FULLTEXT KEY `s_stream_uri` (`s_stream_uri`);
ALTER TABLE `kptv_streams` ADD FULLTEXT KEY `s_name` (`s_name`);
ALTER TABLE `kptv_streams` ADD FULLTEXT KEY `s_orig_name` (`s_orig_name`);
--
-- Indexes for table `kptv_stream_filters`
--
ALTER TABLE `kptv_stream_filters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `u_id` (`u_id`),
  ADD KEY `sf_active` (`sf_active`),
  ADD KEY `sf_type_id` (`sf_type_id`),
  ADD KEY `idx_user_active_type` (`u_id`,`sf_active`,`sf_type_id`);
--
-- Indexes for table `kptv_stream_missing`
--
ALTER TABLE `kptv_stream_missing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lower_name_pid` (`p_id`),
  ADD KEY `stream_id` (`stream_id`),
  ADD KEY `other_id` (`other_id`),
  ADD KEY `u_id` (`u_id`);
--
-- Indexes for table `kptv_stream_providers`
--
ALTER TABLE `kptv_stream_providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `u_id` (`u_id`),
  ADD KEY `sp_name` (`sp_name`);
--
-- Indexes for table `kptv_stream_temp`
--
ALTER TABLE `kptv_stream_temp`
  ADD PRIMARY KEY (`id`);
--
-- AUTO_INCREMENT for table `kptv_streams`
--
ALTER TABLE `kptv_streams`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `kptv_stream_filters`
--
ALTER TABLE `kptv_stream_filters`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `kptv_stream_missing`
--
ALTER TABLE `kptv_stream_missing`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `kptv_stream_providers`
--
ALTER TABLE `kptv_stream_providers`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `kptv_stream_temp`
--
ALTER TABLE `kptv_stream_temp`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
