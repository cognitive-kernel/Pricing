-- G.9A (already executed successfully)
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.FiscalYearRef,vi.PartRef,vi.MajorUnitQuantity AS Qty,vi.Date AS ReceiptDate
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef=2
), P AS (
 SELECT r.*,p.Base2,p.Base3,p.LegacyValue,p.NewValue
 FROM R r CROSS APPLY (
 SELECT SUM(CASE WHEN IsInput=1 AND PriceType=2 THEN NetPrice END) AS Base2,
 SUM(CASE WHEN IsInput=1 AND PriceType=3 THEN NetPrice END) AS Base3,
 SUM(CASE WHEN IsInput=1 AND PriceType IN(3,6,7) THEN NetPrice END) AS LegacyValue,
 SUM(CASE WHEN IsInput=1 AND PriceType IN(2,4) THEN NetPrice END) AS NewValue
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.MovementID
 ) p WHERE (p.Base2>0 AND p.Base3 IS NULL) OR (p.Base3>0 AND p.Base2 IS NULL)
), InvoiceRows AS (
 SELECT p.MovementID,ii.InvoiceItemID,ii.MajorUnitQuantity AS InvoiceQty,ii.NetPrice AS InvoiceNet,
 CASE WHEN h.State=3 AND h.Type=1 AND h.CurrencyRef=1 AND ii.MajorUnitQuantity>0 AND ii.NetPrice>=0 AND pi.PartRef=p.PartRef THEN 0 ELSE 1 END AS InvalidRow
 FROM P p JOIN PRC3.InvoiceItem ii ON ii.ReferenceType=3 AND ii.ReferenceRef=p.MovementID
 JOIN PRC3.Invoice h ON h.InvoiceID=ii.InvoiceRef
 LEFT JOIN PRC3.PurchaseItem pi ON pi.PurchaseItemID=ii.PurchaseItemRef
 WHERE h.State=3 AND h.Type=1 AND h.CurrencyRef=1
), InvoiceAgg AS (
 SELECT MovementID,COUNT_BIG(*) AS InvoiceCount,SUM(InvoiceQty) AS InvoiceQty,SUM(InvoiceNet) AS InvoiceNet,SUM(InvalidRow) AS InvalidRows
 FROM InvoiceRows GROUP BY MovementID
), I AS (
 SELECT p.*,i.InvoiceCount,i.InvoiceQty,i.InvoiceNet,i.InvalidRows
 FROM P p LEFT JOIN InvoiceAgg i ON i.MovementID=p.MovementID
), C AS (
 SELECT *,CASE WHEN Base2>0 THEN 'Type2' ELSE 'Type3' END AS BaseType,
 CASE WHEN Base2>0 THEN Base2 ELSE Base3 END AS BaseValue,
 CASE WHEN Base2>0 THEN NewValue ELSE LegacyValue END AS CandidateNet,
 CASE WHEN COALESCE(InvoiceCount,0)=0 THEN 'NoApprovedInvoice'
 WHEN InvalidRows>0 OR Qty IS NULL OR Qty<=0 OR InvoiceQty IS NULL OR ABS(InvoiceQty-Qty)>0.000001 THEN 'InvoiceNeedsReview'
 ELSE 'Comparable' END AS InvoiceCheck
 FROM I
), D AS (
 SELECT *,CASE WHEN InvoiceCheck<>'Comparable' THEN InvoiceCheck
 WHEN ABS(BaseValue-InvoiceNet)<=1 AND ABS(CandidateNet-InvoiceNet)<=1 THEN 'BothMatch'
 WHEN ABS(BaseValue-InvoiceNet)<=1 THEN 'BaseOnlyMatch'
 WHEN ABS(CandidateNet-InvoiceNet)<=1 THEN 'NetOnlyMatch'
 ELSE 'NeitherMatch' END AS MatchStatus FROM C
)
SELECT FiscalYearRef,BaseType,MatchStatus,COUNT_BIG(*) AS MovementCount,
 SUM(CASE WHEN InvoiceCheck='Comparable' THEN ABS(BaseValue-InvoiceNet) END) AS AbsBaseDiff,
 SUM(CASE WHEN InvoiceCheck='Comparable' THEN ABS(CandidateNet-InvoiceNet) END) AS AbsNetDiff,
 MIN(MovementID) AS SampleMovementID
FROM D GROUP BY FiscalYearRef,BaseType,MatchStatus ORDER BY FiscalYearRef,BaseType,MatchStatus OPTION(MAXDOP 1);


