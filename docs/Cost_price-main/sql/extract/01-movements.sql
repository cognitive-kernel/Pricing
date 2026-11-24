-- Replace 0 locally with selected PartID values; do not commit real IDs.
;WITH Seed AS (SELECT PartID FROM (VALUES (CAST(0 AS bigint))) v(PartID)),
M AS (
 SELECT vi.InventoryVoucherItemID AS MovementID, vi.InventoryVoucherRef AS VoucherID,
 vi.PartRef, st.PlantRef, vi.StoreRef, iv.FiscalYearRef,
 iv.InventoryVoucherSpecificationRef AS VoucherType, vs.Direction, vs.Category,
 vi.Date AS MovementDate, vi.VoucherCreationDate, vi.MajorUnitQuantity AS Qty,
 vi.ReturnableVoucherItemRef AS OriginalMovementID, vi.ReferenceType, vi.ReferenceRef,
 vi.UnitRef, vi.PartUnitRef, vi.Quantity, vi.SecondUnitQuantity,
 iv.CreationDate, iv.LastModificationDate
 FROM LGS3.InventoryVoucherItem vi
 JOIN Seed p ON p.PartID=vi.PartRef
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
 JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
 WHERE iv.LedgerRef=1 AND iv.State<>3 AND vs.TypeOfEffectOnStock=1
 AND vs.Direction IN(1,2) AND vi.Date>='20220818' AND vi.Date<'20261125'
)
SELECT M.*,p.Code AS PartCode,p.Name AS PartName,
 CASE WHEN VoucherType=99 OR Category=13 THEN 'ClosingControl'
 WHEN VoucherType=1 AND FiscalYearRef<>2 THEN 'OpeningControl'
 WHEN VoucherType IN(23,74) THEN 'ExternalOwnershipReview'
 WHEN VoucherType=1 THEN 'InitialOpening'
 ELSE 'PhysicalMovement' END AS EventClass
FROM M JOIN LGS3.Part p ON p.PartID=M.PartRef
ORDER BY MovementDate,VoucherCreationDate,MovementID OPTION(MAXDOP 1);