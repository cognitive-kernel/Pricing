DECLARE @PlantRef BIGINT = 2;
DECLARE @LedgerRef BIGINT = 1;
DECLARE @FiscalYearRef BIGINT = 9;

;WITH cte_stock AS (
    SELECT
        p.PartID,
        p.Code,
        p.Name,
        pr.secondcode,
        SUM(
            CASE
                WHEN s.Direction = 1
                    THEN ISNULL(i.MajorUnitQuantity, 0)
                ELSE -ISNULL(i.MajorUnitQuantity, 0)
            END
        ) AS MajorUnit_Remaining
    FROM LGS3.InventoryVoucherItem AS i
    INNER JOIN LGS3.InventoryVoucher AS v
        ON v.InventoryVoucherID = i.InventoryVoucherRef
    INNER JOIN LGS3.InventoryVoucherSpecification AS s
        ON s.InventoryVoucherSpecificationID = v.InventoryVoucherSpecificationRef
    INNER JOIN LGS3.Store AS st
        ON st.StoreID = i.StoreRef
    INNER JOIN LGS3.Part AS p
        ON p.PartID = i.PartRef
    LEFT JOIN SLS3.Product AS pr
        ON p.PartID = pr.PartRef
    WHERE
        st.PlantRef = @PlantRef
        AND v.LedgerRef = @LedgerRef
        AND i.Date < DATEADD(DAY, 1, CONVERT(date, GETDATE()))
        AND i.FiscalYearRef = @FiscalYearRef
        AND v.InventoryVoucherSpecificationRef NOT IN (99, 999, 9999)
        AND s.TypeOfEffectOnStock = 1
        AND i.State <> 3
        AND s.Category <> 14
        AND (
            v.InventoryVoucherSpecificationRef NOT IN (1, 11, 111)
            OR i.ReturnableVoucherItemRef IS NULL
            OR i.FiscalYearRef = @FiscalYearRef
        )
    GROUP BY p.PartID, p.Code, p.Name, pr.secondcode
    HAVING SUM(
        CASE
            WHEN s.Direction = 1 THEN ISNULL(i.MajorUnitQuantity, 0)
            ELSE -ISNULL(i.MajorUnitQuantity, 0)
        END
    ) > 0
),
cte_Price_Announcement AS (
    SELECT
        ROW_NUMBER() OVER (
            PARTITION BY pli.ProductRef
            ORDER BY pl.StartDate DESC
        ) AS rn_pa,
        p.Number,
        p.Name,
        p.UnitRef AS unit_id,
        pli.Fee,
        u.Name AS unit,
        sys3.fn_DateToShamsiDate(pl.StartDate) AS [date],
        DATEDIFF(DAY, pl.StartDate, GETDATE()) AS diffdate
    FROM SLS3.PriceListItem AS pli
    LEFT JOIN SLS3.PriceList AS pl
        ON pli.PriceListRef = pl.PriceListID
    LEFT JOIN SLS3.Product AS p
        ON pli.ProductRef = p.ProductID
    LEFT JOIN gnr3.Unit AS u
        ON p.UnitRef = u.UnitID
),
cte_Price_invoice AS (
    SELECT
        ROW_NUMBER() OVER (
            PARTITION BY ii.ProductRef
            ORDER BY ii.[Date] DESC
        ) AS rn_pi,
        p.Number,
        p.Name,
        ii.PriceBaseFee,
        u.Name AS unit,
        sys3.fn_DateToShamsiDate(ii.[Date]) AS [date],
        DATEDIFF(DAY, ii.[Date], GETDATE()) AS diffdate
    FROM SLS3.InvoiceItem AS ii
    LEFT JOIN SLS3.Product AS p
        ON ii.ProductRef = p.ProductID
    LEFT JOIN gnr3.Unit AS u
        ON p.UnitRef = u.UnitID
),
cte_price AS (
    SELECT
        cpa.Number AS code,
        cpa.Name AS name,
        cpa.[date] AS Announcement_date,
        cpa.Fee AS Announcement_price,
        cpa.unit AS Announcement_unit,
        cpa.unit_id AS Announcement_unit_id,
        cpa.diffdate AS Announcement_diff,
        pin.[date] AS invoice_date,
        pin.PriceBaseFee AS invoice_price,
        pin.unit AS invoice_unit,
        pin.diffdate AS invoice_diff
    FROM cte_Price_Announcement AS cpa
    LEFT JOIN cte_Price_invoice AS pin
        ON cpa.Number = pin.Number
        AND pin.rn_pi = 1
    WHERE cpa.rn_pa = 1
)
SELECT
    s.PartID,
    s.Code,
    s.Name,
    s.SecondCode AS secondcode,
    s.MajorUnit_Remaining,
    p.Announcement_date,
    p.Announcement_price,
    p.Announcement_unit,
    p.Announcement_unit_id,
    p.Announcement_diff,
    p.invoice_date,
    p.invoice_price,
    p.invoice_unit,
    p.invoice_diff
FROM cte_stock AS s
LEFT JOIN cte_price AS p
    ON s.Code = p.code
ORDER BY s.PartID DESC;
