-- H.4
;WITH D AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,vi.PartRef,vi.Date AS DeliveryDate,
 vi.MajorUnitQuantity AS DeliveryQty,a.AssignmentID
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
JOIN LGS3.IssuePermitItem ipi ON vi.ReferenceType=4 AND ipi.IssuePermitItemID=vi.ReferenceRef
JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
JOIN DSD3.Assignment a ON a.AssignmentID=ip.ReferenceRef AND a.PlantRef=2
WHERE st.PlantRef=2 AND h.LedgerRef=1 AND h.State<>3 AND h.InventoryVoucherSpecificationRef=10005
AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
AND ip.ReferenceType=9 AND ip.IssuePermitType=6 AND ip.PlantRef=2 AND ip.LedgerRef=1
AND ipi.State=4 AND ip.State=4 AND ipi.PartRef=vi.PartRef
 AND NOT EXISTS (SELECT 1 FROM LGS3.ReserveDelivery rd WHERE rd.InventoryVoucherItemRef=vi.InventoryVoucherItemID)
), E AS (
 SELECT DISTINCT d.DeliveryID,si.InvoiceItemID
 FROM D d JOIN DSD3.AssignmentDeliveryItem ad ON ad.AssignmentRef=d.AssignmentID
 JOIN SLS3.OrderItem oi ON oi.OrderRef=ad.OrderRef
 JOIN SLS3.Product p ON p.ProductID=oi.ProductRef AND p.PartRef=d.PartRef
 JOIN SLS3.InvoiceItem si ON si.ReferenceType=4 AND si.ReferenceRef=oi.OrderItemID AND si.ProductRef=oi.ProductRef
 JOIN SLS3.Invoice ih ON ih.InvoiceID=si.InvoiceRef
 WHERE si.Status=11 AND ih.Status=11 AND si.CanceledBy IS NULL AND si.CanceledDate IS NULL
 AND si.PlantRef=2 AND ih.PlantRef=2 AND ih.CurrencyRef=1 AND si.MajorUnitQuantity>0 AND si.EffectiveNetPrice>=0
 AND ih.Date>=d.DeliveryDate AND ih.Date<'20261125'
), A AS (
 SELECT e.DeliveryID,COUNT_BIG(*) AS InvoiceCount,SUM(si.MajorUnitQuantity) AS InvoiceQty
 FROM E e JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=e.InvoiceItemID GROUP BY e.DeliveryID
), C AS (
 SELECT d.*,COALESCE(a.InvoiceCount,0) AS InvoiceCount,a.InvoiceQty,
 CASE WHEN a.InvoiceCount IS NULL THEN 'NoCandidate'
 WHEN a.InvoiceCount>1 THEN 'MultipleCandidates'
 WHEN ABS(a.InvoiceQty-d.DeliveryQty)<=0.000001 THEN 'SingleGrossExact'
 ELSE 'SingleGrossDifference' END AS CandidateStatus
 FROM D d LEFT JOIN A a ON a.DeliveryID=d.DeliveryID
)
SELECT CandidateStatus,COUNT_BIG(*) AS DeliveryCount,SUM(InvoiceCount) AS CandidateLinkCount,
 COUNT(DISTINCT AssignmentID) AS AssignmentCount,MIN(DeliveryID) AS SampleDeliveryID
FROM C GROUP BY CandidateStatus ORDER BY CandidateStatus OPTION(MAXDOP 1);

-- H.5
;WITH D AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,vi.PartRef,vi.MajorUnitQuantity AS Qty,
 ipi.IssuePermitItemID,ipi.MajorUnitQuantity AS PermitQty,ipi.MaxMajorUnitQuantity AS MaxPermitQty,
 ipi.IssuePermitTolerancePercent AS TolerancePercent,h.FiscalYearRef
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
JOIN LGS3.IssuePermitItem ipi ON vi.ReferenceType=4 AND ipi.IssuePermitItemID=vi.ReferenceRef
JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
JOIN DSD3.Assignment a ON a.AssignmentID=ip.ReferenceRef AND a.PlantRef=2
WHERE st.PlantRef=2 AND h.LedgerRef=1 AND h.State<>3 AND h.InventoryVoucherSpecificationRef=10005
AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
AND ip.ReferenceType=9 AND ip.IssuePermitType=6 AND ip.PlantRef=2 AND ip.LedgerRef=1
AND ipi.State=4 AND ip.State=4 AND ipi.PartRef=vi.PartRef
 AND vi.MajorUnitQuantity>ipi.MajorUnitQuantity+0.000001
), R AS (
 SELECT d.*,r.ReturnCount,r.ReturnQty
 FROM D d OUTER APPLY (
 SELECT COUNT_BIG(*) AS ReturnCount,SUM(v.MajorUnitQuantity) AS ReturnQty
 FROM LGS3.InventoryVoucherItem v
 JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=v.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=v.StoreRef
 WHERE v.ReturnableVoucherItemRef=d.DeliveryID AND h.InventoryVoucherSpecificationRef=10012
 AND h.LedgerRef=1 AND h.State<>3 AND s.Direction=1 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
 AND v.MajorUnitQuantity>0 AND st.PlantRef=2 AND v.Date<'20261125'
 ) r
), C AS (
 SELECT *,Qty-COALESCE(ReturnQty,0) AS NetQty,
 CASE WHEN Qty-COALESCE(ReturnQty,0)>COALESCE(MaxPermitQty,PermitQty)+0.000001 THEN 'NetAboveMax'
 WHEN Qty-COALESCE(ReturnQty,0)>PermitQty+0.000001 THEN 'NetAbovePermit'
 WHEN ABS(Qty-COALESCE(ReturnQty,0)-PermitQty)<=0.000001 THEN 'NetEqualsPermit'
 ELSE 'NetBelowPermit' END AS NetStatus
 FROM R
)
SELECT FiscalYearRef,NetStatus,COUNT_BIG(*) AS DeliveryCount,
 SUM(CASE WHEN ReturnCount>0 THEN 1 ELSE 0 END) AS WithReturns,
 MAX(Qty-PermitQty) AS MaxGrossExcess,MAX(NetQty-PermitQty) AS MaxNetDifference,
 MIN(DeliveryID) AS SampleDeliveryID