-- H.2
;WITH T AS (
 SELECT DISTINCT ipi.IssuePermitItemID
 FROM LGS3.InventoryVoucherItem vi
 JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 JOIN LGS3.IssuePermitItem ipi ON vi.ReferenceType=4 AND ipi.IssuePermitItemID=vi.ReferenceRef
 WHERE st.PlantRef=2 AND h.LedgerRef=1 AND h.State<>3 AND h.InventoryVoucherSpecificationRef=10005
 AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
 AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND (ipi.MajorUnitQuantity IS NULL OR vi.MajorUnitQuantity IS NULL OR ABS(ipi.MajorUnitQuantity-vi.MajorUnitQuantity)>0.000001)
), R AS (
 SELECT t.IssuePermitItemID,ipi.MajorUnitQuantity AS PermitQty,ipi.MaxMajorUnitQuantity AS MaxPermitQty,
 ip.FiscalYearRef,
 CASE WHEN ip.ReferenceType=9 AND ip.IssuePermitType=6 AND ip.PlantRef=2 AND ip.LedgerRef=1
 AND ip.State=4 AND ipi.State=4 AND a.AssignmentID IS NOT NULL THEN 1 ELSE 0 END AS ValidPermit,
 vi.InventoryVoucherItemID AS DeliveryID,vi.MajorUnitQuantity AS DeliveryQty,
 CASE WHEN h.InventoryVoucherSpecificationRef=10005 AND h.LedgerRef=1 AND h.State<>3
 AND s.Direction=2 AND s.TypeOfEffectOnStock=1 AND s.Category<>13
 AND vi.PartRef=ipi.PartRef AND vi.MajorUnitQuantity>0 AND st.PlantRef=2
 AND vi.Date<'20261125' THEN 1 ELSE 0 END AS ValidDelivery,
 CASE WHEN vi.Date>='20220818' AND vi.Date<'20261125' AND st.PlantRef=2 THEN 1 ELSE 0 END AS InScope
 FROM T t
 JOIN LGS3.IssuePermitItem ipi ON ipi.IssuePermitItemID=t.IssuePermitItemID
 LEFT JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
 LEFT JOIN DSD3.Assignment a ON a.AssignmentID=ip.ReferenceRef AND a.PlantRef=2
 JOIN LGS3.InventoryVoucherItem vi ON vi.ReferenceType=4 AND vi.ReferenceRef=t.IssuePermitItemID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=vi.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification s ON s.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE vi.Date<'20261125' OR vi.Date IS NULL
), A AS (
 SELECT IssuePermitItemID,FiscalYearRef,PermitQty,MaxPermitQty,ValidPermit,
 SUM(ValidDelivery) AS DeliveryCount,
 SUM(CASE WHEN ValidDelivery=1 THEN DeliveryQty ELSE 0 END) AS TotalDeliveryQty,
 SUM(CASE WHEN ValidDelivery=0 THEN 1 ELSE 0 END) AS InvalidCount,
 SUM(CASE WHEN ValidDelivery=1 AND InScope=0 THEN 1 ELSE 0 END) AS ExtraCount,
 SUM(CASE WHEN ValidDelivery=1 AND InScope=1 AND (PermitQty IS NULL OR ABS(PermitQty-DeliveryQty)>0.000001) THEN 1 ELSE 0 END) AS TargetMismatchCount
 FROM R GROUP BY IssuePermitItemID,FiscalYearRef,PermitQty,MaxPermitQty,ValidPermit
), C AS (
 SELECT *,CASE WHEN ValidPermit=0 THEN 'PermitStructureReview'
 WHEN InvalidCount>0 THEN 'LinkedMovementReview'
 WHEN DeliveryCount=0 THEN 'NoValidDelivery'
 WHEN PermitQty IS NULL OR PermitQty<=0 THEN 'InvalidPermitQty'
 WHEN ABS(TotalDeliveryQty-PermitQty)<=0.000001 THEN 'CumulativeExact'
 WHEN TotalDeliveryQty<PermitQty THEN 'CumulativeLess'
 WHEN MaxPermitQty IS NOT NULL AND TotalDeliveryQty<=MaxPermitQty+0.000001 THEN 'WithinMaxPermitQty'
 ELSE 'CumulativeExceeds' END AS QtyStatus
 FROM A
)
SELECT FiscalYearRef,QtyStatus,COUNT_BIG(*) AS PermitCount,
 SUM(TargetMismatchCount) AS TargetMismatchCount,SUM(DeliveryCount) AS ValidDeliveryCount,
 SUM(InvalidCount) AS InvalidLinkedCount,SUM(ExtraCount) AS ExtraCount,
 SUM(CASE WHEN DeliveryCount>1 THEN 1 ELSE 0 END) AS MultipleDeliveryPermitCount,
 MAX(ABS(TotalDeliveryQty-PermitQty)) AS MaxAbsoluteQtyDifference,
 MIN(IssuePermitItemID) AS SamplePermitItemID
