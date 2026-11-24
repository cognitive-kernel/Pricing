-- E6E2E.2: inspect reserve-item references for selected physical deliveries.
-- Replace placeholder 0 with local delivery IDs. No writes.
;WITH Seeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
)
SELECT DISTINCT
 d.InventoryVoucherItemID AS DeliveryID,d.PartRef,d.StoreRef,d.MajorUnitQuantity AS DeliveryQty,
 d.ReferenceType AS DeliveryReferenceType,d.ReferenceRef AS DeliveryReferenceRef,
 rd.ReserveDeliveryID,rvi.ReserveItemID,rvi.ReserveRef,
 rv.ReferenceType AS ReserveHeaderReferenceType,rv.ReferenceRef AS AssignmentCandidateID,
 rvi.ReferenceType AS ReserveItemReferenceType,rvi.ReferenceRef AS ReserveItemReferenceRef,
 rvi.PartRef AS ReservePartRef,rvi.PlantRef AS ReservePlantRef,rvi.StoreRef AS ReserveStoreRef,
 rvi.RowNumber,rvi.State AS ReserveItemState,rvi.MajorUnitQuantity AS ReserveQty,
 rvi.EffectedMajorQuantity,rvi.CounterpartEntityCode,rvi.CounterpartEntityRef,
 oi.OrderItemID AS MatchingOrderItemID,oi.OrderRef AS MatchingOrderID,
 op.PartRef AS MatchingOrderPartRef,oi.MajorUnitQuantity AS OrderQty,
 CASE WHEN oi.OrderItemID IS NULL THEN 'NoOrderItemIDMatch'
 WHEN op.PartRef IS NULL OR op.PartRef<>d.PartRef THEN 'OrderPartMismatch'
 WHEN EXISTS (SELECT 1 FROM DSD3.AssignmentDeliveryItem adi
 WHERE adi.AssignmentRef=rv.ReferenceRef AND adi.OrderRef=oi.OrderRef)
 THEN 'SamePartAndAssignmentOrderCandidate'
 ELSE 'OrderOutsideAssignment' END AS ReferenceCheck,
 rd.InventoryVoucherItemTrackingFactorRef,rd.ReserveItemTrackingFactorRef
FROM Seeds s
JOIN LGS3.InventoryVoucherItem d ON d.InventoryVoucherItemID=s.DeliveryID
LEFT JOIN LGS3.ReserveDelivery rd ON rd.InventoryVoucherItemRef=d.InventoryVoucherItemID
LEFT JOIN LGS3.ReserveItem rvi ON rvi.ReserveItemID=rd.ReserveItemRef
LEFT JOIN LGS3.Reserve rv ON rv.ReserveID=rvi.ReserveRef
LEFT JOIN SLS3.OrderItem oi ON oi.OrderItemID=rvi.ReferenceRef
LEFT JOIN SLS3.Product op ON op.ProductID=oi.ProductRef
ORDER BY d.PartRef,DeliveryID,rvi.ReserveItemID OPTION(MAXDOP 1);
-- Numeric ID matches alone do not prove polymorphic reference type.
