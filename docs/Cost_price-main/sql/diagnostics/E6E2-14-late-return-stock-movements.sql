-- E6E2F.3: inspect all permanent physical movements near late returns.
-- Replace example part IDs and date bounds locally. No writes.
;WITH Parts AS (SELECT PartID FROM (VALUES (CAST(0 AS bigint))) v(PartID))
SELECT vi.PartRef,st.PlantRef,vi.StoreRef,vi.Date,vi.InventoryVoucherItemID AS MovementID,
 iv.InventoryVoucherID,iv.InventoryVoucherSpecificationRef AS VoucherType,vs.Name AS VoucherTypeName,
 iv.State,iv.LedgerRef,vs.Direction,vs.TypeOfEffectOnStock,vs.Category,
 vi.MajorUnitQuantity,CASE WHEN vs.Direction=1 THEN vi.MajorUnitQuantity WHEN vs.Direction=2 THEN -vi.MajorUnitQuantity END AS SignedQty,
 vi.ReferenceType,vi.ReferenceRef,vi.ReturnableVoucherItemRef,iv.ReferenceType AS HeaderReferenceType,iv.ReferenceRef AS HeaderReferenceRef,iv.CounterpartStoreRef
FROM Parts p JOIN LGS3.InventoryVoucherItem vi ON vi.PartRef=p.PartID
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE vi.Date>='20230729' AND vi.Date<'20230803' AND iv.LedgerRef=1 AND iv.State<>3
AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13 AND vs.Direction IN (1,2)
ORDER BY vi.PartRef,vi.Date,vi.InventoryVoucherItemID OPTION(MAXDOP 1);
-- All plants included to detect compensating transfers; date window is diagnostic, not proof of full lifecycle.
