-- V5.2E.6E.2A | 2026-11-24
-- This is a replacement SELECT, not a standalone query.
-- Use the previously successful V5.2E.6D CTE chain through AllocationControl.
-- Remove its final grouped SELECT; append ONLY the following SELECT.
-- Expected 32 unique physical deliveries. Do not use the failed E.6E.2 chain.
-- No temp tables, no writes, no ItemPrice.
SELECT DeliveryID, PartRef, PlantRef, StoreRef,
       DeliveryDate, GrossDeliveryQty, DistributionReturnQty,
       NetDeliveryQty, ValidInvoiceCount, TargetInvoiceCount,
       AdditionalInvoiceCount, MultiDeliveryInvoiceCount
FROM AllocationControl
WHERE AllocationStatus = 'MultiDeliveryAllocationNeedsReview'
ORDER BY PartRef, DeliveryID
OPTION (MAXDOP 1);
-- Next: pass the returned IDs as literal VALUES into independent SELECTs.
-- Check graph closure and quantity conservation outside source ERP.
-- If this query also fails compilation or exceeds 60 seconds, stop.
-- Use small fiscal-year or assignment-scoped extraction instead.
