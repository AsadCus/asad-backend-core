-- ============================================
-- MAID DATA QUALITY MONITORING SCRIPT
-- ============================================
-- Purpose: Monitor and validate data quality in maids table
-- Usage: Run this in pgAdmin or psql console
-- ============================================

-- 1. CHECK FOR DOUBLE-ESCAPED JSON
-- ============================================
SELECT 
    'Double Escaped JSON Check' as check_name,
    COUNT(*) as total_records,
    SUM(CASE 
        WHEN employment_history::text LIKE '%""%' 
          OR skills_assessment::text LIKE '%""%'
          OR skills_assessment_numeric::text LIKE '%""%'
          OR skills_assessment_qualitative::text LIKE '%""%'
        THEN 1 ELSE 0 
    END) as bad_records,
    ROUND(
        100.0 * SUM(CASE 
            WHEN employment_history::text LIKE '%""%' 
              OR skills_assessment::text LIKE '%""%'
              OR skills_assessment_numeric::text LIKE '%""%'
              OR skills_assessment_qualitative::text LIKE '%""%'
            THEN 1 ELSE 0 
        END) / COUNT(*), 
        2
    ) as bad_percentage
FROM maids;

-- 2. CHECK FOR NULL/EMPTY JSON FIELDS
-- ============================================
SELECT 
    'Null/Empty JSON Fields' as check_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN employment_history IS NULL THEN 1 ELSE 0 END) as null_employment,
    SUM(CASE WHEN skills_assessment_numeric IS NULL THEN 1 ELSE 0 END) as null_skills_numeric,
    SUM(CASE WHEN skills_assessment_qualitative IS NULL THEN 1 ELSE 0 END) as null_skills_qualitative
FROM maids;

-- 3. CHECK DATA COMPLETENESS
-- ============================================
SELECT 
    'Data Completeness' as check_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN name IS NOT NULL AND name != '' THEN 1 ELSE 0 END) as has_name,
    SUM(CASE WHEN date_of_birth IS NOT NULL THEN 1 ELSE 0 END) as has_dob,
    SUM(CASE WHEN country_id IS NOT NULL THEN 1 ELSE 0 END) as has_nationality,
    SUM(CASE WHEN photo_url IS NOT NULL AND photo_url != '' THEN 1 ELSE 0 END) as has_photo
FROM maids;

-- 4. CHECK RECENT DATA QUALITY (Last 7 days)
-- ============================================
SELECT 
    'Recent Data Quality (7 days)' as check_name,
    COUNT(*) as total_new_records,
    SUM(CASE 
        WHEN employment_history::text LIKE '%""%' 
          OR skills_assessment_numeric::text LIKE '%""%'
        THEN 1 ELSE 0 
    END) as bad_new_records,
    MIN(created_at) as oldest_record,
    MAX(created_at) as newest_record
FROM maids
WHERE created_at >= CURRENT_DATE - INTERVAL '7 days';

-- 5. SAMPLE BAD RECORDS (if any)
-- ============================================
SELECT 
    id,
    name,
    LEFT(employment_history::text, 80) as employment_sample,
    LEFT(skills_assessment_numeric::text, 80) as skills_sample,
    created_at
FROM maids
WHERE employment_history::text LIKE '%""%' 
   OR skills_assessment_numeric::text LIKE '%""%'
LIMIT 5;

-- 6. DATABASE SIZE AND PERFORMANCE
-- ============================================
SELECT 
    'Database Statistics' as info,
    pg_size_pretty(pg_total_relation_size('maids')) as table_size,
    (SELECT COUNT(*) FROM maids) as total_records,
    (SELECT COUNT(*) FROM maids WHERE created_at >= CURRENT_DATE - INTERVAL '30 days') as records_last_30_days;

-- 7. INDEX USAGE CHECK
-- ============================================
SELECT 
    indexname,
    idx_scan as times_used,
    idx_tup_read as tuples_read,
    idx_tup_fetch as tuples_fetched
FROM pg_stat_user_indexes
WHERE schemaname = 'public' AND tablename = 'maids'
ORDER BY idx_scan DESC;
