-- E6E2E.3: inspect direct entity relations at both ends of seed deliveries
-- Replace placeholder with local delivery IDs. Only SELECT.
;WITH Seeds AS (
 SELECT DeliveryID FROM (VALUES (CAST(0 AS bigint))) v(DeliveryID)
), D AS (
 SELECT vi.InventoryVoucherItemID AS DeliveryID,vi.ReferenceType,vi.ReferenceRef
 FROM Seeds s JOIN LGS3.InventoryVoucherItem vi ON vi.InventoryVoucherItemID=s.DeliveryID
), Edges AS (
 SELECT d.DeliveryID,d.ReferenceType,d.ReferenceRef,'Incoming' AS RelationDirection,e.SourceType AS RelatedEntityType,e.SourceRef AS RelatedEntityID
 FROM D d JOIN SYS3.EntityRelation e ON e.TargetType=669 AND e.TargetRef=d.DeliveryID
 UNION
 SELECT d.DeliveryID,d.ReferenceType,d.ReferenceRef,'Outgoing',e.TargetType,e.TargetRef
 FROM D d JOIN SYS3.EntityRelation e ON e.SourceType=669 AND e.SourceRef=d.DeliveryID
)
SELECT RelationDirection,RelatedEntityType,ReferenceType,COUNT_BIG(*) AS RelationCount,
 COUNT(DISTINCT DeliveryID) AS DeliveryCount,
 SUM(CASE WHEN RelatedEntityID=ReferenceRef THEN 1 ELSE 0 END) AS ExactReferenceIDMatches,
 COUNT(DISTINCT CASE WHEN RelatedEntityID=ReferenceRef THEN DeliveryID END) AS MatchedDeliveryCount
FROM Edges GROUP BY RelationDirection,RelatedEntityType,ReferenceType
ORDER BY MatchedDeliveryCount DESC,RelationDirection,RelatedEntityType OPTION(MAXDOP 1);
