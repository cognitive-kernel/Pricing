-- V5.2E.6E.2F.2: inspect return financial reference and physical receipt relations.
-- Replace placeholder invoice-return IDs with local IDs. Read only.
;WITH Seeds AS (
 SELECT ReturnInvoiceItemID FROM (VALUES (CAST(0 AS bigint))) v(ReturnInvoiceItemID)
), R AS (
 SELECT ri.ReturnInvoiceItemID,ri.ReferenceType,ri.ReferenceRef,p.PartRef,rh.Date AS FinancialReturnDate,ri.MajorUnitQuantity AS FinancialReturnQty
 FROM Seeds s JOIN SLS3.ReturnInvoiceItem ri ON ri.ReturnInvoiceItemID=s.ReturnInvoiceItemID
 JOIN SLS3.ReturnInvoice rh ON rh.ReturnInvoiceID=ri.ReturnInvoiceRef
 JOIN SLS3.Product p ON p.ProductID=ri.ProductRef
), Edges AS (
 SELECT r.ReturnInvoiceItemID,'Direct' AS RelationPath,e.SourceType AS EntityType,e.SourceRef AS EntityID
 FROM R r JOIN SYS3.EntityRelation e ON e.TargetType=914 AND e.TargetRef=r.ReturnInvoiceItemID
 UNION
 SELECT r.ReturnInvoiceItemID,'Recursive',e.SourceType,e.SourceRef
 FROM R r JOIN SYS3.RecursiveEntityRelation e ON e.TargetType=914 AND e.TargetRef=r.ReturnInvoiceItemID
 UNION
 SELECT r.ReturnInvoiceItemID,'ReferenceIDProbe',669,r.ReferenceRef FROM R r WHERE r.ReferenceRef IS NOT NULL
)
SELECT r.ReturnInvoiceItemID,r.PartRef,r.ReferenceType,r.ReferenceRef,r.FinancialReturnDate,r.FinancialReturnQty,
 e.RelationPath,e.EntityType,e.EntityID,l.EntityName,
 CASE WHEN e.EntityID=r.ReferenceRef THEN 1 ELSE 0 END AS MatchesReferenceRef,
 vi.InventoryVoucherItemID AS MovementID,vi.PartRef AS MovementPartRef,vi.Date AS MovementDate,
 vi.MajorUnitQuantity AS MovementQty,vi.StoreRef,st.PlantRef,iv.InventoryVoucherSpecificationRef AS VoucherType,
 iv.State,iv.LedgerRef,vs.Direction,vs.TypeOfEffectOnStock,vs.Category,
 vi.ReferenceType AS MovementReferenceType,vi.ReferenceRef AS MovementReferenceRef,vi.ReturnableVoucherItemRef
FROM R r LEFT JOIN Edges e ON e.ReturnInvoiceItemID=r.ReturnInvoiceItemID
LEFT JOIN SYS3.EntityLookup l ON l.Code=e.EntityType
LEFT JOIN LGS3.InventoryVoucherItem vi ON e.EntityType=669 AND vi.InventoryVoucherItemID=e.EntityID
LEFT JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
LEFT JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
ORDER BY r.PartRef,r.ReturnInvoiceItemID,e.RelationPath,e.EntityType,e.EntityID OPTION(MAXDOP 1);
-- ReferenceIDProbe is an ID existence check only, not a confirmed relation.
-- Direct and Recursive may repeat the same edge: deduplicate before any quantity/value aggregation.
