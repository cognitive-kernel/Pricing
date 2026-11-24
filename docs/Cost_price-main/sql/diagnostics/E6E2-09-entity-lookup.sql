-- E6E2E.5: resolve entity names, read-only; not yet tested.
SELECT Code AS EntityCode, EntityName FROM SYS3.EntityLookup WHERE Code IN (637,638,639,669,720,727,742,906,914,935,1036) ORDER BY Code;

SELECT TOP (100) Code AS EntityCode, EntityName FROM SYS3.EntityLookup WHERE EntityName LIKE '%Delivery%' OR EntityName LIKE '%Reserve%' OR EntityName LIKE '%Assignment%' OR EntityName LIKE '%Tracking%' OR EntityName LIKE '%Permit%' OR EntityName LIKE '%Batch%' ORDER BY Code;
