-- G.3: identify positive-only nonlegacy, nontype1 input price components for purchase and sales returns.
;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.InventoryVoucherSpecificationRef AS VoucherType,
 iv.FiscalYearRef,vi.PartRef,vi.MajorUnitQuantity AS MovementQty,vi.ReturnableVoucherItemRef AS OriginalID
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef IN(2,18)
), S AS (
 SELECT r.*
 FROM R r
 WHERE EXISTS (SELECT 1 FROM LGS3.ItemPrice p WHERE p.InventoryVoucherItemRef=r.MovementID AND p.IsInput=1 AND p.NetPrice>0)
 AND NOT EXISTS (SELECT 1 FROM LGS3.ItemPrice p WHERE p.InventoryVoucherItemRef=r.MovementID AND p.IsInput=1 AND p.PriceType IN(1,3,6,7) AND p.NetPrice>0)
)
SELECT s.*,p.ItemPriceID,p.IsInput,p.PriceType,p.IsReturn,p.HasReference,p.Effect,p.Quantity AS PriceQty,p.NetPrice,p.FeeReferenceCode,p.FeeReferenceRef,p.PricingFactorItemPriceRef
FROM S s JOIN LGS3.ItemPrice p ON p.InventoryVoucherItemRef=s.MovementID
ORDER BY s.VoucherType,s.MovementID,p.ItemPriceID OPTION(MAXDOP 1);
