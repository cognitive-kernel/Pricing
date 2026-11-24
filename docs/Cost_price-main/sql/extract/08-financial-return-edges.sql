-- Replace 0 locally with selected PartIDs.
;WITH Seed AS (SELECT PartID FROM (VALUES (CAST(0 AS bigint))) v(PartID))
SELECT DISTINCT e.SourceRef AS OriginalInvoiceItemID,e.TargetRef AS ReturnInvoiceItemID
FROM Seed s JOIN SLS3.Product p ON p.PartRef=s.PartID
JOIN SLS3.ReturnInvoiceItem ri ON ri.ProductRef=p.ProductID
JOIN SLS3.ReturnInvoice h ON h.ReturnInvoiceID=ri.ReturnInvoiceRef
JOIN SYS3.RecursiveEntityRelation e ON e.TargetType=914 AND e.TargetRef=ri.ReturnInvoiceItemID AND e.SourceType=906
WHERE h.Date>='20220818' AND h.Date<'20261125'
OPTION(MAXDOP 1);