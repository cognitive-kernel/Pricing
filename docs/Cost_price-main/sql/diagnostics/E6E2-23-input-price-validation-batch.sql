-- G.2 / G.3 / G.4. Independent SELECT statements. Stop each after 60 seconds.
-- Candidate valuation rules, not final acceptance. No raw business IDs.
-- G.2
;WITH R AS (
SELECT vi.InventoryVoucherItemID AS MovementID,iv.InventoryVoucherSpecificationRef AS VoucherType,
CASE WHEN vi.ReturnableVoucherItemRef IS NULL THEN 0 ELSE 1 END AS HasOriginalRef
FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
AND iv.InventoryVoucherSpecificationRef IN(5,17,18,19,24,10008)
)
SELECT r.VoucherType,r.HasOriginalRef,ip.IsInput,ip.PriceType,ip.IsReturn,ip.HasReference,ip.Effect,
COUNT(DISTINCT r.MovementID) AS MovementCount,COUNT_BIG(ip.ItemPriceID) AS PriceRowCount,
COUNT_BIG(CASE WHEN ip.NetPrice>0 THEN 1 END) AS PositiveRows,
COUNT_BIG(CASE WHEN ip.NetPrice=0 THEN 1 END) AS ZeroRows,
COUNT_BIG(CASE WHEN ip.NetPrice<0 THEN 1 END) AS NegativeRows,
COUNT_BIG(CASE WHEN ip.ItemPriceID IS NOT NULL AND ip.NetPrice IS NULL THEN 1 END) AS NullPriceRows,
SUM(ip.NetPrice) AS ComponentValue,MIN(r.MovementID) AS SampleMovementID
FROM R r LEFT JOIN LGS3.ItemPrice ip ON ip.InventoryVoucherItemRef=r.MovementID
GROUP BY r.VoucherType,r.HasOriginalRef,ip.IsInput,ip.PriceType,ip.IsReturn,ip.HasReference,ip.Effect
ORDER BY r.VoucherType,r.HasOriginalRef,ip.IsInput,ip.PriceType,ip.IsReturn,ip.HasReference,ip.Effect OPTION(MAXDOP 1);

-- G.3
;WITH R AS (
SELECT vi.InventoryVoucherItemID AS MovementID,iv.FiscalYearRef,vi.MajorUnitQuantity AS Qty
FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
AND iv.InventoryVoucherSpecificationRef=2
), P AS (
SELECT r.*,p.* FROM R r OUTER APPLY (
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=2 THEN 1 END) AS Rows2,
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=3 THEN 1 END) AS Rows3,
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=4 THEN 1 END) AS Rows4,
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=6 THEN 1 END) AS Rows6,
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=7 THEN 1 END) AS Rows7,
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType IN(2,3,4,6,7) THEN NULL ELSE 1 END) AS OtherRows,
COUNT_BIG(CASE WHEN IsInput=1 AND (NetPrice IS NULL OR Quantity IS NULL OR Quantity<=0) THEN 1 END) AS InvalidRows,
SUM(CASE WHEN IsInput=1 AND PriceType=2 THEN Quantity END) AS Base2Qty,
SUM(CASE WHEN IsInput=1 AND PriceType=3 THEN Quantity END) AS Base3Qty,
SUM(CASE WHEN IsInput=1 AND PriceType=2 THEN NetPrice END) AS Value2,
SUM(CASE WHEN IsInput=1 AND PriceType=3 THEN NetPrice END) AS Value3,
SUM(CASE WHEN IsInput=1 AND PriceType=4 THEN NetPrice END) AS Value4,
SUM(CASE WHEN IsInput=1 AND PriceType=6 THEN NetPrice END) AS Value6,
SUM(CASE WHEN IsInput=1 AND PriceType=7 THEN NetPrice END) AS Value7
FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.MovementID
) p
), C AS (
SELECT *,CASE WHEN Rows2>0 AND Rows3>0 THEN 'BothBaseTypesReview'
WHEN Rows2>0 THEN 'Type2BaseCandidate' WHEN Rows3>0 THEN 'Type3BaseCandidate'
WHEN Rows4+Rows6+Rows7+OtherRows=0 THEN 'NoPrice' ELSE 'NoRecognizedBase' END AS BasePattern,
CASE WHEN Rows4>0 THEN 1 ELSE 0 END AS HasType4,
CASE WHEN Rows6>0 THEN 1 ELSE 0 END AS HasType6,
CASE WHEN Rows7>0 THEN 1 ELSE 0 END AS HasType7,
CASE WHEN Qty>0 AND ((Rows2>0 AND Rows3=0 AND Value2>0 AND ABS(Base2Qty-Qty)<=0.000001)
OR (Rows3>0 AND Rows2=0 AND Value3>0 AND ABS(Base3Qty-Qty)<=0.000001)) THEN 1 ELSE 0 END AS BaseQtyMatch
FROM P
)
SELECT FiscalYearRef,BasePattern,HasType4,HasType6,HasType7,COUNT_BIG(*) AS MovementCount,
SUM(BaseQtyMatch) AS BaseQtyMatchCount,SUM(CASE WHEN OtherRows>0 OR InvalidRows>0 THEN 1 ELSE 0 END) AS ReviewCount,
SUM(Value2) AS Value2,SUM(Value3) AS Value3,SUM(Value4) AS Value4,SUM(Value6) AS Value6,SUM(Value7) AS Value7,MIN(MovementID) AS SampleMovementID
FROM C GROUP BY FiscalYearRef,BasePattern,HasType4,HasType6,HasType7
ORDER BY FiscalYearRef,BasePattern,HasType4,HasType6,HasType7 OPTION(MAXDOP 1);

