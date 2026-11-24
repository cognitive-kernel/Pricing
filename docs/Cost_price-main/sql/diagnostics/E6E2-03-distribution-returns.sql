-- E6E2C: inspect distribution return movements for local delivery seeds.
-- Replace placeholder 0 with local IDs. Read-only, standalone.
;WITH Seeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
)
SELECT d.InventoryVoucherItemID AS DeliveryID,d.PartRef,d.StoreRef,
 d.Date AS DeliveryDate,d.MajorUnitQuantity AS GrossQty,
 r.InventoryVoucherItemID AS ReturnMovementID,r.PartRef AS ReturnPartRef,
 r.StoreRef AS ReturnStoreRef,r.Date AS ReturnDate,r.MajorUnitQuantity AS ReturnQty,
 h.State AS ReturnState,h.LedgerRef,ds.PlantRef AS DeliveryPlantRef,
 rs.PlantRef AS ReturnPlantRef,vs.Direction,vs.TypeOfEffectOnStock,vs.Category,
 CASE WHEN h.LedgerRef IS NULL OR h.LedgerRef<>1 THEN 'ExcludedOtherLedger'
 WHEN h.State IS NULL OR h.State=3 THEN 'ExcludedState'
 WHEN r.Date>='20261125' THEN 'OutsideSnapshot'
 WHEN vs.Direction IS NULL OR vs.Direction<>1 OR vs.TypeOfEffectOnStock IS NULL OR vs.TypeOfEffectOnStock<>1 OR vs.Category IS NULL OR vs.Category=13 THEN 'InvalidStockEffect'
 WHEN r.PartRef IS NULL OR r.PartRef<>d.PartRef THEN 'PartMismatch'
 WHEN rs.PlantRef IS NULL OR ds.PlantRef IS NULL OR rs.PlantRef<>ds.PlantRef THEN 'PlantMismatch'
 WHEN r.MajorUnitQuantity IS NULL OR r.MajorUnitQuantity<=0 THEN 'InvalidReturnQty'
 WHEN r.Date IS NULL OR r.Date<d.Date THEN 'InvalidReturnDate'
 ELSE 'ValidDistributionReturn' END AS ReturnStatus
FROM Seeds s
JOIN LGS3.InventoryVoucherItem d ON d.InventoryVoucherItemID=s.DeliveryID
JOIN LGS3.InventoryVoucherItem r ON r.ReturnableVoucherItemRef=d.InventoryVoucherItemID
JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=r.InventoryVoucherRef AND h.InventoryVoucherSpecificationRef=10012
LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
LEFT JOIN LGS3.Store ds ON ds.StoreID=d.StoreRef
LEFT JOIN LGS3.Store rs ON rs.StoreID=r.StoreRef
ORDER BY d.PartRef,d.InventoryVoucherItemID,r.InventoryVoucherItemID OPTION(MAXDOP 1);
