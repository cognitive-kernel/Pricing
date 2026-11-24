-- E6E2F.10: reverse closure and cumulative physical distribution returns.
-- Read-only, all plants and years for reverse claims within snapshot.
;WITH TargetOriginals AS (
 SELECT DISTINCT vi.ReturnableVoucherItemRef AS OriginalID
 FROM LGS3.InventoryVoucherItem vi
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE iv.InventoryVoucherSpecificationRef=10012 AND st.PlantRef=2
 AND iv.LedgerRef=1 AND iv.State<>3 AND vs.Direction=1
 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND vi.ReturnableVoucherItemRef IS NOT NULL
), O AS (
 SELECT t.OriginalID,o.PartRef,o.StoreRef,st.PlantRef,o.Date AS OriginalDate,o.MajorUnitQuantity AS OriginalQty,
 CASE WHEN o.InventoryVoucherItemID IS NOT NULL AND h.InventoryVoucherSpecificationRef=10005
 AND h.LedgerRef=1 AND h.State<>3 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND o.MajorUnitQuantity>0 AND o.Date IS NOT NULL THEN 1 ELSE 0 END AS ValidOriginal
 FROM TargetOriginals t
 LEFT JOIN LGS3.InventoryVoucherItem o ON o.InventoryVoucherItemID=t.OriginalID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=o.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=o.StoreRef
), R AS (
 SELECT o.OriginalID,r.InventoryVoucherItemID AS ReturnID,r.MajorUnitQuantity AS ReturnQty,r.Date AS ReturnDate,
 st.PlantRef AS ReturnPlantRef,r.StoreRef AS ReturnStoreRef,h.FiscalYearRef,
 CASE WHEN h.InventoryVoucherSpecificationRef=10012 AND h.LedgerRef=1 AND h.State<>3
 AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND r.PartRef=o.PartRef AND r.MajorUnitQuantity>0 AND r.Date>=o.OriginalDate AND r.Date<'20261125'
 THEN 1 ELSE 0 END AS ValidPhysical,
 CASE WHEN st.PlantRef=2 AND r.Date>='20220818' AND r.Date<'20261125' THEN 1 ELSE 0 END AS InTargetScope,
 CASE WHEN st.PlantRef IS NULL OR st.PlantRef<>o.PlantRef THEN 1 ELSE 0 END AS CrossPlant,
 CASE WHEN r.StoreRef IS NULL OR r.StoreRef<>o.StoreRef THEN 1 ELSE 0 END AS CrossStore
 FROM O o
 JOIN LGS3.InventoryVoucherItem r ON r.ReturnableVoucherItemRef=o.OriginalID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=r.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=r.StoreRef
 WHERE r.Date<'20261125' OR r.Date IS NULL
), A AS (
 SELECT OriginalID,COUNT_BIG(*) AS LinkedCount,
 SUM(CASE WHEN ValidPhysical=1 THEN 1 ELSE 0 END) AS ValidReturnCount,
 SUM(CASE WHEN ValidPhysical=1 AND InTargetScope=1 THEN 1 ELSE 0 END) AS TargetReturnCount,
 SUM(CASE WHEN ValidPhysical=1 AND InTargetScope=0 THEN 1 ELSE 0 END) AS ExtraReturnCount,
 SUM(CASE WHEN ValidPhysical=0 THEN 1 ELSE 0 END) AS InvalidReturnCount,
 SUM(CASE WHEN ValidPhysical=1 THEN ReturnQty ELSE 0 END) AS TotalReturnQty,
 SUM(CASE WHEN ValidPhysical=1 AND CrossPlant=1 THEN 1 ELSE 0 END) AS CrossPlantCount,
 SUM(CASE WHEN ValidPhysical=1 AND CrossStore=1 THEN 1 ELSE 0 END) AS CrossStoreCount,
 COUNT(DISTINCT CASE WHEN ValidPhysical=1 THEN FiscalYearRef END) AS ReturnFiscalYearCount
 FROM R GROUP BY OriginalID
), C AS (
 SELECT o.*,a.LinkedCount,a.ValidReturnCount,a.TargetReturnCount,a.ExtraReturnCount,a.InvalidReturnCount,
 a.TotalReturnQty,a.CrossPlantCount,a.CrossStoreCount,a.ReturnFiscalYearCount,
 CASE WHEN o.ValidOriginal=0 THEN 'InvalidOriginal'
 WHEN COALESCE(a.ValidReturnCount,0)=0 THEN 'NoValidReturn'
 WHEN a.TotalReturnQty>o.OriginalQty+0.000001 THEN 'CumulativeOverReturn'
 WHEN ABS(a.TotalReturnQty-o.OriginalQty)<=0.000001 THEN 'FullReturn'
 ELSE 'PartialReturn' END AS QtyStatus
 FROM O o LEFT JOIN A a ON a.OriginalID=o.OriginalID
)
SELECT QtyStatus,COUNT_BIG(*) AS OriginalCount,
 SUM(CAST(COALESCE(TargetReturnCount,0) AS bigint)) AS TargetReturnCount,
 SUM(CAST(COALESCE(ValidReturnCount,0) AS bigint)) AS AllValidReturnCount,
 SUM(CAST(COALESCE(ExtraReturnCount,0) AS bigint)) AS ExtraReturnCount,
 SUM(CAST(COALESCE(InvalidReturnCount,0) AS bigint)) AS InvalidLinkedCount,
 SUM(CASE WHEN ValidReturnCount>1 THEN 1 ELSE 0 END) AS MultipleReturnOriginalCount,
 SUM(CASE WHEN ReturnFiscalYearCount>1 THEN 1 ELSE 0 END) AS CrossYearOriginalCount,
 SUM(COALESCE(CrossPlantCount,0)) AS CrossPlantReturnCount,
 SUM(COALESCE(CrossStoreCount,0)) AS CrossStoreReturnCount,
 MAX(ValidReturnCount) AS MaxReturnsPerOriginal,
 MAX(CASE WHEN TotalReturnQty>OriginalQty THEN TotalReturnQty-OriginalQty ELSE 0 END) AS MaxExcessQty,
 MIN(OriginalID) AS SampleOriginalID
FROM C GROUP BY QtyStatus ORDER BY QtyStatus OPTION(MAXDOP 1);
-- ValidPhysical includes cross-plant returns to detect global reference claims; not automatic approval of cross-plant costing.
-- InvalidLinkedCount includes other voucher types and rejected rows; inspect separately if nonzero.
-- Do not equate a complete quantity reconciliation with an exact invoice-item allocation.
