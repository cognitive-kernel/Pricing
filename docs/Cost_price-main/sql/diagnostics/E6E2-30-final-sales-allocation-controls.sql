-- H.14
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
 SELECT e.AssignmentID,e.PartRef,COUNT_BIG(*) AS InvoiceCount,SUM(si.MajorUnitQuantity) AS InvoiceQty,MIN(ih.Date) AS FirstInvoiceDate,MAX(ih.Date) AS LastInvoiceDate
 FROM E e JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=e.InvoiceItemID JOIN SLS3.Invoice ih ON ih.InvoiceID=si.InvoiceRef
 GROUP BY e.AssignmentID,e.PartRef
), C AS (
 SELECT g.*,COALESCE(i.InvoiceCount,0) AS InvoiceCount,i.InvoiceQty,i.FirstInvoiceDate,i.LastInvoiceDate,
 CASE WHEN i.InvoiceCount IS NULL THEN 'NoCandidate'
 WHEN ABS(i.InvoiceQty-g.GrossQty)<=0.000001 THEN 'GrossExact'
 WHEN i.InvoiceQty>g.GrossQty THEN 'InvoiceGreater'
 ELSE 'DeliveryGreater' END AS QtyStatus
 FROM G g LEFT JOIN I i ON i.AssignmentID=g.AssignmentID AND i.PartRef=g.PartRef
)
,
T AS (
 SELECT * FROM C WHERE InvoiceCount>0 AND InvoiceQty>GrossQty-ReturnedQty+0.000001
), R AS (
 SELECT t.AssignmentID,t.PartRef,
 SUM(CASE WHEN v.Date<=t.FirstInvoiceDate THEN v.MajorUnitQuantity ELSE 0 END) AS ReturnThroughFirst,
 SUM(CASE WHEN v.Date<=t.LastInvoiceDate THEN v.MajorUnitQuantity ELSE 0 END) AS ReturnThroughLast,
 SUM(CASE WHEN v.Date>t.LastInvoiceDate THEN v.MajorUnitQuantity ELSE 0 END) AS ReturnAfterLast
 FROM T t JOIN D d ON d.AssignmentID=t.AssignmentID AND d.PartRef=t.PartRef
 JOIN LGS3.InventoryVoucherItem v ON v.ReturnableVoucherItemRef=d.DeliveryID AND v.PartRef=d.PartRef
 JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=v.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=v.StoreRef
 WHERE h.InventoryVoucherSpecificationRef=10012 AND h.LedgerRef=1 AND h.State<>3 AND s.Direction=1 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
 AND st.PlantRef=2 AND v.MajorUnitQuantity>0 AND v.Date>=d.DeliveryDate AND v.Date<'20261125'
 GROUP BY t.AssignmentID,t.PartRef
), F AS (
 SELECT t.*,COALESCE(r.ReturnAfterLast,0) AS LateQty,
 CASE WHEN t.LastDeliveryDate>t.LastInvoiceDate THEN 'DeliveryAfterLastInvoiceReview'
 WHEN ABS(t.InvoiceQty-(t.GrossQty-COALESCE(r.ReturnThroughLast,0)))<=0.000001 THEN 'ExactThroughLastInvoice'
 WHEN ABS(t.InvoiceQty-(t.GrossQty-COALESCE(r.ReturnThroughFirst,0)))<=0.000001 THEN 'ExactThroughFirstInvoiceDiagnostic'
 ELSE 'StillMismatch' END AS TimingStatus
 FROM T t LEFT JOIN R r ON r.AssignmentID=t.AssignmentID AND r.PartRef=t.PartRef
)
SELECT QtyStatus,TimingStatus,COUNT_BIG(*) AS GroupCount,SUM(DeliveryCount) AS DeliveryCount,
 SUM(CASE WHEN LateQty>0 THEN 1 ELSE 0 END) AS GroupsWithLateReturns,
 MIN(AssignmentID) AS SampleAssignmentID
