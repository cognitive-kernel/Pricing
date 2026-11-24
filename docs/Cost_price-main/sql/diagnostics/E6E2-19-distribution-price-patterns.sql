-- E6E2F.8: inspect pricing component patterns of all distribution returns.
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.FiscalYearRef,vi.ReturnableVoucherItemRef AS OriginalID
 FROM LGS3.InventoryVoucherItem vi
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE iv.InventoryVoucherSpecificationRef=10012 AND st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
 AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND vi.Date>='20220818' AND vi.Date<'20261125'
), P AS (
 SELECT r.MovementID,r.FiscalYearRef,r.OriginalID,ip.ItemPriceID,ip.IsInput,ip.PriceType,ip.IsReturn,ip.HasReference,ip.Effect,ip.FeeReferenceCode,ip.Quantity,ip.NetPrice,
 MAX(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN 1 ELSE 0 END) OVER(PARTITION BY r.MovementID) AS HasType1,
 MAX(CASE WHEN ip.ItemPriceID IS NOT NULL AND (ip.IsInput IS NULL OR ip.IsInput<>1 OR ip.PriceType IS NULL OR ip.PriceType NOT IN(1,3,6,7)) THEN 1 ELSE 0 END) OVER(PARTITION BY r.MovementID) AS HasOther
 FROM R r LEFT JOIN LGS3.ItemPrice ip ON ip.InventoryVoucherItemRef=r.MovementID
), C AS (
 SELECT *,CASE WHEN HasType1=1 AND HasOther=1 THEN 'Type1WithOther'
 WHEN HasType1=1 THEN 'Type1WithoutOther'
 WHEN OriginalID IS NULL THEN 'WithoutOriginalReference'
 ELSE 'Other' END AS PriceGroup FROM P
)
SELECT FiscalYearRef,PriceGroup,IsInput,PriceType,IsReturn,HasReference,Effect,FeeReferenceCode,
 COUNT(DISTINCT MovementID) AS MovementCount,COUNT_BIG(ItemPriceID) AS PriceRowCount,
 COUNT_BIG(CASE WHEN NetPrice>0 THEN 1 END) AS PositiveRows,
 COUNT_BIG(CASE WHEN NetPrice=0 THEN 1 END) AS ZeroRows,
 COUNT_BIG(CASE WHEN NetPrice<0 THEN 1 END) AS NegativeRows,
 COUNT_BIG(CASE WHEN ItemPriceID IS NOT NULL AND NetPrice IS NULL THEN 1 END) AS NullPriceRows,
 SUM(Quantity) AS ComponentQty,SUM(NetPrice) AS ComponentValue,
 MIN(MovementID) AS SampleMovementID
FROM C GROUP BY FiscalYearRef,PriceGroup,IsInput,PriceType,IsReturn,HasReference,Effect,FeeReferenceCode
ORDER BY FiscalYearRef,PriceGroup,IsInput,PriceType,IsReturn,HasReference,Effect,FeeReferenceCode OPTION(MAXDOP 1);
-- Component values are diagnostic only; never sum across types until semantics validated.
