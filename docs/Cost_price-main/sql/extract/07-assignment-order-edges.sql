-- Replace 0 locally with selected PartIDs.
;WITH Seed AS (SELECT PartID FROM (VALUES (CAST(0 AS bigint))) v(PartID))
SELECT DISTINCT a.AssignmentID,a.PlantRef,oi.OrderItemID,oi.OrderRef,oi.ProductRef,p.PartRef
FROM Seed s JOIN SLS3.Product p ON p.PartRef=s.PartID
JOIN SLS3.OrderItem oi ON oi.ProductRef=p.ProductID
JOIN DSD3.AssignmentDeliveryItem ad ON ad.OrderRef=oi.OrderRef
JOIN DSD3.Assignment a ON a.AssignmentID=ad.AssignmentRef
OPTION(MAXDOP 1);