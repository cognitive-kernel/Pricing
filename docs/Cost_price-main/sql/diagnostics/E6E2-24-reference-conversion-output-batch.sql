-- G.5 / G.6 / G.7 independent statements
;WITH T AS (
 SELECT DISTINCT vi.ReturnableVoucherItemRef AS OriginalID
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef IN(5,17,18,24)
 AND vi.ReturnableVoucherItemRef IS NOT NULL
), O AS (
 SELECT t.OriginalID,o.PartRef,o.StoreRef,o.Date AS OriginalDate,o.MajorUnitQuantity AS OriginalQty,
 h.InventoryVoucherSpecificationRef AS OriginalType,st.PlantRef AS OriginalPlant,
 CASE WHEN h.LedgerRef=1 AND h.State<>3 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13 AND o.MajorUnitQuantity>0 AND h.InventoryVoucherSpecificationRef IN(51,68,69,73) THEN 1 ELSE 0 END AS ValidOriginal
 FROM T t LEFT JOIN LGS3.InventoryVoucherItem o ON o.InventoryVoucherItemID=t.OriginalID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=o.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=o.StoreRef
), R AS (
 SELECT o.OriginalID,r.InventoryVoucherItemID AS ReturnID,r.MajorUnitQuantity AS Qty,
 st.PlantRef,r.StoreRef,h.FiscalYearRef,
 CASE WHEN h.InventoryVoucherSpecificationRef=CASE o.OriginalType WHEN 51 THEN 5 WHEN 68 THEN 17 WHEN 69 THEN 18 WHEN 73 THEN 24 END
 AND h.LedgerRef=1 AND h.State<>3 AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
 AND r.PartRef=o.PartRef AND r.MajorUnitQuantity>0 AND r.Date>=o.OriginalDate AND r.Date<'20261125' THEN 1 ELSE 0 END AS ValidReturn,
 CASE WHEN st.PlantRef=2 AND r.Date>='20220818' AND r.Date<'20261125' THEN 1 ELSE 0 END AS IsTarget,
 CASE WHEN st.PlantRef<>o.OriginalPlant OR st.PlantRef IS NULL OR o.OriginalPlant IS NULL THEN 1 ELSE 0 END AS CrossPlant,
 CASE WHEN r.StoreRef<>o.StoreRef THEN 1 ELSE 0 END AS CrossStore
 FROM O o JOIN LGS3.InventoryVoucherItem r ON r.ReturnableVoucherItemRef=o.OriginalID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=r.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=r.StoreRef
 WHERE r.Date<'20261125' OR r.Date IS NULL
), A AS (
 SELECT OriginalID,SUM(ValidReturn) AS ValidCount,
 SUM(CASE WHEN ValidReturn=1 THEN Qty ELSE 0 END) AS TotalQty,
 SUM(CASE WHEN ValidReturn=1 AND IsTarget=1 THEN 1 ELSE 0 END) AS TargetCount,
 SUM(CASE WHEN ValidReturn=1 AND IsTarget=0 THEN 1 ELSE 0 END) AS ExtraCount,
 SUM(CASE WHEN ValidReturn=0 THEN 1 ELSE 0 END) AS InvalidCount,
 SUM(CASE WHEN ValidReturn=1 THEN CrossPlant ELSE 0 END) AS CrossPlantCount,
 SUM(CASE WHEN ValidReturn=1 THEN CrossStore ELSE 0 END) AS CrossStoreCount,
 COUNT(DISTINCT CASE WHEN ValidReturn=1 THEN FiscalYearRef END) AS YearCount
 FROM R GROUP BY OriginalID
), C AS (
 SELECT o.*,a.ValidCount,a.TotalQty,a.TargetCount,a.ExtraCount,a.InvalidCount,a.CrossPlantCount,a.CrossStoreCount,a.YearCount,
 CASE WHEN o.ValidOriginal=0 THEN 'InvalidOriginal'
 WHEN COALESCE(a.ValidCount,0)=0 THEN 'NoValidReturn'
 WHEN a.TotalQty>o.OriginalQty+0.000001 THEN 'OverReturn'
 WHEN ABS(a.TotalQty-o.OriginalQty)<=0.000001 THEN 'FullReturn'
 ELSE 'PartialReturn' END AS QtyStatus
 FROM O o LEFT JOIN A a ON a.OriginalID=o.OriginalID
)
SELECT OriginalType,QtyStatus,COUNT_BIG(*) AS OriginalCount,SUM(TargetCount) AS TargetCount,SUM(ValidCount) AS AllValidCount,
 SUM(ExtraCount) AS ExtraCount,SUM(InvalidCount) AS InvalidCount,
 SUM(CASE WHEN ValidCount>1 THEN 1 ELSE 0 END) AS MultipleOriginalCount,
 SUM(CASE WHEN YearCount>1 THEN 1 ELSE 0 END) AS MultiYearCount,
 SUM(CrossPlantCount) AS CrossPlantCount,SUM(CrossStoreCount) AS CrossStoreCount,
 MAX(CASE WHEN TotalQty>OriginalQty THEN TotalQty-OriginalQty ELSE 0 END) AS MaxExcessQty,
 MIN(OriginalID) AS SampleOriginalID
