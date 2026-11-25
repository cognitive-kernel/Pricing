# گزارش تحلیل مانیتورینگ ثبت اولیه اعلامیه قیمت راهکاران

> منبع: یک فایل `.xel` ارسالی کاربر در ۲۰۲۶/۱۱/۲۵  
> روش: بازرسی ایستای ساختار باینری و متن‌های UTF-16LE ثبت‌شده، بدون اجرای فراخوانی SQL یا اسمبلی‌های راهکاران  
> **محدودیت:** این بررسی جایگزین بازخوانی کامل رویدادها با `sys.fn_xe_file_target_read_file` نیست؛ موقعیت‌ها در این گزارش offset بایتی‌اند، نه زمان دقیق رویداد.  
> **ایمنی:** متن خام همه رویدادها بازنشر نشده؛ متن نمونه SQLهای واقعی فقط با پارامترهای نمادین نمایش داده شده است. **هرگز این دستورات را مستقیماً روی دیتابیس عملیاتی اجرا نکنید.**

## نتیجه قطعی در فایل XEL

- درج سربرگ `SLS3.PriceList` با `PriceListID=48478`، `Number=46996` و **`State=1`** دیده شد.
- درج **یک** ردیف کالا در `SLS3.PriceListItem` با `PriceListItemID=2296935`، `PriceListRef=48478` و `ProductRef=29770` دیده شد.
- درج **سه** ردیف `SLS3.PriceListResultRow` با شناسه‌های `79797`، `79798` و `79799`، مرتبط با اعلامیه 48478 و `PaymentAgreementRef`های **20، 21 و 23** دیده شد.
- فراخوانی **`SYS3.spAddNumberingMethodReservation`** با `NumberingMethodRangeID=77`، `DomainKey=N''` و `ReservedValue=46996` ثبت شده است.
- پیش از ذخیره، `SYS3.spGetNextId` برای `SLS3.PriceList` (`IncValue=1`)، `SLS3.PriceListItem` (`IncValue=1`) و `SLS3.PriceListResultRow` (`IncValue=3`) دیده شد.
- قبل از درج سربرگ، یک `SELECT EXISTS` روی `SLS3.PriceList` برای بررسی تکراری‌نبودن `Number=46996` اجرا شده است.
- در این فایل **فراخوانی مشخصی که محاسبه عدد 46996 را پیش از رزرو نشان دهد شناسایی نشده**؛ رزرو به معنی کشف الگوریتم تولید نیست.
- صرف وجود رویداد `rpc_completed` فقط تکمیل فراخوانی را نشان می‌دهد، نه الزاماً موفقیت نهایی و COMMIT؛ برای اطمینان از ماندگاری داده‌ها، `SELECT` خواندنی روی دیتابیس لازم است. جزئیات Begin/Commit تراکنش و هماهنگی هم‌زمان هنوز ثابت نشده‌اند.

## ترتیب مشاهده‌شده در فایل

```text
SYS3.spGetNextId(TableName='SLS3.PriceList', IncValue=1)        → PriceListID=48478
SYS3.spGetNextId(TableName='SLS3.PriceListItem', IncValue=1)    → PriceListItemID=2296935
SYS3.spGetNextId(TableName='SLS3.PriceListResultRow', IncValue=3) → IDs 79797..79799
SYS3.spAddNumberingMethodReservation(Range=77, DomainKey='', ReservedValue=46996)
SELECT EXISTS FROM SLS3.PriceList WHERE Number='46996' AND PriceListID<>48478
INSERT INTO SLS3.PriceList (State=1)
INSERT INTO SLS3.PriceListItem (PriceListRef=48478)
INSERT INTO SLS3.PriceListResultRow (PaymentAgreementRef=20)
INSERT INTO SLS3.PriceListResultRow (PaymentAgreementRef=21)
INSERT INTO SLS3.PriceListResultRow (PaymentAgreementRef=23)
```

ترتیب بالا ترتیب وقوع متن دستورها در فایل است؛ وجود نشست‌های موازی را نفی نمی‌کند و لزوماً نمایانگر تمام فراخوانی‌های داخلی برنامه نیست.

## SQL واقعی استخراج‌شده — با پارامترهای نمادین و بدون مقادیر عملیاتی

### 01. سربرگ

```sql
INSERT INTO [SLS3].[PriceList]([PriceListID], [ConfirmationDate], [CreationDate], [Creator], [CurrencyRef], [EndDate], [FeeMapperPolicyRef], [FeePolicyRef], [LastModificationDate], [LastModifier], [Number], [PriceListParametersRef], [PriceListType], [SalesDocumentType], [StartDate], [State], [Title]) 
VALUES (@p0, @p1, @p2, @p3, @p4, @p5, @p6, @p7, @p8, @p9, @p10, @p11, @p12, @p13, @p14, @p15, @p16)

SELECT [t0].[Version]
FROM [SLS3].[PriceList] AS [t0]
WHERE [t0].[PriceListID] = @p0
```

### 02. ردیف کالا

