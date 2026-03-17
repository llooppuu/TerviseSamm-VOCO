-- UTF8 Fix

USE vormipaevik;

UPDATE activities
SET name = CONVERT(CAST(CONVERT(name USING latin1) AS BINARY) USING utf8mb4)
WHERE name LIKE '%Ã%' OR name LIKE '%Â%';

UPDATE users
SET name = CONVERT(CAST(CONVERT(name USING latin1) AS BINARY) USING utf8mb4)
WHERE name LIKE '%Ã%' OR name LIKE '%Â%';

UPDATE `groups`
SET name = CONVERT(CAST(CONVERT(name USING latin1) AS BINARY) USING utf8mb4)
WHERE name LIKE '%Ã%' OR name LIKE '%Â%';
