-- E6E2B: standalone, replace example VALUES with local seed IDs.
;WITH Seeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
),
D AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID, vi.PartRef, vi.StoreRef,
 st.PlantRef, vi.Date AS DeliveryDate, CAST(vi.MajorUnitQuantity AS decimal(28,6)) AS GrossQty
 FROM Seeds s JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=s.DeliveryID
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE iv.InventoryVoucherSpecificationRef=10005 AND iv.LedgerRef=1 AND iv.State<>3
 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND st.PlantRef=2 AND vi.MajorUnitQuantity>0 AND vi.Date>='20220818' AND vi.Date<'20261125'
),
A AS (
 SELECT DISTINCT d.DeliveryID,d.PartRef,d.PlantRef,rv.ReferenceRef AS AssignmentID
 FROM D d JOIN LGS3.ReserveDelivery rd ON rd.InventoryVoucherItemRef=d.DeliveryID
 JOIN LGS3.ReserveItem rvi ON rvi.ReserveItemID=rd.ReserveItemRef
 JOIN LGS3.Reserve rv ON rv.ReserveID=rvi.ReserveRef AND rv.ReferenceType=1036
 JOIN DSD3.Assignment a ON a.AssignmentID=rv.ReferenceRef
),
Pairs AS (
 SELECT a.DeliveryID,si.InvoiceItemID
 FROM A a JOIN DSD3.AssignmentDeliveryItem adi ON adi.AssignmentRef=a.AssignmentID
 JOIN SLS3.OrderItem oi ON oi.OrderRef=adi.OrderRef
 JOIN SLS3.Product p ON p.ProductID=oi.ProductRef AND p.PartRef=a.PartRef
 JOIN SLS3.InvoiceItem si ON si.ReferenceType=4 AND si.ReferenceRef=oi.OrderItemID AND si.ProductRef=oi.ProductRef AND si.PlantRef=a.PlantRef
 UNION
 SELECT d.DeliveryID,e.TargetRef FROM D d JOIN SYS3.RecursiveEntityRelation e
 ON e.SourceType=669 AND e.SourceRef=d.DeliveryID AND e.TargetType=906
),
ValidLinks AS (
 SELECT d.DeliveryID,d.PartRef,d.StoreRef,d.DeliveryDate,d.GrossQty,
 si.InvoiceItemID,sh.Date AS InvoiceDate,si.ReferenceType,si.ReferenceRef,
 oi.OrderRef AS OrderID,CAST(si.MajorUnitQuantity AS decimal(28,6)) AS InvoiceQty
 FROM D d JOIN Pairs x ON x.DeliveryID=d.DeliveryID
 JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=x.InvoiceItemID
 JOIN SLS3.Invoice sh ON sh.InvoiceID=si.InvoiceRef
 JOIN SLS3.Product p ON p.ProductID=si.ProductRef AND p.PartRef=d.PartRef
 LEFT JOIN SLS3.OrderItem oi ON si.ReferenceType=4 AND oi.OrderItemID=si.ReferenceRef AND oi.ProductRef=si.ProductRef
 WHERE si.Status=11 AND sh.Status=11 AND si.CanceledBy IS NULL AND si.CanceledDate IS NULL
 AND sh.CurrencyRef=1 AND si.PlantRef=d.PlantRef AND sh.PlantRef=d.PlantRef
 AND si.MajorUnitQuantity>0 AND si.EffectiveNetPrice>=0
 AND sh.Date>=d.DeliveryDate AND sh.Date<'20261125'
)
SELECT *,COUNT_BIG(*) OVER(PARTITION BY DeliveryID) AS InvoicesPerDelivery,
 COUNT_BIG(*) OVER(PARTITION BY InvoiceItemID) AS SeedDeliveriesPerInvoice,
 COUNT_BIG(*) OVER() AS TotalLinks
FROM ValidLinks ORDER BY PartRef,DeliveryID,InvoiceItemID OPTION(MAXDOP 1);
-- Expected 62 links. Subsequent independent steps: distribution returns and full graph closure.