FROM F GROUP BY QtyStatus,TimingStatus ORDER BY QtyStatus,TimingStatus OPTION(MAXDOP 1);

-- H.15
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

SELECT CASE WHEN DeliveryCount=1 AND InvoiceCount=1 THEN 'OneToOne'
 WHEN DeliveryCount>1 AND InvoiceCount=1 THEN 'ManyToOne'
 WHEN DeliveryCount=1 AND InvoiceCount>1 THEN 'OneToMany'
 ELSE 'ManyToMany' END AS AllocationShape,
 COUNT_BIG(*) AS GroupCount,SUM(DeliveryCount) AS DeliveryCount,SUM(InvoiceCount) AS InvoiceCount,
 SUM(CASE WHEN ReturnedQty>0 THEN 1 ELSE 0 END) AS GroupsWithReturns,
 MIN(AssignmentID) AS SampleAssignmentID
FROM C WHERE InvoiceCount>0 AND ABS(InvoiceQty-(GrossQty-ReturnedQty))<=0.000001
GROUP BY CASE WHEN DeliveryCount=1 AND InvoiceCount=1 THEN 'OneToOne'
 WHEN DeliveryCount>1 AND InvoiceCount=1 THEN 'ManyToOne'
 WHEN DeliveryCount=1 AND InvoiceCount>1 THEN 'OneToMany'
 ELSE 'ManyToMany' END ORDER BY AllocationShape OPTION(MAXDOP 1);

-- H.16
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
,
T AS (
 SELECT * FROM C WHERE InvoiceCount=0 AND GrossQty-ReturnedQty>0.000001
), E2 AS (
 SELECT DISTINCT t.AssignmentID,t.PartRef,si.InvoiceItemID,
 CASE WHEN si.Status=11 AND ih.Status=11 AND si.CanceledBy IS NULL AND si.CanceledDate IS NULL
 AND si.PlantRef=2 AND ih.PlantRef=2 AND ih.CurrencyRef=1 AND si.MajorUnitQuantity>0 AND si.EffectiveNetPrice>=0
 AND ih.Date<'20261125' THEN 1 ELSE 0 END AS ValidInvoice
 FROM T t JOIN O o ON o.AssignmentID=t.AssignmentID AND o.PartRef=t.PartRef
 JOIN SYS3.RecursiveEntityRelation er ON er.SourceType=935 AND er.SourceRef=o.OrderItemID AND er.TargetType=906
 JOIN SLS3.InvoiceItem si ON si.InvoiceItemID=er.TargetRef AND si.ProductRef=o.ProductRef
 JOIN SLS3.Invoice ih ON ih.InvoiceID=si.InvoiceRef
), A AS (
 SELECT AssignmentID,PartRef,COUNT(DISTINCT InvoiceItemID) AS AllCandidateCount,
 COUNT(DISTINCT CASE WHEN ValidInvoice=1 THEN InvoiceItemID END) AS ValidCandidateCount
 FROM E2 GROUP BY AssignmentID,PartRef
), F AS (
 SELECT t.*,COALESCE(a.ValidCandidateCount,0) AS AlternativeCount,
 CASE WHEN a.ValidCandidateCount>0 THEN 'AlternativeOrderRelation'
 WHEN a.AllCandidateCount>0 THEN 'OnlyInvalidAlternative'
 ELSE 'NoAlternativeOrderRelation' END AS AlternativeStatus
 FROM T t LEFT JOIN A a ON a.AssignmentID=t.AssignmentID AND a.PartRef=t.PartRef
)
SELECT AlternativeStatus,COUNT_BIG(*) AS GroupCount,SUM(DeliveryCount) AS DeliveryCount,SUM(AlternativeCount) AS CandidateCount,
 MIN(AssignmentID) AS SampleAssignmentID
FROM F GROUP BY AlternativeStatus ORDER BY AlternativeStatus OPTION(MAXDOP 1);
