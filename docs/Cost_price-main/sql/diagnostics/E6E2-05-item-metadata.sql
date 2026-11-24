-- E6E2E: discover item-level relationship metadata; read-only.
SELECT SCHEMA_NAME(t.schema_id) AS SchemaName,t.name AS TableName,c.column_id AS ColumnID,c.name AS ColumnName,ty.name AS DataType,c.max_length,c.precision,c.scale,c.is_nullable,
 OBJECT_SCHEMA_NAME(fkc.referenced_object_id) AS ReferencedSchema,
 OBJECT_NAME(fkc.referenced_object_id) AS ReferencedTable,
 COL_NAME(fkc.referenced_object_id,fkc.referenced_column_id) AS ReferencedColumn
FROM sys.tables t
JOIN sys.columns c ON c.object_id=t.object_id
JOIN sys.types ty ON ty.user_type_id=c.user_type_id
LEFT JOIN sys.foreign_key_columns fkc ON fkc.parent_object_id=c.object_id AND fkc.parent_column_id=c.column_id
WHERE (SCHEMA_NAME(t.schema_id)='LGS3' AND t.name IN ('ReserveItem','ReserveDelivery'))
 OR (SCHEMA_NAME(t.schema_id)='DSD3' AND t.name LIKE 'Assignment%' AND (c.name LIKE '%Ref%' OR c.name LIKE '%ID%' OR c.name LIKE '%Quantity%' OR c.name LIKE '%Type%'))
 OR (SCHEMA_NAME(t.schema_id)='SLS3' AND t.name='OrderItem' AND (c.name LIKE '%Ref%' OR c.name LIKE '%Quantity%' OR c.name LIKE '%Type%'))
ORDER BY SchemaName,TableName,ColumnID OPTION(MAXDOP 1);