FROM C GROUP BY OriginalType,QtyStatus ORDER BY OriginalType,QtyStatus OPTION(MAXDOP 1);

;WITH T AS (
 SELECT DISTINCT vi.ReturnableVoucherItemRef AS OriginalID
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
 AND iv.InventoryVoucherSpecificationRef=19 AND vi.ReturnableVoucherItemRef IS NOT NULL
), O AS (
 SELECT t.OriginalID,o.PartRef,o.Date AS OriginalDate,
 CASE WHEN h.InventoryVoucherSpecificationRef=70 AND h.LedgerRef=1 AND h.State<>3 AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13 AND o.MajorUnitQuantity>0 THEN 1 ELSE 0 END AS ValidOriginal,
 p.BaseQty,p.BaseValue,p.NetValue,p.OtherRows,o.MajorUnitQuantity AS OriginalQty
 FROM T t LEFT JOIN LGS3.InventoryVoucherItem o ON o.InventoryVoucherItemID=t.OriginalID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=o.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 OUTER APPLY (
 SELECT SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN Quantity END) AS BaseQty,
 SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN NetPrice END) AS BaseValue,
 SUM(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) THEN NetPrice END) AS NetValue,
 COUNT_BIG(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) AND NetPrice IS NOT NULL THEN NULL ELSE 1 END) AS OtherRows
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=t.OriginalID
 ) p
), R AS (
 SELECT o.OriginalID,r.InventoryVoucherItemID AS ReceiptID,r.PartRef,r.MajorUnitQuantity AS ReceiptQty,st.PlantRef,
 CASE WHEN h.InventoryVoucherSpecificationRef=19 AND h.LedgerRef=1 AND h.State<>3 AND vs.Direction=1 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13 AND r.Date>=o.OriginalDate AND r.Date<'20261125' AND r.MajorUnitQuantity>0 THEN 1 ELSE 0 END AS ValidReceipt,
 p.BaseQty,p.BaseValue,p.NetValue,p.OtherRows
 FROM O o JOIN LGS3.InventoryVoucherItem r ON r.ReturnableVoucherItemRef=o.OriginalID
 LEFT JOIN LGS3.InventoryVoucher h ON h.InventoryVoucherID=r.InventoryVoucherRef
 LEFT JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=h.InventoryVoucherSpecificationRef
 LEFT JOIN LGS3.Store st ON st.StoreID=r.StoreRef
 OUTER APPLY (
 SELECT SUM(CASE WHEN IsInput=1 AND PriceType=1 THEN Quantity END) AS BaseQty,
 SUM(CASE WHEN IsInput=1 AND PriceType=1 THEN NetPrice END) AS BaseValue,
 SUM(CASE WHEN IsInput=1 AND PriceType IN(1,8,9) THEN NetPrice END) AS NetValue,
 COUNT_BIG(CASE WHEN IsInput=1 AND PriceType IN(1,8,9) AND NetPrice IS NOT NULL THEN NULL ELSE 1 END) AS OtherRows
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.InventoryVoucherItemID
 ) p
 WHERE r.Date<'20261125' OR r.Date IS NULL
), A AS (
 SELECT OriginalID,SUM(ValidReceipt) AS ReceiptCount,
 SUM(CASE WHEN ValidReceipt=0 THEN 1 ELSE 0 END) AS InvalidCount,
 SUM(CASE WHEN ValidReceipt=1 AND (PlantRef<>2 OR PlantRef IS NULL) THEN 1 ELSE 0 END) AS ExternalCount,
 SUM(CASE WHEN ValidReceipt=1 AND BaseValue>0 AND ABS(BaseQty-ReceiptQty)<=0.000001 AND OtherRows=0 THEN 1 ELSE 0 END) AS PricedCount,
 SUM(CASE WHEN ValidReceipt=1 THEN NetValue END) AS ReceiptNet
 FROM R GROUP BY OriginalID
), C AS (
 SELECT o.*,a.ReceiptCount,a.InvalidCount,a.ExternalCount,a.PricedCount,a.ReceiptNet,
 CASE WHEN o.ValidOriginal=0 THEN 'InvalidOriginal'
 WHEN COALESCE(a.ReceiptCount,0)=0 THEN 'NoValidReceipt'
 WHEN o.BaseValue IS NULL THEN 'MissingOutputPrice'
 WHEN o.BaseValue<=0 OR o.OtherRows>0 OR o.BaseQty IS NULL OR ABS(o.BaseQty-o.OriginalQty)>0.000001 THEN 'OutputPriceReview'
 WHEN a.PricedCount<a.ReceiptCount THEN 'IncompleteInputPrice'
 WHEN ABS(a.ReceiptNet-o.NetValue)<=1 THEN 'ValueMatch'
 ELSE 'ValueDifference' END AS ValueStatus
 FROM O o LEFT JOIN A a ON a.OriginalID=o.OriginalID
)
SELECT ValueStatus,COUNT_BIG(*) AS OriginalCount,SUM(ReceiptCount) AS ReceiptCount,SUM(PricedCount) AS PricedCount,
 SUM(InvalidCount) AS InvalidCount,SUM(ExternalCount) AS ExternalCount,
 SUM(CASE WHEN ReceiptCount>1 THEN 1 ELSE 0 END) AS MultipleReceiptOriginalCount,
 SUM(CASE WHEN ValueStatus IN('ValueMatch','ValueDifference') THEN ABS(ReceiptNet-NetValue) END) AS AbsoluteValueDifference,
 MIN(OriginalID) AS SampleOriginalID
