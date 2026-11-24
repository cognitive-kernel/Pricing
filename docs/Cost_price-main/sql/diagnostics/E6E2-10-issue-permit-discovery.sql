-- E6E2E.6A: discover physical issue permit tables and FK metadata.
SELECT SCHEMA_NAME(t.schema_id) AS SchemaName,t.name AS TableName,c.column_id AS ColumnID,c.name AS ColumnName,ty.name AS DataType,c.is_nullable,
 OBJECT_SCHEMA_NAME(f.referenced_object_id) AS ReferencedSchema,OBJECT_NAME(f.referenced_object_id) AS ReferencedTable,COL_NAME(f.referenced_object_id,f.referenced_column_id) AS ReferencedColumn
FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id
JOIN sys.types ty ON ty.user_type_id=c.user_type_id
LEFT JOIN sys.foreign_key_columns f ON f.parent_object_id=c.object_id AND f.parent_column_id=c.column_id
WHERE t.name IN ('IssuePermit','IssuePermitItem')
ORDER BY SchemaName,TableName,ColumnID OPTION(MAXDOP 1);

-- E6E2E.6B: replace placeholder with local IssuePermitItem IDs (delivery ReferenceRef).
;WITH Seeds AS (
 SELECT PermitItemID FROM (VALUES (CAST(0 AS bigint))) v(PermitItemID)
), Edges AS (
 SELECT s.PermitItemID,'Incoming' AS RelationDirection,e.SourceType AS RelatedEntityType,e.SourceRef AS RelatedEntityID
 FROM Seeds s JOIN SYS3.EntityRelation e ON e.TargetType=639 AND e.TargetRef=s.PermitItemID
 UNION
 SELECT s.PermitItemID,'Outgoing',e.TargetType,e.TargetRef
 FROM Seeds s JOIN SYS3.EntityRelation e ON e.SourceType=639 AND e.SourceRef=s.PermitItemID
), Totals AS (
 SELECT RelationDirection,RelatedEntityType,COUNT_BIG(*) AS RelationCount,COUNT(DISTINCT PermitItemID) AS PermitItemCount,COUNT(DISTINCT RelatedEntityID) AS RelatedItemCount
 FROM Edges GROUP BY RelationDirection,RelatedEntityType
)
SELECT t.*,l.EntityName FROM Totals t LEFT JOIN SYS3.EntityLookup l ON l.Code=t.RelatedEntityType
ORDER BY t.RelationDirection,t.RelatedEntityType OPTION(MAXDOP 1);
