-- E6E2F.9: compare referenced distribution return component values with original output.
-- Read-only. Component sums are hypotheses, not accepted valuation.
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS ReturnID,vi.ReturnableVoucherItemRef AS OriginalID,
 vi.PartRef,vi.StoreRef,st.PlantRef,vi.Date AS ReturnDate,vi.MajorUnitQuantity AS ReturnQty,iv.FiscalYearRef
 FROM LGS3.InventoryVoucherItem vi
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE iv.InventoryVoucherSpecificationRef=10012 AND st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
 AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND vi.Date>='20220818' AND vi.Date<'20261125' AND vi.ReturnableVoucherItemRef IS NOT NULL
), V AS (
 SELECT r.*,o.MajorUnitQuantity AS OriginalQty,
 CASE WHEN o.InventoryVoucherItemID IS NOT NULL AND oh.InventoryVoucherSpecificationRef=10005
 AND oh.LedgerRef=1 AND oh.State<>3 AND os.Direction=2 AND os.TypeOfEffectOnStock=1 AND os.Category<>13
 AND o.PartRef=r.PartRef AND ost.PlantRef=r.PlantRef AND o.Date<=r.ReturnDate
 AND o.MajorUnitQuantity>0 AND r.ReturnQty>0 AND r.ReturnQty<=o.MajorUnitQuantity THEN 1 ELSE 0 END AS ValidOriginal,
 rp.BaseRows,rp.BaseQty,rp.BaseValue,rp.AddValue,rp.DeductValue,rp.UnexpectedRows,
 op.BaseRows AS OriginalBaseRows,op.BaseQty AS OriginalBaseQty,op.BaseValue AS OriginalBaseValue,
 op.AddValue AS OriginalAddValue,op.DeductValue AS OriginalDeductValue,op.UnexpectedRows AS OriginalUnexpectedRows
 FROM R r
 LEFT JOIN LGS3.InventoryVoucherItem o ON o.InventoryVoucherItemID=r.OriginalID
 LEFT JOIN LGS3.InventoryVoucher oh ON oh.InventoryVoucherID=o.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification os ON os.InventoryVoucherSpecificationID=oh.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store ost ON ost.StoreID=o.StoreRef
 OUTER APPLY (
 SELECT COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN 1 END) AS BaseRows,
 SUM(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN ip.Quantity END) AS BaseQty,
 SUM(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN ip.NetPrice END) AS BaseValue,
 SUM(CASE WHEN ip.IsInput=1 AND ip.PriceType=8 THEN ip.NetPrice ELSE 0 END) AS AddValue,
 SUM(CASE WHEN ip.IsInput=1 AND ip.PriceType=9 THEN ip.NetPrice ELSE 0 END) AS DeductValue,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType IN(1,8,9) AND ip.IsReturn=1 AND ip.HasReference=1 AND ip.NetPrice IS NOT NULL AND ((ip.PriceType IN(1,8) AND ip.Effect=1 AND ip.NetPrice>0) OR (ip.PriceType=9 AND ip.Effect=-1 AND ip.NetPrice<0)) THEN NULL ELSE 1 END) AS UnexpectedRows
 FROM LGS3.ItemPrice ip WHERE ip.InventoryVoucherItemRef=r.ReturnID
 ) rp
 OUTER APPLY (
 SELECT COUNT_BIG(CASE WHEN ip.IsInput=0 AND ip.PriceType=1 THEN 1 END) AS BaseRows,
 SUM(CASE WHEN ip.IsInput=0 AND ip.PriceType=1 THEN ip.Quantity END) AS BaseQty,
 SUM(CASE WHEN ip.IsInput=0 AND ip.PriceType=1 THEN ip.NetPrice END) AS BaseValue,
 SUM(CASE WHEN ip.IsInput=0 AND ip.PriceType=8 THEN ip.NetPrice ELSE 0 END) AS AddValue,
 SUM(CASE WHEN ip.IsInput=0 AND ip.PriceType=9 THEN ip.NetPrice ELSE 0 END) AS DeductValue,
 COUNT_BIG(CASE WHEN ip.IsInput=0 AND ip.PriceType IN(1,8,9) AND ip.NetPrice IS NOT NULL THEN NULL ELSE 1 END) AS UnexpectedRows
 FROM LGS3.ItemPrice ip WHERE ip.InventoryVoucherItemRef=r.OriginalID
 ) op
), D AS (
 SELECT *, CASE WHEN ValidOriginal=1 AND OriginalBaseRows>0 AND OriginalBaseValue>0 AND ABS(OriginalBaseQty-OriginalQty)<=0.000001
 AND BaseRows>0 AND BaseValue>0 AND ABS(BaseQty-ReturnQty)<=0.000001 AND UnexpectedRows=0 AND OriginalUnexpectedRows=0 THEN 1 ELSE 0 END AS Comparable,
 CAST(OriginalBaseValue AS decimal(28,6))/NULLIF(CAST(OriginalQty AS decimal(28,6)),0)*ReturnQty AS ExpectedBase,
 CAST(OriginalBaseValue+COALESCE(OriginalAddValue,0)+COALESCE(OriginalDeductValue,0) AS decimal(28,6))/NULLIF(CAST(OriginalQty AS decimal(28,6)),0)*ReturnQty AS ExpectedNet,
 BaseValue+COALESCE(AddValue,0)+COALESCE(DeductValue,0) AS ReturnNet
 FROM V
), C AS (
 SELECT *,CASE WHEN ValidOriginal=0 THEN 'InvalidOriginal'
 WHEN Comparable=0 THEN 'PriceNeedsReview'
 WHEN ABS(BaseValue-ExpectedBase)<=1 AND ABS(ReturnNet-ExpectedNet)<=1 THEN 'BaseAndNetMatch'
 WHEN ABS(BaseValue-ExpectedBase)<=1 THEN 'BaseOnlyMatch'
 WHEN ABS(ReturnNet-ExpectedNet)<=1 THEN 'NetOnlyMatch'
 ELSE 'NeitherMatch' END AS MatchStatus FROM D
)
SELECT FiscalYearRef,MatchStatus,COUNT_BIG(*) AS ReturnCount,COUNT(DISTINCT OriginalID) AS OriginalCount,
 SUM(CASE WHEN Comparable=1 THEN ABS(BaseValue-ExpectedBase) END) AS AbsoluteBaseDifference,
 SUM(CASE WHEN Comparable=1 THEN ABS(ReturnNet-ExpectedNet) END) AS AbsoluteNetDifference,
 MIN(ReturnID) AS SampleReturnID,MIN(OriginalID) AS SampleOriginalID
FROM C GROUP BY FiscalYearRef,MatchStatus ORDER BY FiscalYearRef,MatchStatus OPTION(MAXDOP 1);
-- Proportional original value is diagnostic for partial returns, not proof of exact lot lineage.
-- Cumulative return quantity and cross-plant/store dependency checks remain separate.