FROM C GROUP BY ValueStatus ORDER BY ValueStatus OPTION(MAXDOP 1);

;WITH R AS (
 SELECT vi.InventoryVoucherItemID AS MovementID,iv.InventoryVoucherSpecificationRef AS VoucherType,vi.MajorUnitQuantity AS Qty
 FROM LGS3.InventoryVoucherItem vi
JOIN LGS3.InventoryVoucher iv ON iv.InventoryVoucherID=vi.InventoryVoucherRef
JOIN LGS3.InventoryVoucherSpecification vs ON vs.InventoryVoucherSpecificationID=iv.InventoryVoucherSpecificationRef
JOIN LGS3.Store st ON st.StoreID=vi.StoreRef
WHERE st.PlantRef=2 AND iv.LedgerRef=1 AND iv.State<>3
AND vs.Direction=2 AND vs.TypeOfEffectOnStock=1 AND vs.Category<>13
AND vi.Date>='20220818' AND vi.Date<'20261125'
), P AS (
 SELECT r.*,p.AllRows,p.BaseQty,p.BaseValue,p.NetValue,p.AddRows,p.DeductRows,p.OtherRows
 FROM R r OUTER APPLY (
 SELECT COUNT_BIG(*) AS AllRows,
 SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN Quantity END) AS BaseQty,
 SUM(CASE WHEN IsInput=0 AND PriceType=1 THEN NetPrice END) AS BaseValue,
 SUM(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) THEN NetPrice END) AS NetValue,
 COUNT_BIG(CASE WHEN IsInput=0 AND PriceType=8 THEN 1 END) AS AddRows,
 COUNT_BIG(CASE WHEN IsInput=0 AND PriceType=9 THEN 1 END) AS DeductRows,
 COUNT_BIG(CASE WHEN IsInput=0 AND PriceType IN(1,8,9) AND NetPrice IS NOT NULL
 AND ((PriceType IN(1,8) AND NetPrice>=0 AND Effect=1) OR (PriceType=9 AND NetPrice<=0 AND Effect=-1)) THEN NULL ELSE 1 END) AS OtherRows
 FROM LGS3.ItemPrice WHERE InventoryVoucherItemRef=r.MovementID
 ) p
), C AS (
 SELECT *,CASE WHEN AllRows=0 THEN 'NoItemPrice'
 WHEN OtherRows>0 THEN 'OtherComponentReview'
 WHEN BaseValue IS NULL OR BaseValue<=0 OR NetValue IS NULL OR NetValue<=0 THEN 'BaseOrNetReview'
 WHEN Qty IS NULL OR Qty<=0 OR BaseQty IS NULL OR ABS(BaseQty-Qty)>0.000001 THEN 'QuantityReview'
 ELSE 'SignedOutputPriceCandidate' END AS PriceStatus
 FROM P
)
SELECT VoucherType,PriceStatus,COUNT_BIG(*) AS MovementCount,
 SUM(CASE WHEN AddRows>0 THEN 1 ELSE 0 END) AS WithType8,
 SUM(CASE WHEN DeductRows>0 THEN 1 ELSE 0 END) AS WithType9,
 SUM(BaseValue) AS BaseValue,SUM(NetValue) AS CandidateNetValue,
 MIN(MovementID) AS SampleMovementID
FROM C GROUP BY VoucherType,PriceStatus ORDER BY VoucherType,PriceStatus OPTION(MAXDOP 1);
