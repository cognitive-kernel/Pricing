-- Replace 0 locally; keep all plants for selected parts.
;WITH Seed AS (SELECT PartID FROM (VALUES (CAST(0 AS bigint))) v(PartID))
SELECT 'Sale' AS EventType,si.InvoiceItemID AS EventID,h.InvoiceID AS HeaderID,
 p.PartRef,si.ProductRef,h.PlantRef,h.Date AS FinancialDate,h.Status AS HeaderStatus,
 si.Status AS ItemStatus,h.CurrencyRef,si.MajorUnitQuantity AS Qty,si.EffectiveNetPrice AS Revenue,
 si.ReferenceType,si.ReferenceRef,si.CanceledBy,si.CanceledDate,CAST(NULL AS int) AS IsWaste
FROM SLS3.InvoiceItem si JOIN SLS3.Invoice h ON h.InvoiceID=si.InvoiceRef
JOIN SLS3.Product p ON p.ProductID=si.ProductRef JOIN Seed s ON s.PartID=p.PartRef
WHERE h.Date>='20220818' AND h.Date<'20261125'
UNION ALL
SELECT 'Return',ri.ReturnInvoiceItemID,h.ReturnInvoiceID,p.PartRef,ri.ProductRef,h.PlantRef,h.Date,
 h.Status,ri.Status,h.CurrencyRef,ri.MajorUnitQuantity,ri.EffectiveNetPrice,ri.ReferenceType,ri.ReferenceRef,
 ri.CanceledBy,ri.CanceledDate,CAST(ri.IsWaste AS int)
FROM SLS3.ReturnInvoiceItem ri JOIN SLS3.ReturnInvoice h ON h.ReturnInvoiceID=ri.ReturnInvoiceRef
JOIN SLS3.Product p ON p.ProductID=ri.ProductRef JOIN Seed s ON s.PartID=p.PartRef
WHERE h.Date>='20220818' AND h.Date<'20261125'
OPTION(MAXDOP 1);