-- V5.2E.6E.2F.7: re-audit distribution return pricing, without changing source data.
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID, vi.MajorUnitQuantity AS Qty,
 vi.ReturnableVoucherItemRef AS OriginalID, iv.FiscalYearRef,
 vi.PartRef, st.PlantRef
 FROM LGS3.InventoryVoucherItem vi
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE iv.InventoryVoucherSpecificationRef=10012 AND st.PlantRef=2
 AND iv.LedgerRef=1 AND iv.State<>3 AND vs.Direction=1
 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND vi.Date>='20220818' AND vi.Date<'20261125'
), P AS (
 SELECT r.*,p.AllRows,p.LegacyRows,p.LegacyPositiveRows,p.Type1Rows,p.Type1PositiveRows,p.Type1Qty,p.Type1Value,
 p.OtherRows,p.InvalidType1Rows
 FROM R r OUTER APPLY (
 SELECT COUNT_BIG(*) AS AllRows,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType IN(3,6,7) THEN 1 END) AS LegacyRows,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType IN(3,6,7) AND ip.NetPrice>0 THEN 1 END) AS LegacyPositiveRows,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN 1 END) AS Type1Rows,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 AND ip.NetPrice>0 THEN 1 END) AS Type1PositiveRows,
 SUM(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN ip.Quantity END) AS Type1Qty,
 SUM(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 THEN ip.NetPrice END) AS Type1Value,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType IN(1,3,6,7) THEN NULL ELSE 1 END) AS OtherRows,
 COUNT_BIG(CASE WHEN ip.IsInput=1 AND ip.PriceType=1 AND
 (ip.IsReturn IS NULL OR ip.IsReturn<>1 OR ip.HasReference IS NULL OR ip.HasReference<>1 OR ip.Effect IS NULL OR ip.Effect<>1 OR ip.Quantity IS NULL OR ip.Quantity<=0 OR ip.NetPrice IS NULL OR ip.NetPrice<=0) THEN 1 END) AS InvalidType1Rows
 FROM LGS3.ItemPrice ip WHERE ip.InventoryVoucherItemRef=r.MovementID
 ) p
), C AS (
 SELECT *,CASE
 WHEN AllRows=0 THEN 'NoItemPrice'
 WHEN Type1Rows>0 AND LegacyRows>0 THEN 'MixedType1AndLegacyReview'
 WHEN Type1Rows>0 AND (InvalidType1Rows>0 OR OriginalID IS NULL OR Qty IS NULL OR Qty<=0 OR Type1Qty IS NULL OR ABS(Type1Qty-Qty)>0.000001) THEN 'Type1NeedsReview'
 WHEN Type1Rows>0 AND OtherRows>0 THEN 'Type1WithOtherComponentsReview'
 WHEN Type1Rows>0 THEN 'Type1ReferencePriceCandidate'
 WHEN LegacyPositiveRows>0 THEN 'LegacyPositiveCandidate'
 WHEN LegacyRows>0 THEN 'LegacyWithoutPositivePrice'
 ELSE 'OtherPriceComponentsOnly' END AS PricingStatus
 FROM P
)
SELECT FiscalYearRef,PricingStatus,COUNT_BIG(*) AS MovementCount,
 SUM(CASE WHEN LegacyPositiveRows>0 THEN CAST(1 AS bigint) ELSE 0 END) AS LegacyPositiveMovementCount,
 SUM(CASE WHEN Type1PositiveRows>0 THEN CAST(1 AS bigint) ELSE 0 END) AS Type1PositiveMovementCount,
 SUM(CASE WHEN Type1PositiveRows>0 AND LegacyPositiveRows=0 THEN CAST(1 AS bigint) ELSE 0 END) AS NewlyDiscoveredPositiveCount,
 SUM(CASE WHEN OtherRows>0 THEN CAST(1 AS bigint) ELSE 0 END) AS WithOtherComponentsCount,
 SUM(CASE WHEN OriginalID IS NULL THEN CAST(1 AS bigint) ELSE 0 END) AS MissingOriginalRefCount,
 MIN(MovementID) AS SampleMovementID
FROM C GROUP BY FiscalYearRef,PricingStatus ORDER BY FiscalYearRef,PricingStatus OPTION(MAXDOP 1);
-- Candidate status is NOT final accepted cost. Validate original movement and cumulative returns next.
-- Legacy positive count is a diagnostic; compare prior coverage only with identical historical predicates.
