-- E6E2F.1: inspect all financial returns of original sales in the late distribution-return groups.
-- Replace placeholder with local original invoice item IDs. No writes.
;WITH Seeds AS (
 SELECT InvoiceItemID FROM (VALUES (CAST(0 AS bigint))) v(InvoiceItemID)
), Links AS (
 SELECT DISTINCT s.InvoiceItemID,e.TargetRef AS ReturnInvoiceItemID
 FROM Seeds s JOIN SYS3.RecursiveEntityRelation e
 ON e.SourceType=906 AND e.SourceRef=s.InvoiceItemID AND e.TargetType=914
)
SELECT s.InvoiceItemID,sp.PartRef,sh.Date AS OriginalInvoiceDate,
 si.MajorUnitQuantity AS OriginalQty,si.PlantRef AS OriginalPlantRef,
 l.ReturnInvoiceItemID,rh.Date AS FinancialReturnDate,
 ri.MajorUnitQuantity AS FinancialReturnQty,rh.PlantRef AS ReturnPlantRef,
 rh.Status AS ReturnHeaderStatus,ri.Status AS ReturnItemStatus,
 ri.ReferenceType AS ReturnReferenceType,ri.ReferenceRef AS ReturnReferenceRef,ri.IsWaste,
 CASE WHEN l.ReturnInvoiceItemID IS NULL THEN 'NoFinancialReturnRelation'
 WHEN ri.ReturnInvoiceItemID IS NULL OR rh.ReturnInvoiceID IS NULL THEN 'MissingReturnDocument'
 WHEN rh.Status IS NULL OR rh.Status<>11 OR ri.Status IS NULL OR ri.Status<>11 THEN 'InvalidStatus'
 WHEN ri.CanceledBy IS NOT NULL OR ri.CanceledDate IS NOT NULL THEN 'CanceledReturn'
 WHEN rh.CurrencyRef IS NULL OR rh.CurrencyRef<>1 THEN 'CurrencyMismatch'
 WHEN rp.PartRef IS NULL OR rp.PartRef<>sp.PartRef THEN 'PartMismatch'
 WHEN ri.MajorUnitQuantity IS NULL OR ri.MajorUnitQuantity<=0 OR ri.EffectiveNetPrice IS NULL OR ri.EffectiveNetPrice<0 THEN 'InvalidFinancialValues'
 WHEN rh.Date IS NULL OR rh.Date<sh.Date THEN 'InvalidDate'
 WHEN rh.Date>='20261125' THEN 'OutsideSnapshot'
 ELSE 'ValidFinancialReturn' END AS FinancialReturnCheck
FROM Seeds s
JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=s.InvoiceItemID
JOIN SLS3.Invoice sh ON sh.InvoiceID=si.InvoiceRef
JOIN SLS3.Product sp ON sp.ProductID=si.ProductRef
LEFT JOIN Links l ON l.InvoiceItemID=s.InvoiceItemID
LEFT JOIN SLS3.ReturnInvoiceItem ri ON ri.ReturnInvoiceItemID=l.ReturnInvoiceItemID
LEFT JOIN SLS3.ReturnInvoice rh ON rh.ReturnInvoiceID=ri.ReturnInvoiceRef
LEFT JOIN SLS3.Product rp ON rp.ProductID=ri.ProductRef
ORDER BY sp.PartRef,s.InvoiceItemID,rh.Date,l.ReturnInvoiceItemID OPTION(MAXDOP 1);
-- Return quantities must be deduplicated and checked for competing original sales before cost reversal.
