-- E6E2A.1 standalone: target invoices with multiple distribution candidates
-- Error 208: AllocationControl was a statement-scoped CTE, not a database object.
-- Replaces the dependent seed snippet. No writes; not yet tested.
;WITH TargetReturns AS
(
    SELECT DISTINCT e.TargetRef AS ReturnItemID
    FROM LGS3.InventoryVoucherItem vi
    JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
    JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
    JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
    JOIN SYS3.EntityRelation e ON e.SourceType=669 AND e.SourceRef=vi.InventoryVoucherItemID AND e.TargetType=914
    JOIN SLS3.ReturnInvoiceItem ri ON ri.ReturnInvoiceItemID=e.TargetRef
    JOIN SLS3.ReturnInvoice rh ON rh.ReturnInvoiceID=ri.ReturnInvoiceRef
    JOIN SLS3.Product p ON p.ProductID=ri.ProductRef
    WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
      AND iv.InventoryVoucherSpecificationRef=18
      AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
      AND vi.ReturnableVoucherItemRef IS NULL
      AND vi.Date>='20220818' AND vi.Date<'20261125'
      AND rh.Status=11 AND ri.Status=11
      AND ri.CanceledBy IS NULL AND ri.CanceledDate IS NULL
      AND rh.PlantRef=2 AND rh.CurrencyRef=1 AND rh.Date IS NOT NULL
      AND ri.MajorUnitQuantity>0 AND ri.EffectiveNetPrice>=0 AND p.PartRef IS NOT NULL
),
OriginalIDs AS
(
    SELECT DISTINCT e.SourceRef AS InvoiceItemID
    FROM TargetReturns t
    JOIN SYS3.RecursiveEntityRelation e ON e.SourceType=906 AND e.TargetType=914 AND e.TargetRef=t.ReturnItemID
),
Sales AS
(
    SELECT si.InvoiceItemID, p.PartRef, oi.OrderRef AS OrderID
    FROM OriginalIDs t
    JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=t.InvoiceItemID
    JOIN SLS3.Invoice sh ON sh.InvoiceID=si.InvoiceRef
    JOIN SLS3.Product p ON p.ProductID=si.ProductRef
    JOIN SLS3.OrderItem oi ON oi.OrderItemID=si.ReferenceRef AND oi.ProductRef=si.ProductRef
    WHERE si.PlantRef=2 AND si.ReferenceType=4
      AND NOT EXISTS
      (
          SELECT 1 FROM SYS3.RecursiveEntityRelation e
          WHERE e.SourceType=669 AND e.TargetType=906 AND e.TargetRef=si.InvoiceItemID
      )
),
Pairs AS
(
    SELECT DISTINCT s.InvoiceItemID, vi.InventoryVoucherItemID AS DeliveryID,
           vi.PartRef, vi.StoreRef, vi.Date AS DeliveryDate,
           CAST(vi.MajorUnitQuantity AS decimal(28,6)) AS GrossQty
    FROM Sales s
    JOIN DSD3.AssignmentDeliveryItem adi ON adi.OrderRef=s.OrderID
    JOIN DSD3.Assignment a ON a.AssignmentID=adi.AssignmentRef
    JOIN LGS3.Reserve rv ON rv.ReferenceType=1036 AND rv.ReferenceRef=a.AssignmentID
    JOIN LGS3.ReserveItem rvi ON rvi.ReserveRef=rv.ReserveID
    JOIN LGS3.ReserveDelivery rd ON rd.ReserveItemRef=rvi.ReserveItemID
    JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=rd.InventoryVoucherItemRef AND vi.PartRef=s.PartRef
    JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
    JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
    JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
    WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
      AND iv.InventoryVoucherSpecificationRef=10005
      AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
      AND vi.MajorUnitQuantity>0 AND vi.Date>='20220818' AND vi.Date<'20261125'
),
Counted AS
(
    SELECT *, COUNT_BIG(*) OVER(PARTITION BY InvoiceItemID) AS DeliveryCount
    FROM Pairs
)
SELECT DeliveryID, PartRef, StoreRef, DeliveryDate, GrossQty,
       COUNT_BIG(*) AS TargetMultiInvoiceLinks,
       MIN(InvoiceItemID) AS SampleInvoiceItemID,
       COUNT_BIG(*) OVER() AS UniqueSeedCount
FROM Counted
WHERE DeliveryCount>1
GROUP BY DeliveryID, PartRef, StoreRef, DeliveryDate, GrossQty
ORDER BY PartRef, DeliveryID
OPTION (MAXDOP 1);
-- Expected ~32 seed deliveries / 40 links from 20 target invoices.
-- This is a seed set, not complete graph closure or proof of allocation.
-- Expand all competing invoices and deliveries separately with literal IDs.
