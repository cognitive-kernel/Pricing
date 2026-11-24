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
SELECT m.MovementID,m.PartRef,m.PlantRef,m.Qty,m.MovementDate,
 ipi.IssuePermitItemID,ipi.MajorUnitQuantity AS PermitQty,ipi.MaxMajorUnitQuantity,
 ipi.PartRef AS PermitPartRef,ipi.State AS PermitItemState,ip.State AS PermitState,
 ip.PlantRef AS PermitPlant,ip.LedgerRef AS PermitLedger,
 ip.ReferenceType AS PermitReferenceType,ip.IssuePermitType,a.AssignmentID
FROM M m LEFT JOIN LGS3.IssuePermitItem ipi ON m.ReferenceType=4 AND ipi.IssuePermitItemID=m.ReferenceRef
LEFT JOIN LGS3.IssuePermit ip ON ip.IssuePermitID=ipi.IssuePermitRef
LEFT JOIN DSD3.Assignment a ON ip.ReferenceType=9 AND a.AssignmentID=ip.ReferenceRef
WHERE m.VoucherType=10005
OPTION(MAXDOP 1);