FROM C GROUP BY FiscalYearRef,QtyStatus ORDER BY FiscalYearRef,QtyStatus OPTION(MAXDOP 1);

-- H.3
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,iv.FiscalYearRef,vi.PartRef,
 vi.MajorUnitQuantity AS Qty,vi.ReferenceType,vi.ReferenceRef
 FROM LGS3.InventoryVoucherItem vi
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
 AND iv.InventoryVoucherSpecificationRef=10005 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND vi.Date>='20220818' AND vi.Date<'20261125'
 -- AND iv.FiscalYearRef=7
), P AS (
 SELECT r.*,a.AssignmentID AS PermitAssignmentID,
 CASE WHEN ipi.IssuePermitItemID IS NOT NULL AND ipi.PartRef=r.PartRef AND ipi.State=4 AND ip.State=4
 AND ip.ReferenceType=9 AND ip.IssuePermitType=6 AND ip.PlantRef=2 AND ip.LedgerRef=1
 AND a.AssignmentID IS NOT NULL THEN 1 ELSE 0 END AS ValidPermitPath
 FROM R r
 LEFT JOIN LGS3.IssuePermitItem ipi ON r.ReferenceType=4 AND ipi.IssuePermitItemID=r.ReferenceRef
 LEFT JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
 LEFT JOIN DSD3.Assignment a ON ip.ReferenceType=9 AND a.AssignmentID=ip.ReferenceRef AND a.PlantRef=2
), A AS (
 SELECT p.DeliveryID,p.FiscalYearRef,p.PermitAssignmentID,p.ValidPermitPath,
 COUNT_BIG(rd.ReserveDeliveryID) AS ReserveLinkCount,
 COUNT(DISTINCT a.AssignmentID) AS ReserveAssignmentCount,
 MIN(a.AssignmentID) AS ReserveAssignmentID,
 COUNT_BIG(CASE WHEN rd.ReserveDeliveryID IS NOT NULL AND (a.AssignmentID IS NULL OR ri.PartRef IS NULL OR ri.PartRef<>p.PartRef) THEN 1 END) AS InvalidReserveLinkCount
 FROM P p
 LEFT JOIN LGS3.ReserveDelivery rd ON rd.InventoryVoucherItemRef=p.DeliveryID
 LEFT JOIN LGS3.ReserveItem ri ON ri.ReserveItemID=rd.ReserveItemRef
 LEFT JOIN LGS3.Reserve rr ON rr.ReserveID=ri.ReserveRef
 LEFT JOIN DSD3.Assignment a ON rr.ReferenceType=1036 AND a.AssignmentID=rr.ReferenceRef AND a.PlantRef=2
 GROUP BY p.DeliveryID,p.FiscalYearRef,p.PermitAssignmentID,p.ValidPermitPath
), C AS (
 SELECT *,CASE WHEN ValidPermitPath=0 THEN 'PermitPathReview'
 WHEN ReserveLinkCount=0 THEN 'NoReserveLink'
 WHEN InvalidReserveLinkCount>0 THEN 'ReserveLinkReview'
 WHEN ReserveAssignmentCount>1 THEN 'MultipleReserveAssignments'
 WHEN ReserveAssignmentCount=0 THEN 'NoReserveAssignment'
 WHEN PermitAssignmentID=ReserveAssignmentID THEN 'SameAssignment'
 ELSE 'DifferentAssignment' END AS PathStatus
 FROM A
)
SELECT FiscalYearRef,PathStatus,COUNT_BIG(*) AS DeliveryCount,
 SUM(CASE WHEN ReserveLinkCount>1 THEN 1 ELSE 0 END) AS MultiReserveLinkCount,
 MAX(ReserveLinkCount) AS MaxReserveLinks,
 MIN(DeliveryID) AS SampleDeliveryID
FROM C GROUP BY FiscalYearRef,PathStatus ORDER BY FiscalYearRef,PathStatus OPTION(MAXDOP 1);
