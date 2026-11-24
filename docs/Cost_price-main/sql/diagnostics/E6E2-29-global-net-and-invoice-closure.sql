-- H.11
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

), DR AS (
 SELECT d.*,r.ReturnQty
 FROM D d OUTER APPLY (
 SELECT SUM(v.MajorUnitQuantity) AS ReturnQty
 FROM LGS3.InventoryVoucherItem v
 JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=v.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=v.StoreRef
 WHERE v.ReturnableVoucherItemRef=d.DeliveryID AND h.InventoryVoucherSpecificationRef=10012
 AND h.LedgerRef=1 AND h.State<>3 AND s.Direction=1 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
 AND st.PlantRef=2 AND v.PartRef=d.PartRef AND v.MajorUnitQuantity>0
 AND v.Date>=d.DeliveryDate AND v.Date<'20261125'
 ) r
), G AS (
 SELECT AssignmentID,PartRef,COUNT_BIG(*) AS DeliveryCount,SUM(Qty) AS GrossQty,SUM(COALESCE(ReturnQty,0)) AS ReturnedQty,
 MIN(DeliveryDate) AS FirstDeliveryDate,MAX(DeliveryDate) AS LastDeliveryDate
 FROM DR GROUP BY AssignmentID,PartRef
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
SELECT QtyStatus,
 CASE WHEN ReturnedQty>GrossQty+0.000001 THEN 'OverReturnReview'
 WHEN InvoiceCount=0 AND ABS(GrossQty-ReturnedQty)<=0.000001 THEN 'NoInvoiceZeroNet'
 WHEN InvoiceCount=0 THEN 'NoInvoicePositiveNet'
 WHEN ABS(InvoiceQty-(GrossQty-ReturnedQty))<=0.000001 THEN 'NetExact'
 WHEN InvoiceQty>GrossQty-ReturnedQty THEN 'InvoiceGreaterThanNet'
 ELSE 'NetDeliveryGreater' END AS NetStatus,
 COUNT_BIG(*) AS GroupCount,SUM(DeliveryCount) AS DeliveryCount,
 SUM(InvoiceCount) AS CandidateInvoiceCount,
 SUM(CASE WHEN ReturnedQty>0 THEN 1 ELSE 0 END) AS GroupsWithReturns,
 MIN(AssignmentID) AS SampleAssignmentID
FROM C GROUP BY QtyStatus,
 CASE WHEN ReturnedQty>GrossQty+0.000001 THEN 'OverReturnReview'
 WHEN InvoiceCount=0 AND ABS(GrossQty-ReturnedQty)<=0.000001 THEN 'NoInvoiceZeroNet'
 WHEN InvoiceCount=0 THEN 'NoInvoicePositiveNet'
 WHEN ABS(InvoiceQty-(GrossQty-ReturnedQty))<=0.000001 THEN 'NetExact'
 WHEN InvoiceQty>GrossQty-ReturnedQty THEN 'InvoiceGreaterThanNet'
 ELSE 'NetDeliveryGreater' END
ORDER BY QtyStatus,NetStatus OPTION(MAXDOP 1);

-- H.12
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
), T AS (
 SELECT DISTINCT InvoiceItemID FROM E
), A AS (
 SELECT DISTINCT t.InvoiceItemID,a.AssignmentID
 FROM T t JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=t.InvoiceItemID
 JOIN SLS3.OrderItem oi ON si.ReferenceType=4 AND oi.OrderItemID=si.ReferenceRef AND oi.ProductRef=si.ProductRef
 JOIN DSD3.AssignmentDeliveryItem ad ON ad.OrderRef=oi.OrderRef
 JOIN DSD3.Assignment a ON a.AssignmentID=ad.AssignmentRef AND a.PlantRef=2
), V AS (
 SELECT DISTINCT a.InvoiceItemID,a.AssignmentID
 FROM A a JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=a.InvoiceItemID
 JOIN SLS3.Invoice ih ON ih.InvoiceID=si.InvoiceRef
 JOIN SLS3.Product p ON p.ProductID=si.ProductRef
 JOIN LGS3.IssuePermit ip ON ip.ReferenceType=9 AND ip.ReferenceRef=a.AssignmentID AND ip.IssuePermitType=6 AND ip.PlantRef=2 AND ip.LedgerRef=1 AND ip.State=4
 JOIN LGS3.IssuePermitItem ipi ON ipi.IssuePermitRef=ip.IssuePermitID AND ipi.PartRef=p.PartRef AND ipi.State=4
 JOIN LGS3.InventoryVoucherItem vi ON vi.ReferenceType=4 AND vi.ReferenceRef=ipi.IssuePermitItemID AND vi.PartRef=p.PartRef
 JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE h.InventoryVoucherSpecificationRef=10005 AND h.LedgerRef=1 AND h.State<>3
 AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13 AND st.PlantRef=2
 AND vi.MajorUnitQuantity>0 AND vi.Date<=ih.Date AND vi.Date<'20261125'
), C AS (
 SELECT t.InvoiceItemID,COUNT(DISTINCT v.AssignmentID) AS AssignmentCount
 FROM T t LEFT JOIN V v ON v.InvoiceItemID=t.InvoiceItemID GROUP BY t.InvoiceItemID
)
SELECT AssignmentCount,COUNT_BIG(*) AS InvoiceItemCount,MIN(InvoiceItemID) AS SampleInvoiceItemID
FROM C GROUP BY AssignmentCount ORDER BY AssignmentCount OPTION(MAXDOP 1);

