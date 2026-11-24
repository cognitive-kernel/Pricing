-- E6E2F.6: inspect ALL ItemPrice components, not only previously selected price types.
-- Replace placeholder with local original delivery and return movement IDs.
;WITH Seeds AS (
 SELECT MovementID FROM (VALUES (CAST(0 AS bigint))) v(MovementID)
)
SELECT vi.InventoryVoucherItemID AS MovementID,vi.PartRef,
 iv.InventoryVoucherSpecificationRef AS VoucherType,
 vi.MajorUnitQuantity AS MovementQty,
 ip.ItemPriceID,ip.IsInput,ip.PriceType,ip.IsReturn,ip.HasReference,
 ip.Quantity AS PriceQty,ip.Fee,ip.Price,ip.NetFee,ip.NetPrice,
 ip.Effect,ip.FeeReferenceCode,ip.FeeReferenceRef,
 ip.VoucherItemRef,ip.PricingFactorItemPriceRef,ip.CardexRef,
 ip.Date AS PriceDate,
 src.InventoryVoucherItemRef AS LinkedPriceMovementID,
 src.PriceType AS LinkedPriceType,src.NetPrice AS LinkedNetPrice
FROM Seeds s
JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=s.MovementID
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
LEFT JOIN LGS3.ItemPrice ip ON ip.InventoryVoucherItemRef=vi.InventoryVoucherItemID
LEFT JOIN LGS3.ItemPrice src ON src.ItemPriceID=ip.PricingFactorItemPriceRef
ORDER BY vi.PartRef,vi.Date,vi.InventoryVoucherItemID,ip.ItemPriceID OPTION(MAXDOP 1);
-- A NULL ItemPriceID indicates no pricing row, not zero cost.
-- Do not sum all price types or infer linked price semantics before validation.
