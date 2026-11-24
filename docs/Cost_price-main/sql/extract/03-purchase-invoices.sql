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
SELECT m.MovementID,ii.InvoiceItemID,h.InvoiceID,h.InvoiceDate,h.IssuanceDate,h.State AS HeaderState,
 ii.State AS ItemState,h.CurrencyRef,ii.CurrencyRef AS ItemCurrencyRef,h.Type AS InvoiceType,
 pi.PartRef,ii.MajorUnitQuantity AS Qty,ii.NetPrice
FROM M m JOIN PRC3.InvoiceItem ii ON ii.ReferenceType=3 AND ii.ReferenceRef=m.MovementID
JOIN PRC3.Invoice h ON h.InvoiceID=ii.InvoiceRef
LEFT JOIN PRC3.PurchaseItem pi ON pi.PurchaseItemID=ii.PurchaseItemRef
WHERE m.VoucherType IN(2,3)
ORDER BY m.MovementID,ii.InvoiceItemID OPTION(MAXDOP 1);