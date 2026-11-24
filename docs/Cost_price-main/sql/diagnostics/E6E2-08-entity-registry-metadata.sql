-- E6E2E.4: discover entity registry metadata, without assuming registry table names.
SELECT SCHEMA_NAME(t.schema_id) AS SchemaName,t.name AS TableName,
 c.column_id AS ColumnID,c.name AS ColumnName,ty.name AS DataType
FROM sys.tables t
JOIN sys.columns c ON c.object_id=t.object_id
JOIN sys.types ty ON ty.user_type_id=c.user_type_id
WHERE SCHEMA_NAME(t.schema_id) IN ('SYS3','GNR3')
 AND (t.name LIKE '%Entity%' OR t.name LIKE '%BusinessObject%')
 AND (c.name LIKE '%Type%' OR c.name LIKE '%Name%' OR c.name LIKE '%ID%' OR c.name LIKE '%Code%' OR c.name LIKE '%Table%' OR c.name LIKE '%Schema%' OR c.name LIKE '%Ref%')
 AND t.name NOT IN ('EntityRelation','RecursiveEntityRelation')
ORDER BY SchemaName,TableName,ColumnID OPTION(MAXDOP 1);
