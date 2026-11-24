-- Independent read-only statements. Execute separately; stop each after 60 seconds.
-- F.11
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.FiscalYearRef,vi.MajorUnitQuantity AS Qty
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef=10012 AND vi.ReturnableVoucherItemRef IS NULL
), P AS (
 SELECT r.*,p.PriceRows,p.BaseRows,p.BaseQty,p.BaseValue,p.InvalidRows,p.Code0Rows,p.Code6Rows
 FROM R r OUTER APPLY (
 SELECT COUNT_BIG(*) AS PriceRows,
 COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=3 THEN 1 END) AS BaseRows,
 SUM(CASE WHEN IsInput=1 AND PriceType=3 THEN Quantity END) AS BaseQty,
 SUM(CASE WHEN IsInput=1 AND PriceType=3 THEN NetPrice END) AS BaseValue,
 COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=3 AND IsReturn=0 AND HasReference=0 AND Effect=1 AND Quantity>0 AND NetPrice>0 AND FeeReferenceCode IN(0,6) THEN NULL ELSE 1 END) AS InvalidRows,
 COUNT_BIG(CASE WHEN FeeReferenceCode=0 THEN 1 END) AS Code0Rows,
 COUNT_BIG(CASE WHEN FeeReferenceCode=6 THEN 1 END) AS Code6Rows
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.MovementID
 ) p
), C AS (
 SELECT *,CASE WHEN PriceRows=0 THEN 'NoPrice'
 WHEN InvalidRows>0 OR BaseRows=0 OR BaseValue IS NULL OR BaseValue<=0 THEN 'PriceNeedsReview'
 WHEN Qty IS NULL OR Qty<=0 OR BaseQty IS NULL OR ABS(BaseQty-Qty)>0.000001 THEN 'QuantityMismatch'
 ELSE 'IndependentOfficialPriceCandidate' END AS PriceStatus,
 CASE WHEN Code0Rows>0 AND Code6Rows=0 THEN 'Code0' WHEN Code6Rows>0 AND Code0Rows=0 THEN 'Code6' ELSE 'MixedOrOther' END AS FeeSource
 FROM P
)
SELECT FiscalYearRef,PriceStatus,FeeSource,COUNT_BIG(*) AS MovementCount,
 SUM(PriceRows) AS PriceRowCount,SUM(Qty) AS MovementQty,SUM(BaseValue) AS ObservedBaseValue,
 MIN(MovementID) AS SampleMovementID
FROM C GROUP BY FiscalYearRef,PriceStatus,FeeSource
ORDER BY FiscalYearRef,PriceStatus,FeeSource OPTION(MAXDOP 1);

-- F.12
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS ReturnID,vi.ReturnableVoucherItemRef AS OriginalID,
 vi.PartRef,vi.StoreRef AS ReturnStoreRef,vi.MajorUnitQuantity AS ReturnQty,
 vi.Date AS ReturnDate,iv.FiscalYearRef
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef=10012 AND vi.ReturnableVoucherItemRef IS NOT NULL
)
SELECT r.FiscalYearRef,o.StoreRef AS OriginalStoreRef,os.Name AS OriginalStoreName,
 r.ReturnStoreRef,rs.Name AS ReturnStoreName,COUNT_BIG(*) AS ReturnCount,
 COUNT(DISTINCT r.PartRef) AS PartCount,
 SUM(CASE WHEN ABS(r.ReturnQty-o.MajorUnitQuantity)<=0.000001 THEN 1 ELSE 0 END) AS FullReturnCount,
 SUM(CASE WHEN r.ReturnQty<o.MajorUnitQuantity-0.000001 THEN 1 ELSE 0 END) AS PartialReturnCount,
 SUM(r.ReturnQty) AS ReturnQty,MIN(DATEDIFF(DAY,o.Date,r.ReturnDate)) AS MinDays,
 MAX(DATEDIFF(DAY,o.Date,r.ReturnDate)) AS MaxDays,
 MIN(r.ReturnID) AS SampleReturnID,MIN(r.OriginalID) AS SampleOriginalID
FROM R r JOIN LGS3.InventoryVoucherItem o ON o.InventoryVoucherItemID=r.OriginalID
JOIN LGS3.Store os ON os.StoreID=o.StoreRef JOIN LGS3.Store rs ON rs.StoreID=r.ReturnStoreRef
WHERE o.StoreRef<>r.ReturnStoreRef
GROUP BY r.FiscalYearRef,o.StoreRef,os.Name,r.ReturnStoreRef,rs.Name
ORDER BY r.FiscalYearRef,o.StoreRef,r.ReturnStoreRef OPTION(MAXDOP 1);

-- G.1
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.InventoryVoucherSpecificationRef AS VoucherType,
 vi.ReturnableVoucherItemRef AS OriginalID
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
), P AS (
 SELECT r.*,p.PriceRows,p.LegacyPositive,p.Type1Positive,p.AnyInputPositive,p.OtherInputPositive
 FROM R r OUTER APPLY (
 SELECT COUNT_BIG(*) AS PriceRows,
 COUNT_BIG(CASE WHEN IsInput=1 AND PriceType IN(3,6,7) AND NetPrice>0 THEN 1 END) AS LegacyPositive,
 COUNT_BIG(CASE WHEN IsInput=1 AND PriceType=1 AND NetPrice>0 THEN 1 END) AS Type1Positive,
 COUNT_BIG(CASE WHEN IsInput=1 AND NetPrice>0 THEN 1 END) AS AnyInputPositive,
 COUNT_BIG(CASE WHEN IsInput=1 AND PriceType NOT IN(1,3,6,7) AND NetPrice>0 THEN 1 END) AS OtherInputPositive
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.MovementID
 ) p
)
SELECT VoucherType,COUNT_BIG(*) AS MovementCount,
 SUM(CAST(CASE WHEN LegacyPositive>0 THEN 1 ELSE 0 END AS bigint)) AS LegacyPositiveCount,
 SUM(CAST(CASE WHEN Type1Positive>0 THEN 1 ELSE 0 END AS bigint)) AS Type1PositiveCount,
 SUM(CAST(CASE WHEN Type1Positive>0 AND LegacyPositive=0 THEN 1 ELSE 0 END AS bigint)) AS NewlyFoundType1Count,
 SUM(CAST(CASE WHEN Type1Positive>0 AND LegacyPositive>0 THEN 1 ELSE 0 END AS bigint)) AS MixedBaseCount,
 SUM(CAST(CASE WHEN AnyInputPositive>0 THEN 1 ELSE 0 END AS bigint)) AS AnyInputPositiveCount,
 SUM(CAST(CASE WHEN OtherInputPositive>0 AND LegacyPositive=0 AND Type1Positive=0 THEN 1 ELSE 0 END AS bigint)) AS OtherPositiveOnlyCount,
 SUM(CAST(CASE WHEN PriceRows=0 THEN 1 ELSE 0 END AS bigint)) AS NoItemPriceCount,
 SUM(CAST(CASE WHEN PriceRows>0 AND AnyInputPositive=0 THEN 1 ELSE 0 END AS bigint)) AS RowsWithoutPositiveInputCount,
 SUM(CAST(CASE WHEN OriginalID IS NOT NULL THEN 1 ELSE 0 END AS bigint)) AS ReferencedMovementCount,
 MIN(CASE WHEN Type1Positive>0 AND LegacyPositive=0 THEN MovementID END) AS SampleNewMovementID
FROM P GROUP BY VoucherType ORDER BY VoucherType OPTION(MAXDOP 1);

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