-- H.13
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
), T AS (
 SELECT DISTINCT InvoiceItemID FROM E
), DirectEdges AS (
 SELECT DISTINCT t.InvoiceItemID,vi.InventoryVoucherItemID AS MovementID,
 h.InventoryVoucherSpecificationRef AS VoucherType
 FROM T t
 JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=t.InvoiceItemID
 JOIN SLS3.Invoice ih ON ih.InvoiceID=si.InvoiceRef
 JOIN SLS3.Product p ON p.ProductID=si.ProductRef
 JOIN SYS3.RecursiveEntityRelation er ON er.TargetType=906 AND er.TargetRef=t.InvoiceItemID AND er.SourceType=669
 JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=er.SourceRef AND vi.PartRef=p.PartRef
 JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE h.InventoryVoucherSpecificationRef IN(69,10005) AND h.LedgerRef=1 AND h.State<>3
 AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
 AND st.PlantRef=2 AND vi.MajorUnitQuantity>0 AND vi.Date<=ih.Date AND vi.Date<'20261125'
), A AS (
 SELECT InvoiceItemID,
 SUM(CASE WHEN VoucherType=69 THEN 1 ELSE 0 END) AS Direct69Count,
 SUM(CASE WHEN VoucherType=10005 THEN 1 ELSE 0 END) AS DirectDistributionCount
 FROM DirectEdges GROUP BY InvoiceItemID
), C AS (
 SELECT t.InvoiceItemID,COALESCE(a.Direct69Count,0) AS Direct69Count,
 COALESCE(a.DirectDistributionCount,0) AS DirectDistributionCount
 FROM T t LEFT JOIN A a ON a.InvoiceItemID=t.InvoiceItemID
)
SELECT CASE WHEN Direct69Count>0 AND DirectDistributionCount>0 THEN 'BothTypes'
 WHEN Direct69Count>0 THEN 'Direct69Only'
 WHEN DirectDistributionCount>0 THEN 'Direct10005Only'
 ELSE 'NoDirectRecursiveLink' END AS RelationStatus,
 COUNT_BIG(*) AS InvoiceItemCount,SUM(CAST(Direct69Count AS bigint)) AS Direct69Links,
 SUM(CAST(DirectDistributionCount AS bigint)) AS DirectDistributionLinks,
 MIN(InvoiceItemID) AS SampleInvoiceItemID
FROM C GROUP BY CASE WHEN Direct69Count>0 AND DirectDistributionCount>0 THEN 'BothTypes'
 WHEN Direct69Count>0 THEN 'Direct69Only'
 WHEN DirectDistributionCount>0 THEN 'Direct10005Only'
 ELSE 'NoDirectRecursiveLink' END ORDER BY RelationStatus OPTION(MAXDOP 1);