-- G.4
;WITH R AS (
SELECT vi.InventoryVoucherItemID AS ReturnID,vi.ReturnableVoucherItemRef AS OriginalID,
iv.InventoryVoucherSpecificationRef AS VoucherType,vi.PartRef,vi.StoreRef,vi.Date AS ReturnDate,vi.MajorUnitQuantity AS ReturnQty
FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
AND iv.InventoryVoucherSpecificationRef IN(5,17,18,19,24) AND vi.ReturnableVoucherItemRef IS NOT NULL
), V AS (
SELECT r.*,o.MajorUnitQuantity AS OriginalQty,o.PartRef AS OriginalPartRef,ost.PlantRef AS OriginalPlantRef,
oh.InventoryVoucherSpecificationRef AS OriginalType,
CASE WHEN o.InventoryVoucherItemID IS NOT NULL AND oh.LedgerRef=1 AND oh.State<>3
AND os.Direction=2 AND os.TypeOfEffectOnStock=1 AND os.Category<>13 AND o.Date<=r.ReturnDate
AND o.MajorUnitQuantity>0 AND r.ReturnQty>0
AND ((r.VoucherType=5 AND oh.InventoryVoucherSpecificationRef=51) OR (r.VoucherType=17 AND oh.InventoryVoucherSpecificationRef=68) OR (r.VoucherType=18 AND oh.InventoryVoucherSpecificationRef=69) OR (r.VoucherType=19 AND oh.InventoryVoucherSpecificationRef=70) OR (r.VoucherType=24 AND oh.InventoryVoucherSpecificationRef=73))
AND (r.VoucherType=19 OR (o.PartRef=r.PartRef AND r.ReturnQty<=o.MajorUnitQuantity+0.000001))
THEN 1 ELSE 0 END AS ValidOriginal,
rp.BaseQty AS ReturnBaseQty,rp.BaseValue AS ReturnBaseValue,rp.NetValue AS ReturnNetValue,rp.OtherRows AS ReturnOtherRows,
op.BaseQty AS OriginalBaseQty,op.BaseValue AS OriginalBaseValue,op.NetValue AS OriginalNetValue,op.OtherRows AS OriginalOtherRows
FROM R r LEFT JOIN LGS3.InventoryVoucherItem o ON o.InventoryVoucherItemID=r.OriginalID
LEFT JOIN LGS3.InventoryVoucher oh ON oh.InventoryVoucherID=o.InventoryVoucherRef
LEFT JOIN LGS3.InventoryVoucherSpecification os ON os.InventoryVoucherSpecificationID=oh.InventoryVoucherSpecificationRef
LEFT JOIN LGS3.Store ost ON ost.StoreID=o.StoreRef
OUTER APPLY (
SELECT SUM(CASE WHEN IsInput=1 AND PriceType=1 THEN Quantity END) AS BaseQty,
SUM(CASE WHEN IsInput=1 AND PriceType=1 THEN NetPrice END) AS BaseValue,
SUM(CASE WHEN IsInput=1 AND PriceType IN(1,8,9) THEN NetPrice END) AS NetValue,
COUNT_BIG(CASE WHEN IsInput=1 AND PriceType IN(1,8,9) AND NetPrice IS NOT NULL
AND ((PriceType IN(1,8) AND NetPrice>0 AND Effect=1) OR (PriceType=9 AND NetPrice<0 AND Effect=-1)) THEN NULL ELSE 1 END) AS OtherRows
FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.ReturnID
) rp
OUTER APPLY (
SELECT SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN Quantity END) AS BaseQty,
SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN NetPrice END) AS BaseValue,
SUM(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) THEN NetPrice END) AS NetValue,
COUNT_BIG(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) AND NetPrice IS NOT NULL THEN NULL ELSE 1 END) AS OtherRows
FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.OriginalID
) op
), D AS (
SELECT *,CASE WHEN ValidOriginal=1 AND VoucherType<>19 AND ReturnBaseValue>0 AND OriginalBaseValue>0
AND ABS(ReturnBaseQty-ReturnQty)<=0.000001 AND ABS(OriginalBaseQty-OriginalQty)<=0.000001
AND ReturnOtherRows=0 AND OriginalOtherRows=0 THEN 1 ELSE 0 END AS Comparable,
CAST(OriginalNetValue AS decimal(28,6))/NULLIF(CAST(OriginalQty AS decimal(28,6)),0)*ReturnQty AS ExpectedNetValue
FROM V
), C AS (
SELECT *,CASE WHEN ValidOriginal=0 THEN 'InvalidOriginal'
WHEN VoucherType=19 THEN 'ConversionSeparateControl'
WHEN ReturnBaseValue IS NULL THEN 'MissingInputType1'
WHEN OriginalBaseValue IS NULL THEN 'MissingOutputType1'
WHEN Comparable=0 THEN 'PriceNeedsReview'
WHEN ABS(ReturnNetValue-ExpectedNetValue)<=1 THEN 'NetMatch' ELSE 'NetDifference' END AS MatchStatus FROM D
)
SELECT VoucherType,OriginalType,MatchStatus,COUNT_BIG(*) AS MovementCount,COUNT(DISTINCT OriginalID) AS OriginalCount,
SUM(CASE WHEN OriginalPlantRef<>2 THEN 1 ELSE 0 END) AS ExternalPlantCount,
SUM(CASE WHEN Comparable=1 THEN ABS(ReturnNetValue-ExpectedNetValue) END) AS AbsoluteDifference,
MIN(ReturnID) AS SampleReturnID
FROM C GROUP BY VoucherType,OriginalType,MatchStatus ORDER BY VoucherType,OriginalType,MatchStatus OPTION(MAXDOP 1);