```sql
INSERT INTO [SLS3].[PriceListItem]([PriceListItemID], [AdditionTolerance], [BatchRef], [CurrencyRef], [Definitiveness], [Fee], [PackageRef], [PartTrackingFactorRef1], [PartTrackingFactorRef2], [PartTrackingFactorRef3], [PartTrackingFactorRef4], [PartTrackingFactorRef5], [PriceListRef], [ProductGroupRef], [ProductRef], [ProductUnitRef], [Quantity], [ReductionTolerance], [RowNumber], [TrackingFactor1], [TrackingFactor2], [TrackingFactor3], [TrackingFactor4], [TrackingFactor5], [TrackingFactorInputMode1], [TrackingFactorInputMode2], [TrackingFactorInputMode3], [TrackingFactorInputMode4], [TrackingFactorInputMode5], [TrackingFactorValue], [Weight]) 
VALUES (@p0, @p1, @p2, @p3, @p4, @p5, @p6, @p7, @p8, @p9, @p10, @p11, @p12, @p13, @p14, @p15, @p16, @p17, @p18, @p19, @p20, @p21, @p22, @p23, @p24, @p25, @p26, @p27, @p28, @p29, @p30)

SELECT [t0].[Version]
FROM [SLS3].[PriceListItem] AS [t0]
WHERE [t0].[PriceListItemID] = @p0
```

### 03. توافق پرداخت

```sql
INSERT INTO [SLS3].[PriceListResultRow]([PriceListResultRowID], [BrokerRef], [CustomerRef], [DistributorRef], [DivisionRef], [GroupCustomerRef], [InventoryRef], [PaymentAgreementRef], [PlantRef], [PriceListRef], [ProductPackRef], [RegionalDivisionRef], [RouteRef], [SalesAreaRef], [SalesChannelRef], [SalesGroupRef], [SalesOfficeRef], [SalesOrganizationRef], [SalesTerritoryRef], [SalesTypeRef], [ShippingPointRef], [TermOfDeliveryRef], [TermOfPaymentRef], [Type], [Weight]) 
VALUES (@p0, @p1, @p2, @p3, @p4, @p5, @p6, @p7, @p8, @p9, @p10, @p11, @p12, @p13, @p14, @p15, @p16, @p17, @p18, @p19, @p20, @p21, @p22, @p23, @p24)

SELECT [t0].[Version]
FROM [SLS3].[PriceListResultRow] AS [t0]
WHERE [t0].[PriceListResultRowID] = @p0
```

## پارامترهای تأییدشده از رویدادهای این اعلامیه

| مفهوم | مقدار مشاهده‌شده |
|---|---|
| PriceListID | 48478 |
| Number | 46996 |
| State | 1 |
| PriceListParametersRef | 2 |
| PriceListType | 1 |
| SalesDocumentType | 2 |
| CurrencyRef | 1 |
| Creator | 231 |
| CreationDate | 2026-11-25 14:43:40.300 |
| StartDate | 2026-11-25 14:43:00 |
| PriceListItemID | 2296935 |
| ProductRef | 29770 |
| ProductUnitRef | 1 |
| RowNumber | 1 |
| PriceListResultRowIDs | 79797, 79798, 79799 |
| PaymentAgreementRefs | 20, 21, 23 |
| NumberingMethodRangeID | 77 |
| DomainKey | رشته خالی |
| ReservedValue | 46996 |

**مبلغ `Fee` و عنوان سند عمداً در گزارش خلاصه بازنشر نشده‌اند.** این مقادیر در XEL مشاهده شدند اما برای اثبات ساختار ثبت لازم نیستند.

## استعلام صرفاً خواندنی برای کنترل نتیجه روی دیتابیس عملیاتی

```sql
SELECT PriceListID, Number, State, CreationDate, StartDate
FROM SLS3.PriceList WHERE PriceListID = 48478;

SELECT PriceListItemID, PriceListRef, ProductRef, ProductUnitRef, RowNumber
FROM SLS3.PriceListItem WHERE PriceListRef = 48478;

SELECT PriceListResultRowID, PriceListRef, PaymentAgreementRef
FROM SLS3.PriceListResultRow WHERE PriceListRef = 48478;

SELECT NumberingMethodRangeRef, DomainKey, ReservedSerial
FROM SYS3.NumberingRangeReservation
WHERE NumberingMethodRangeRef = 77 AND ReservedSerial = 46996;
```

## نتیجه برای پروژه Pricing

این ردیابی ساختار واقعی SQL ثبت پیش‌نویس، ارتباط داخلی شناسه‌ها و روش رزرو شماره را **در این مورد** تأیید می‌کند. بااین‌حال تبدیل دستورها به عملیات مستقیم SQL در محیط عملیاتی هنوز مجاز یا ایمن ارزیابی نشده؛ زیرا تولید عدد تجاری، رزرو اتمیک، سازگاری با UI و Rules/validation، مدیریت تراکنش، rollback و هم‌زمانی هنوز اثبات نشده‌اند. تا مشخص‌شدن مسیر رسمی، عملیات نوشتن Pricing روی دیتابیس اصلی غیرفعال می‌ماند. آزمایش کد مبتنی بر این ساختار، فقط در دیتابیس تست بازیابی‌شده انجام می‌شود.
