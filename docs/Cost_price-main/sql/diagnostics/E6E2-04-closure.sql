-- E6E2D: check full forward candidate scope for the 31 local invoice IDs.
-- Replace both placeholder lists with local IDs before execution.
;WITH InvoiceSeeds AS (
 SELECT InvoiceItemID FROM (VALUES (CAST(0 AS bigint))) v(InvoiceItemID)
), DeliverySeeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
), I AS (
 SELECT si.InvoiceItemID,si.PlantRef,p.PartRef,sh.Date AS InvoiceDate,
 oi.OrderRef AS OrderID
 FROM InvoiceSeeds s JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=s.InvoiceItemID
 JOIN SLS3.Invoice sh ON sh.InvoiceID=si.InvoiceRef
 JOIN SLS3.Product p ON p.ProductID=si.ProductRef
 LEFT JOIN SLS3.OrderItem oi ON si.ReferenceType=4 AND oi.OrderItemID=si.ReferenceRef AND oi.ProductRef=si.ProductRef
), CandidatePairs AS (
 SELECT i.InvoiceItemID,rd.InventoryVoucherItemRef AS DeliveryID
 FROM I i JOIN DSD3.AssignmentDeliveryItem adi ON adi.OrderRef=i.OrderID
 JOIN DSD3.Assignment a ON a.AssignmentID=adi.AssignmentRef
 JOIN LGS3.Reserve rv ON rv.ReferenceType=1036 AND rv.ReferenceRef=a.AssignmentID
 JOIN LGS3.ReserveItem rvi ON rvi.ReserveRef=rv.ReserveID
 JOIN LGS3.ReserveDelivery rd ON rd.ReserveItemRef=rvi.ReserveItemID
 JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=rd.InventoryVoucherItemRef AND vi.PartRef=i.PartRef
 UNION
 SELECT i.InvoiceItemID,e.SourceRef FROM I i JOIN SYS3.RecursiveEntityRelation e
 ON e.SourceType=669 AND e.TargetType=906 AND e.TargetRef=i.InvoiceItemID
), Checked AS (
 SELECT c.InvoiceItemID,c.DeliveryID,vi.Date AS DeliveryDate,
 CASE WHEN vi.InventoryVoucherItemID IS NOT NULL AND iv.InventoryVoucherSpecificationRef IN(69,10005)
 AND iv.LedgerRef=1 AND iv.State<>3 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND vi.PartRef=i.PartRef AND st.PlantRef=i.PlantRef AND vi.MajorUnitQuantity>0
 AND vi.Date>='20220818' AND vi.Date<'20261125' THEN 1 ELSE 0 END AS IsValidPhysical,
 CASE WHEN s.DeliveryID IS NULL THEN 1 ELSE 0 END AS IsOutsideSeed,
 CASE WHEN vi.Date>i.InvoiceDate THEN 1 ELSE 0 END AS IsAfterInvoice
 FROM CandidatePairs c JOIN I i ON i.InvoiceItemID=c.InvoiceItemID
 LEFT JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=c.DeliveryID
 LEFT JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 LEFT JOIN DeliverySeeds s ON s.DeliveryID=c.DeliveryID
)
SELECT i.PartRef,i.InvoiceItemID,
 COUNT(c.DeliveryID) AS CandidateCount,
 SUM(CASE WHEN c.IsValidPhysical=1 AND c.IsAfterInvoice=0 THEN 1 ELSE 0 END) AS ValidAtInvoiceCount,
 SUM(CASE WHEN c.IsValidPhysical=1 AND c.IsAfterInvoice=0 AND c.IsOutsideSeed=1 THEN 1 ELSE 0 END) AS OutsideSeedAtInvoiceCount,
 SUM(CASE WHEN c.IsValidPhysical=1 AND c.IsAfterInvoice=1 THEN 1 ELSE 0 END) AS LaterDeliveryCount,
 SUM(CASE WHEN c.IsValidPhysical=0 THEN 1 ELSE 0 END) AS InvalidCandidateCount,
 MIN(CASE WHEN c.IsValidPhysical=1 AND c.IsOutsideSeed=1 AND c.IsAfterInvoice=0 THEN c.DeliveryID END) AS SampleOutsideDeliveryID
FROM I i LEFT JOIN Checked c ON c.InvoiceItemID=i.InvoiceItemID
GROUP BY i.PartRef,i.InvoiceItemID ORDER BY i.PartRef,i.InvoiceItemID OPTION(MAXDOP 1);
-- Expected 31 rows, 62 valid at-invoice links if no extra candidates.
-- A nonzero outside count requires reverse expansion to all competing invoices.
-- Later deliveries are diagnostic only, not historical cost allocation.
