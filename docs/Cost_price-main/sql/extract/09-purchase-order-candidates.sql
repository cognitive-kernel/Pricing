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
SELECT DISTINCT m.MovementID,oi.OrderItemID,h.OrderID,h.OrderDate,h.LastModificationDate,
 h.State AS HeaderState,oi.State AS ItemState,h.CurrencyRef,oi.CurrencyRef AS ItemCurrencyRef,
 pi.PartRef,oi.MajorUnitQuantity AS OrderQty,oi.NetPrice
FROM M m JOIN SYS3.RecursiveEntityRelation e ON e.TargetType=669 AND e.TargetRef=m.MovementID AND e.SourceType=720
JOIN PRC3.OrderItem oi ON oi.OrderItemID=e.SourceRef JOIN PRC3.[Order] h ON h.OrderID=oi.OrderRef
LEFT JOIN PRC3.PurchaseItem pi ON pi.PurchaseItemID=oi.PurchaseItemRef
WHERE m.VoucherType IN(2,3)
OPTION(MAXDOP 1);