FROM C GROUP BY FiscalYearRef,NetStatus ORDER BY FiscalYearRef,NetStatus OPTION(MAXDOP 1);

-- H.6
;WITH D AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,vi.PartRef,vi.MajorUnitQuantity AS Qty,vi.Date AS DeliveryDate,a.AssignmentID
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
JOIN LGS3.IssuePermitItem ipi ON vi.ReferenceType=4 AND ipi.IssuePermitItemID=vi.ReferenceRef
JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
JOIN DSD3.Assignment a ON a.AssignmentID=ip.ReferenceRef AND a.PlantRef=2
WHERE st.PlantRef=2 AND h.LedgerRef=1 AND h.State<>3 AND h.InventoryVoucherSpecificationRef=10005
AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
AND ip.ReferenceType=9 AND ip.IssuePermitType=6 AND ip.PlantRef=2 AND ip.LedgerRef=1
AND ipi.State=4 AND ip.State=4 AND ipi.PartRef=vi.PartRef
 AND h.FiscalYearRef=7
), G AS (
 SELECT AssignmentID,PartRef,COUNT_BIG(*) AS DeliveryCount,SUM(Qty) AS GrossQty,
 MIN(DeliveryDate) AS FirstDeliveryDate,MAX(DeliveryDate) AS LastDeliveryDate
 FROM D GROUP BY AssignmentID,PartRef
), O AS (
 SELECT DISTINCT g.AssignmentID,g.PartRef,oi.OrderItemID,oi.ProductRef,g.FirstDeliveryDate
 FROM G g JOIN DSD3.AssignmentDeliveryItem ad ON ad.AssignmentRef=g.AssignmentID
 JOIN SLS3.OrderItem oi ON oi.OrderRef=ad.OrderRef
 JOIN SLS3.Product p ON p.ProductID=oi.ProductRef AND p.PartRef=g.PartRef
), E AS (
 SELECT DISTINCT o.AssignmentID,o.PartRef,si.InvoiceItemID
 FROM O o JOIN SLS3.InvoiceItem si ON si.ReferenceType=4 AND si.ReferenceRef=o.OrderItemID AND si.ProductRef=o.ProductRef
 JOIN SLS3.Invoice ih ON ih.InvoiceID=si.InvoiceRef
 WHERE si.Status=11 AND ih.Status=11 AND si.CanceledBy IS NULL AND si.CanceledDate IS NULL
 AND si.PlantRef=2 AND ih.PlantRef=2 AND ih.CurrencyRef=1 AND si.MajorUnitQuantity>0 AND si.EffectiveNetPrice>=0
 AND ih.Date>=o.FirstDeliveryDate AND ih.Date<'20261125'
), I AS (
 SELECT e.AssignmentID,e.PartRef,COUNT_BIG(*) AS InvoiceCount,SUM(si.MajorUnitQuantity) AS InvoiceQty
 FROM E e JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=e.InvoiceItemID
 GROUP BY e.AssignmentID,e.PartRef
), C AS (
 SELECT g.*,COALESCE(i.InvoiceCount,0) AS InvoiceCount,i.InvoiceQty,
 CASE WHEN i.InvoiceCount IS NULL THEN 'NoCandidate'
 WHEN ABS(i.InvoiceQty-g.GrossQty)<=0.000001 THEN 'GrossExact'
 WHEN i.InvoiceQty>g.GrossQty THEN 'InvoiceGreater'
 ELSE 'DeliveryGreater' END AS QtyStatus
 FROM G g LEFT JOIN I i ON i.AssignmentID=g.AssignmentID AND i.PartRef=g.PartRef
)
SELECT QtyStatus,COUNT_BIG(*) AS GroupCount,SUM(DeliveryCount) AS DeliveryCount,SUM(InvoiceCount) AS CandidateInvoiceCount,
 SUM(CASE WHEN DeliveryCount>1 THEN 1 ELSE 0 END) AS MultiDeliveryGroups,
 SUM(CASE WHEN InvoiceCount>1 THEN 1 ELSE 0 END) AS MultiInvoiceGroups,
 MIN(AssignmentID) AS SampleAssignmentID
FROM C GROUP BY QtyStatus ORDER BY QtyStatus OPTION(MAXDOP 1);
