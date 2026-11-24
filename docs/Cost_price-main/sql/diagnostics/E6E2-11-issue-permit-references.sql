-- E6E2E.7: inspect issue permit header/item references for local distribution delivery seeds.
-- Replace placeholder with local delivery IDs. No writes. XML content intentionally excluded.
;WITH Seeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
)
SELECT d.InventoryVoucherItemID AS DeliveryID,d.PartRef,d.MajorUnitQuantity AS DeliveryQty,
 p.IssuePermitItemID,p.IssuePermitRef,p.RowNumber,p.PartRef AS PermitPartRef,
 p.MajorUnitQuantity AS PermitQty,p.MaxMajorUnitQuantity,p.State AS PermitItemState,
 p.ReferenceType AS ItemReferenceType,p.ReferenceRef AS ItemReferenceRef,
 p.CounterpartEntityCode AS ItemCounterpartCode,p.CounterpartEntityRef AS ItemCounterpartRef,
 p.ReferenceCode,
 h.IssuePermitType,h.ReferenceType AS HeaderReferenceType,h.ReferenceRef AS HeaderReferenceRef,
 h.CounterpartEntityCode AS HeaderCounterpartCode,h.CounterpartEntityRef AS HeaderCounterpartRef,
 h.PlantRef,h.StoreRef,h.LedgerRef,h.State AS HeaderState,h.Date AS PermitDate,
 CASE WHEN p.ExtraData IS NULL THEN 0 ELSE 1 END AS HasExtraData,
 CASE WHEN p.ExtraData IS NULL THEN 0 ELSE DATALENGTH(CONVERT(nvarchar(max),p.ExtraData)) END AS ExtraDataBytes,
 CASE WHEN p.Additional1 IS NOT NULL OR p.Additional2 IS NOT NULL OR p.Additional3 IS NOT NULL OR p.Additional4 IS NOT NULL OR p.Additional5 IS NOT NULL THEN 1 ELSE 0 END AS HasItemAdditionalData,
 CASE WHEN h.Additional1 IS NOT NULL OR h.Additional2 IS NOT NULL OR h.Additional3 IS NOT NULL OR h.Additional4 IS NOT NULL OR h.Additional5 IS NOT NULL THEN 1 ELSE 0 END AS HasHeaderAdditionalData,
 CASE WHEN p.IssuePermitItemID IS NULL THEN 'MissingPermitItem'
 WHEN h.IssuePermitID IS NULL THEN 'MissingPermitHeader'
 WHEN p.PartRef<>d.PartRef THEN 'PartMismatch'
 WHEN ABS(p.MajorUnitQuantity-d.MajorUnitQuantity)>0.000001 THEN 'QtyMismatch'
 ELSE 'ExactItemQty' END AS PermitCheck
FROM Seeds s JOIN LGS3.InventoryVoucherItem d ON d.InventoryVoucherItemID=s.DeliveryID
LEFT JOIN LGS3.IssuePermitItem p ON d.ReferenceType=4 AND p.IssuePermitItemID=d.ReferenceRef
LEFT JOIN LGS3.IssuePermit h ON h.IssuePermitID=p.IssuePermitRef
ORDER BY d.PartRef,d.InventoryVoucherItemID OPTION(MAXDOP 1);
-- Structural reference matches and quantities are diagnostic, not proof of invoice allocation.
