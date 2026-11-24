-- G.8
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.InventoryVoucherSpecificationRef AS VoucherType,
 iv.FiscalYearRef,vi.PartRef,vi.StoreRef,vi.Date,vi.MajorUnitQuantity AS Qty
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef IN (69,10005)
), P AS (
 SELECT r.*,p.BaseQty,p.BaseValue,p.NetValue,p.OtherRows
 FROM R r CROSS APPLY (
 SELECT SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN Quantity END) AS BaseQty,
 SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN NetPrice END) AS BaseValue,
 SUM(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) THEN NetPrice END) AS NetValue,
 COUNT_BIG(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) AND NetPrice IS NOT NULL
 AND ((PriceType IN(1,8) AND NetPrice>=0 AND Effect=1) OR (PriceType=9 AND NetPrice<=0 AND Effect=-1)) THEN NULL ELSE 1 END) AS OtherRows
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.MovementID
 HAVING COUNT_BIG(*)>0
 ) p
), C AS (
 SELECT *,CASE WHEN OtherRows>0 THEN 'OtherComponentReview'
 WHEN BaseValue IS NULL OR BaseValue<=0 OR NetValue IS NULL OR NetValue<=0 THEN 'BaseOrNetReview'
 WHEN Qty IS NULL OR Qty<=0 OR BaseQty IS NULL OR ABS(BaseQty-Qty)>0.000001 THEN 'QuantityReview'
 ELSE 'OK' END AS PriceStatus FROM P
)
SELECT c.*,ip.ItemPriceID,ip.IsInput,ip.PriceType,ip.Quantity AS PriceQty,ip.NetPrice,ip.Effect,
 ip.HasReference,ip.IsReturn,ip.FeeReferenceCode,ip.FeeReferenceRef,ip.CardexRef,ip.VoucherItemRef
FROM C c JOIN LGS3.ItemPrice ip ON ip.InventoryVoucherItemRef=c.MovementID
WHERE c.PriceStatus<>'OK'
ORDER BY c.VoucherType,c.MovementID,ip.ItemPriceID OPTION(MAXDOP 1);

-- G.9
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
), I AS (
 SELECT p.*,i.InvoiceCount,i.InvoiceQty,i.InvoiceNet,i.InvalidRows
 FROM P p OUTER APPLY (
 SELECT COUNT_BIG(*) AS InvoiceCount,SUM(ii.MajorUnitQuantity) AS InvoiceQty,SUM(ii.NetPrice) AS InvoiceNet,
 COUNT_BIG(CASE WHEN h.State=3 AND h.Type=1 AND h.CurrencyRef=1 AND ii.MajorUnitQuantity>0 AND ii.NetPrice>=0 AND pi.PartRef=p.PartRef THEN NULL ELSE 1 END) AS InvalidRows
 FROM PRC3.InvoiceItem ii JOIN PRC3.Invoice h ON h.InvoiceID=ii.InvoiceRef
 LEFT JOIN PRC3.PurchaseItem pi ON pi.PurchaseItemID=ii.PurchaseItemRef
 WHERE ii.ReferenceType=3 AND ii.ReferenceRef=p.MovementID AND h.State=3 AND h.Type=1 AND h.CurrencyRef=1
 ) i
), C AS (
 SELECT *,CASE WHEN Base2>0 THEN 'Type2' ELSE 'Type3' END AS BaseType,
 CASE WHEN Base2>0 THEN Base2 ELSE Base3 END AS BaseValue,
 CASE WHEN Base2>0 THEN NewValue ELSE LegacyValue END AS CandidateNet,
 CASE WHEN InvoiceCount=0 THEN 'NoApprovedInvoice'
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

-- H.1
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,vi.PartRef,vi.MajorUnitQuantity AS Qty,
 vi.ReferenceType,vi.ReferenceRef,vi.Date,iv.FiscalYearRef
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef=10005
 -- AND iv.FiscalYearRef=7
), C AS (
 SELECT r.*,ipi.IssuePermitItemID,ip.IssuePermitID,a.AssignmentID,
 CASE WHEN r.ReferenceType IS NULL OR r.ReferenceType<>4 THEN 'OtherReferenceType'
 WHEN ipi.IssuePermitItemID IS NULL THEN 'MissingPermitItem'
 WHEN ip.IssuePermitID IS NULL THEN 'MissingPermitHeader'
 WHEN ipi.PartRef IS NULL OR ipi.PartRef<>r.PartRef THEN 'PartMismatch'
 WHEN ipi.MajorUnitQuantity IS NULL OR r.Qty IS NULL OR ABS(ipi.MajorUnitQuantity-r.Qty)>0.000001 THEN 'QuantityReview'
 WHEN ip.ReferenceType IS NULL OR ip.ReferenceType<>9 OR ip.IssuePermitType IS NULL OR ip.IssuePermitType<>6 THEN 'OtherPermitType'
 WHEN ip.PlantRef IS NULL OR ip.PlantRef<>2 OR ip.LedgerRef IS NULL OR ip.LedgerRef<>1 THEN 'PermitScopeReview'
 WHEN ipi.State IS NULL OR ipi.State<>4 OR ip.State IS NULL OR ip.State<>4 THEN 'PermitStateReview'
 WHEN a.AssignmentID IS NULL THEN 'MissingAssignment'
 ELSE 'ShortPathCandidate' END AS PathStatus
 FROM R r LEFT JOIN LGS3.IssuePermitItem ipi ON ipi.IssuePermitItemID=r.ReferenceRef AND r.ReferenceType=4
 LEFT JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
 LEFT JOIN DSD3.Assignment a ON a.AssignmentID=ip.ReferenceRef AND ip.ReferenceType=9 AND a.PlantRef=2
)
SELECT FiscalYearRef,PathStatus,COUNT_BIG(*) AS DeliveryCount,COUNT(DISTINCT IssuePermitItemID) AS PermitItemCount,
 COUNT(DISTINCT AssignmentID) AS AssignmentCount,MIN(DeliveryID) AS SampleDeliveryID
FROM C GROUP BY FiscalYearRef,PathStatus ORDER BY FiscalYearRef,PathStatus OPTION(MAXDOP 1);
