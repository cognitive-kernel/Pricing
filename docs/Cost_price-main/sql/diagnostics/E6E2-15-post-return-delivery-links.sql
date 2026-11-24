-- V5.2E.6E.2F.4: targeted post-return distribution assignment and order candidates.
-- Replace 0 with local movement IDs. No writes. Candidate edges are not allocations.
;WITH Seeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
), D AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,vi.InventoryVoucherRef AS VoucherID,
 vi.PartRef,vi.StoreRef,st.PlantRef,vi.Date AS DeliveryDate,vi.MajorUnitQuantity AS DeliveryQty,
 p.IssuePermitItemID,p.IssuePermitRef,h.ReferenceType AS PermitReferenceType,
 h.IssuePermitType,h.ReferenceRef AS PermitAssignmentRef,a.AssignmentID
 FROM Seeds s
 JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=s.DeliveryID
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 LEFT JOIN LGS3.IssuePermitItem p ON vi.ReferenceType=4 AND p.IssuePermitItemID=vi.ReferenceRef AND p.PartRef=vi.PartRef
 LEFT JOIN LGS3.IssuePermit h ON h.IssuePermitID=p.IssuePermitRef
 LEFT JOIN DSD3.Assignment a ON h.ReferenceType=9 AND h.IssuePermitType=6 AND a.AssignmentID=h.ReferenceRef AND a.PlantRef=st.PlantRef
 WHERE iv.InventoryVoucherSpecificationRef=10005 AND iv.LedgerRef=1 AND iv.State<>3
 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
), O AS (
 SELECT DISTINCT d.DeliveryID,adi.AssignmentDeliveryItemID,adi.OrderRef,adi.InvoiceRef AS AssignmentInvoiceRef,
 adi.NotDeliveredType,oi.OrderItemID,oi.ProductRef,oi.MajorUnitQuantity AS OrderQty
 FROM D d JOIN DSD3.AssignmentDeliveryItem adi ON adi.AssignmentRef=d.AssignmentID
 JOIN SLS3.OrderItem oi ON oi.OrderRef=adi.OrderRef
 JOIN SLS3.Product p ON p.ProductID=oi.ProductRef AND p.PartRef=d.PartRef
)
SELECT d.*,o.AssignmentDeliveryItemID,o.OrderRef,o.AssignmentInvoiceRef,o.NotDeliveredType,o.OrderItemID,o.OrderQty,
 si.InvoiceItemID,si.InvoiceRef,sh.Date AS InvoiceDate,si.MajorUnitQuantity AS InvoiceQty,
 si.Status AS InvoiceItemStatus,sh.Status AS InvoiceHeaderStatus,si.PlantRef AS InvoicePlantRef,
 CASE WHEN si.InvoiceItemID IS NULL THEN 'NoInvoiceCandidate'
 WHEN si.Status=11 AND sh.Status=11 AND si.CanceledBy IS NULL AND si.CanceledDate IS NULL
 AND sh.CurrencyRef=1 AND si.PlantRef=d.PlantRef AND sh.PlantRef=d.PlantRef
 AND si.MajorUnitQuantity>0 AND si.EffectiveNetPrice>=0 AND sh.Date>=d.DeliveryDate AND sh.Date<'20261125'
 THEN 'ValidInvoiceCandidate' ELSE 'InvoiceNeedsReview' END AS InvoiceCheck,
 CASE WHEN o.AssignmentInvoiceRef=si.InvoiceRef THEN 1 ELSE 0 END AS AssignmentInvoiceMatch
FROM D d LEFT JOIN O o ON o.DeliveryID=d.DeliveryID
LEFT JOIN SLS3.InvoiceItem si ON si.ReferenceType=4 AND si.ReferenceRef=o.OrderItemID AND si.ProductRef=o.ProductRef
LEFT JOIN SLS3.Invoice sh ON sh.InvoiceID=si.InvoiceRef
ORDER BY d.VoucherID,d.PartRef,d.DeliveryID,o.OrderItemID,si.InvoiceItemID OPTION(MAXDOP 1);
-- Next: inspect reverse relations and distribution returns for any candidate new deliveries.
-- No inference of compensating delivery solely from matching quantities/dates.
