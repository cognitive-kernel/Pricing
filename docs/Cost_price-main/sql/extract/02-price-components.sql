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
SELECT m.MovementID,ip.ItemPriceID,ip.PriceType,ip.IsInput,ip.IsReturn,ip.HasReference,
 ip.Effect,ip.Quantity AS PriceQty,ip.NetPrice,ip.FeeReferenceCode,ip.FeeReferenceRef,
 ip.Date AS PriceDate,ip.VoucherCreationDate,ip.SortingDateInCardex,ip.CardexRef
FROM M m JOIN LGS3.ItemPrice ip ON ip.InventoryVoucherItemRef=m.MovementID
ORDER BY m.MovementID,ip.ItemPriceID OPTION(MAXDOP 1);