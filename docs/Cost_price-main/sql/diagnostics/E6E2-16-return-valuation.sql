-- E6E2F.5: official valuation and direct lineage of original deliveries and two distinct return types.
-- Replace placeholder seed IDs locally. Read-only; missing prices remain NULL.
;WITH Seeds AS (
 SELECT MovementID FROM (VALUES (CAST(0 AS bigint))) v(MovementID)
), M AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,vi.PartRef,vi.StoreRef,vi.Date,
 iv.InventoryVoucherSpecificationRef AS VoucherType,vs.Direction,vi.MajorUnitQuantity,
 vi.ReferenceType,vi.ReferenceRef,vi.ReturnableVoucherItemRef,
 iv.State,iv.LedgerRef
 FROM Seeds s JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=s.MovementID
 JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
 JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
)
SELECT m.*,p.PriceRowCount,p.PricedRowCount,p.PricedQty,p.OfficialValue,
 p.OfficialValue/NULLIF(m.MajorUnitQuantity,0) AS ImpliedUnitCost,
 rp.ReceiptPermitItemID,rp.ReferenceType AS ReceiptPermitReferenceType,rp.ReferenceRef AS ReceiptPermitReferenceRef,
 rp.PartRef AS ReceiptPermitPartRef,rp.MajorUnitQuantity AS ReceiptPermitQty,
 rp.ReceiptPermitRef
FROM M m
OUTER APPLY (
 SELECT COUNT_BIG(*) AS PriceRowCount,COUNT_BIG(ip.NetPrice) AS PricedRowCount,
 SUM(ip.Quantity) AS PricedQty,SUM(ip.NetPrice) AS OfficialValue
 FROM LGS3.ItemPrice ip WHERE ip.InventoryVoucherItemRef=m.MovementID
 AND ((m.Direction=1 AND ip.IsInput=1 AND ip.PriceType IN(3,6,7))
 OR (m.Direction=2 AND ip.IsInput=0 AND ip.PriceType IN(1,9)))
) p
LEFT JOIN LGS3.ReceiptPermitItem rp ON m.VoucherType=18 AND m.ReferenceType=2 AND rp.ReceiptPermitItemID=m.ReferenceRef
ORDER BY m.PartRef,m.Date,m.MovementID OPTION(MAXDOP 1);
-- OfficialValue is observed valuation, not automatically accepted cost; inspect quantity/price coverage.
-- PricedQty can include multiple price components; do not assume it is unique physical quantity.
