# مستند تحقیق و آزمون ثبت پیش‌نویس اعلامیه قیمت در راهکاران

> مخزن مصرف‌کننده: `cognitive-kernel/Pricing`  
> مسیر: `docs/RAHKARAN-PRICE-REGISTRATION-RESEARCH.fa.md`  
> تاریخ بازنگری: ۱۴۰۵/۰۸/۲۵ (۲۰۲۶/۱۱/۱۶)  
> وضعیت: **ساختار پیش‌نویس در دیتابیس بازیابی‌شده آزموده شده؛ ثبت عملیاتی هنوز تأیید نشده است.**  
> دامنه قطعی: **فقط ایجاد پیش‌نویس اعلامیه قیمت (`State=1`)؛ بدون تأیید، فعال‌سازی یا ایجاد/خاتمه قوانین قیمت.**

## ۱. خلاصه مدیریتی و سطح اطمینان

پروژه Pricing باید بتواند قیمت‌های تأییدشده در *خود پروژه Pricing* را، پس از انتخاب و تأیید ارسال توسط کاربر، در راهکاران **صرفاً به‌عنوان پیش‌نویس** ثبت کند. تأیید در Pricing به معنی تأیید PriceList در راهکاران نیست. اجرای عملیات تأیید راهکاران، تغییر `State` به ۲، ساخت قوانین قیمت و خاتمه قوانین قبلی از دامنه فعلی خارج است.

سه سطح شواهد را نباید با هم اشتباه گرفت:

1. **ساختار مشاهده‌شده:** جدول‌ها، ستون‌ها، روابط و الگوهای نمونه‌داده در نسخه بررسی‌شده راهکاران.
2. **آزمون اجراشده:** اسکریپت ایجاد پیش‌نویس روی SQL Server آزمایشی `IT-Soleymani` و دیتابیس بازیابی‌شده `Rahkaran_sg3`، با نتیجه موفق و Rollback مستند.
3. **سازگاری عملیاتی تأییدنشده:** تخصیص رسمی شماره، اجرای هم‌زمان با UI راهکاران، سازگاری کامل با قواعد برنامه، idempotency و بررسی پیش‌نویس در UI.

**نتیجه:** الگوی درج سربرگ، کالا و ردیف‌های توافق پرداخت در دیتابیس آزمایشی با موفقیت آزموده شده است. این موفقیت به‌تنهایی مجوز درج در دیتابیس عملیاتی نیست.

## ۲. وضعیت پروژه Pricing و مسیر یکپارچه‌سازی

طبق بررسی ثبت‌شده از کد پروژه:

- اتصال راهکاران از `app/Services/RahkaranPriceService.php` و `PDO_SQLSRV` انجام می‌شود.
- تنظیمات محیطی شامل `RAHKARAN_SERVER`، `RAHKARAN_DATABASE`، `RAHKARAN_UID`، `RAHKARAN_PWD`، `RAHKARAN_CHARSET` و `RAHKARAN_TRUST_SERVER_CERTIFICATE` است.
- `app/Controllers/PriceApprovalController.php` صف تأیید Pricing را مدیریت می‌کند: `Approved=0` در انتظار، `Approved=1` تأیید و `Approved=2` رد.
- منطق موجود خروجی اکسل فقط گروه‌هایی را صادر می‌کند که تمام ردیف‌هایشان تأیید شده‌اند و `isexported=0` دارند.
- گروه‌بندی در `MarketPriceCalculator::groupByPaymentAgreement()` بر اساس `PaymentAgreementID` است.
- `dbo.PriceApprove` و `ProductPriceMonitor.isexported` در مسیر خروجی اکسل استفاده می‌شوند.
- مسیرهای برنامه در `routes/web.php`، منوها در `views/layout.php` و مجوزها در `database/seed.php` و `PermissionService` تعریف شده‌اند.

**اصلاح مهم:** خروجی اکسل و «ثبت در راهکاران» باید دو جریان مستقل با وضعیت و تاریخچه مستقل باشند. تأیید قیمت در Pricing مجوز ساخت پیش‌نویس است، نه تأیید یا اعمال قیمت در راهکاران.

## ۳. محدوده نهایی INSERT برای پیش‌نویس

| جدول | عملیات موردنیاز | تعداد در آزمون یک‌کالا/دو توافق |
|---|---|---:|
| `SLS3.PriceList` | INSERT سربرگ | ۱ |
| `SLS3.PriceListItem` | INSERT کالا و قیمت | ۱ |
| `SLS3.PriceListResultRow` | INSERT توافق پرداخت | ۲ |
| `SYS3.NumberingRangeReservation` | رزرو شماره در آزمون؛ **مسیر رسمی هنوز اثبات نشده** | ۱ |
| `SLS3.PriceListConditionRow` | **بدون تغییر** | ۰ |
| `GNR3.BusinessPolicyConditionRow` | **بدون تغییر** | ۰ |
| `GNR3.BusinessPolicyInputValue` | **بدون تغییر** | ۰ |
| `GNR3.BusinessPolicyOutputValue` | **بدون تغییر** | ۰ |

در این دامنه `State=1` درج می‌شود و هیچ عملیات تأیید، ساخت قانون، تغییر قانون فعال، خاتمه قانون قبلی یا تولید XML/Hash سیاست قیمت انجام نمی‌شود.

**دقت:** تعداد ResultRow باید از توافق‌های واقعی انتخاب‌شده در Pricing تعیین شود. وجود دو ردیف در آزمون، قاعده عمومی «همیشه دو توافق» نیست.

## ۴. ساختار جداول و روابط اصلی

### ۴.۱. `SLS3.PriceList`

| ستون | مشخصات مشاهده‌شده |
|---|---|
| `PriceListID` | bigint، PK، بدون Identity |
| `PriceListParametersRef` | bigint، اجباری؛ FK به پارامترهای PriceList |
| `Number` | nvarchar(100)، اجباری؛ **شماره تجاری، متفاوت از ID** |
| `Title` | nvarchar(512)، اجباری |
| `StartDate` | datetime، اجباری |
| `EndDate` | datetime، nullable |
| `PriceListType` | int، اجباری |
| `SalesDocumentType` | int، اجباری |
| `CurrencyRef` | bigint، اجباری |
| `State` | int، اجباری |
| `CreationDate`, `LastModificationDate` | datetime، اجباری |
| `Creator`, `LastModifier` | bigint، اجباری؛ FK به `SYS3.User` |
| `FeeMapperPolicyRef`, `FeePolicyRef` | bigint، nullable |
| `ConfirmationDate` | datetime، nullable |
| `Version` | rowversion؛ **نباید در INSERT مقداردهی شود** |

### ۴.۲. `SLS3.PriceListItem`

| ستون | مشخصات مشاهده‌شده |
|---|---|
| `PriceListItemID` | bigint، PK، بدون Identity |
| `PriceListRef` | bigint، FK به PriceList |
| `RowNumber` | int، اجباری؛ از ۱ شروع می‌شود |
| `ProductRef` | bigint، nullable؛ در سناریوی کالا مقدار دارد |
| `ProductGroupRef` | bigint، nullable |
| `ProductUnitRef` | bigint، اجباری؛ FK به `GNR3.Unit` |
| `PackageRef`, `Quantity` | nullable؛ Quantity از نوع decimal(28,6) |
| `CurrencyRef` | bigint، اجباری |
| `Fee` | decimal(28,6)، اجباری |
| `Definitiveness` | int، اجباری؛ در نمونه ۱ |
| `ReductionTolerance`, `AdditionTolerance` | decimal(28,6)، nullable |
| `TrackingFactor1..5`, `PartTrackingFactorRef1..5`, `TrackingFactorInputMode1..5`, `TrackingFactorValue`, `BatchRef` | فیلدهای جانبی nullable، بسته به سناریو |
| `Version` | rowversion؛ نباید در INSERT مقداردهی شود |

### ۴.۳. `SLS3.PriceListResultRow`

- `PriceListResultRowID` کلید اصلی bigint و غیر Identity است.
- `PriceListRef` به سربرگ اشاره می‌کند.
- `PaymentAgreementRef` توافق پرداخت را مشخص می‌کند.
- سایر فیلدهای مشاهده‌شده عمدتاً nullable هستند؛ `Version` را نباید درج کرد.

### ۴.۴. `SLS3.PriceListConditionRow` و سیاست‌ها

جدول ConditionRow به PriceList، Item، ResultRow و ساختار BusinessPolicy وصل است. برای **ثبت پیش‌نویس** هیچ رکوردی در آن درج نمی‌شود. جزئیات آن در بخش ۱۰ صرفاً برای حفظ نتایج تحقیق تاریخی آمده است و جزو عملیات اجرایی فعلی نیست.

### ۴.۵. کنترل روابط

کلیدهای خارجی، CHECK، DEFAULT و ایندکس‌ها باید از **همان دیتابیس مقصد** بازخوانی شوند. وجود FK جداگانه برای محصول و واحد، لزوماً مجاز بودن **زوج محصول/واحد** را ثابت نمی‌کند؛ این زوج باید با `SLS3.ProductUnit` کنترل شود.

## ۵. شناسه داخلی و شماره تجاری: دو موضوع مستقل

### ۵.۱. شناسه داخلی

برای `PriceListID`، `PriceListItemID` و `PriceListResultRowID` از روش شناسایی‌شده راهکاران استفاده می‌شود:

```sql
EXEC SYS3.spGetNextId 'SLS3.PriceList', @PriceListID OUTPUT;
EXEC SYS3.spGetNextId 'SLS3.PriceListItem', @PriceListItemID OUTPUT;
EXEC SYS3.spGetNextId 'SLS3.PriceListResultRow', @ResultRowID OUTPUT;
```

این Stored Procedure از Sequenceهای داخلی با الگوی `SYS3_TableIdGen__<TableName>` و اطلاعات ReplicationSiteId استفاده می‌کند. **Sequence با ROLLBACK به عقب برنمی‌گردد.** در صورت نبود Sequence، خود Procedure ممکن است آن را ایجاد کند؛ حتی خواندن با `@IncValue=0` الزاماً بدون اثر جانبی نیست.

استفاده از `MAX(PriceListID)+1` یا ساخت دستی شناسه داخلی ممنوع است.

### ۵.۲. شماره تجاری PriceList

روش مشاهده‌شده:

| ویژگی | مقدار مشاهده‌شده |
|---|---|
| `SYS3.NumberingMethod.NumberingMethodID` | 77 |
| عنوان | اعلامیه قیمت |
| Component | `SystemGroup.Sales.PriceList` |
| Entity | `PriceList` |
| قالب | `*سریال` |
| `NumberType` | 1 |
| `NumberDigitsType` | 2 |
| `NumberOfDigits` | 0 |
| `AssignApproch` | 1 |
| `CheckUniqueness` | 1 |
| `MissedNumberBehavior` | 0 |
| `NumberingMethodRangeID` | 77 |
| `StartSerial` / `SerialStep` | 1 / 1 |
| `FinishSerial` | NULL |
| DomainKey نمونه‌های مشاهده‌شده | رشته خالی |

روش ۲۰ مربوط به `SystemGroup.Logistics.VoucherPricing` و روش ۳۰۷ مربوط به TriplePriceList است؛ برای اعلامیه قیمت فروش عادی، روش ۷۷ مرتبط‌ترین مورد مشاهده‌شده است.

### ۵.۳. جدول رزرو شماره

`SYS3.NumberingRangeReservation` دارای ستون‌های زیر است:

- `NumberingRangeReservationID bigint IDENTITY(1,1)`؛ توسط دیتابیس تولید می‌شود.
- `NumberingMethodRangeRef bigint NOT NULL`.
- `DomainKey nvarchar(1000) NOT NULL`.
- `ReservedSerial bigint NOT NULL`.

ایندکس یکتا: `UIX_SYS3_NumberingRangeReservation_NumberingMethodRangeRef_DomainKey_ReservedSerial`.

تطبیق داده‌ای قبلی نشان داد ۱۰۷ رزرو Range=77 در بازه ۴۶۵۰۶ تا ۴۶۶۱۵ با ۱۰۷ PriceList متمایز تطبیق داشته‌اند. این مشاهده رابطه قوی میان شماره تجاری و رزرو را نشان می‌دهد، اما ترتیب رسمی فراخوانی‌ها، نحوه انتخاب شماره، یا تضمین هم‌زمانی را ثابت نمی‌کند.

### ۵.۴. Procedureهای مرتبط با شماره‌گذاری

| Procedure | رفتار مشاهده‌شده | استفاده در ثبت فعلی |
|---|---|---|
| `SYS3.CreateNumberingMethod` | ایجاد تنظیمات روش شماره‌گذاری در صورت نبود | خیر |
| `SYS3.CreateNumberingMethodReservation` | درج اولیه تنها در نبود هر رزرو برای Range | خیر |
| `SYS3.spAddNumberingMethodReservation` | درج رزرو مشخص در صورت نبود ترکیب سه‌گانه | مسیر کامل تخصیص شماره نیست |
| `SYS3.spRemoveNumberingMethodReservation` | حذف رزرو مشخص | خیر |
| `SYS3.spRemoveOldNumberingReservations` | حذف رزروهای قدیمی‌تر از ۵۰ شماره اخیر هر دامنه | خیر |

در متن مشاهده‌شده `spAddNumberingMethodReservation`، خطای 2601 مربوط به ایندکس یکتا به‌طور خاص مدیریت می‌شود و مسیر Catch ممکن است تراکنش فراخواننده را Rollback کند. این Procedure مقدار `ReservedValue` را **از فراخواننده دریافت می‌کند** و خود الگوریتم انتخاب شماره بعدی نیست.

جست‌وجوی متنی در `sys.sql_modules`، Jobها، Triggerها و CLRها مسیر کامل تولید شماره را پیدا نکرد. این نتیجه، وجود منطق در کد کلاینت، ماژول رمزگذاری‌شده یا سرویس خارجی را رد نمی‌کند.

### ۵.۵. الگوریتم آزمایشی و محدودیت آن

در اسکریپت آزمون، شماره پیشنهادی با بزرگ‌ترین مقدار عددی `PriceList.Number` و بزرگ‌ترین `ReservedSerial` برای Range=77/DomainKey خالی، به‌علاوه ۱، انتخاب شد؛ سپس رزرو مستقیماً درج شد.

**این روش در محیط آزمایشی کار کرده است، اما الگوریتم رسمی راهکاران اثبات نشده و برای اجرای هم‌زمان عملیاتی قابل اتکا نیست.** `sp_getapplock` اختصاصی ما تنها نویسندگانی را هماهنگ می‌کند که همان Resource را قفل می‌کنند؛ لزوماً نرم‌افزار راهکاران را هماهنگ نمی‌کند.

## ۶. پارامترها و داده‌های مرجع

### ۶.۱. پارامتر PriceList

در `SLS3.PriceListParameters` دو رکورد مشاهده شده است:

| ID | StartDate | EndDate | PriceListType | SalesDocumentType | Model |
|---:|---|---|---:|---:|---:|
| 1 | 2022-03-21 12:08 | 2022-10-14 23:59 | 1 | 2 | 1 |
| 2 | 2022-10-15 00:01 | NULL | 1 | 2 | 1 |

آیتم‌های مشاهده‌شده پارامتر ۲ (ID / Priority / Ref / Static):

| ID | Priority | Ref | Static |
|---:|---:|---:|---:|
| 13 | 2 | 63 | 1 |
| 14 | 3 | 64 | 1 |
| 15 | 4 | 67 | 1 |
| 16 | 12 | 68 | 1 |
| 17 | 1 | 69 | 1 |
| 18 | 5 | 70 | 0 |
| 19 | 6 | 61 | 0 |
| 20 | 7 | 32 | 0 |
| 21 | 8 | 59 | 0 |
| 22 | 9 | 29 | 0 |
| 23 | 10 | 30 | 0 |
| 24 | 11 | 31 | 0 |

مقدار ۲ در تست استفاده شده است؛ در محیط مقصد باید اعتبار و تناسب آن با تاریخ/نوع اعلامیه کنترل شود.

### ۶.۲. توافق‌های پرداخت نمونه

| PaymentAgreementRef | عنوان مشاهده‌شده |
|---:|---|
| 20 | نقد کارتکش |
| 21 | نقد ۱ الی ۱۵ روزه |
| 23 | چک یک ماهه |
| 24 | چک سه الی چهار ماهه |
| 30 | چک دوماهه |

این شناسه‌ها **نمونه داده** هستند و برای ثبت عمومی نباید ثابت فرض شوند. باید نگاشت `PaymentAgreementID` پروژه Pricing به مرجع معتبر راهکاران در زمان اجرا بررسی شود.

### ۶.۳. محصول، واحد، ارز و کاربر

- `SLS3.Product.Number` کد کالا و `SLS3.Product.ProductID` شناسه ارجاعی `PriceListItem.ProductRef` است.
- `PriceListItem.ProductUnitRef` به `GNR3.Unit.UnitID` اشاره دارد و زوج محصول/واحد باید در `SLS3.ProductUnit` معتبر و فعال باشد.
- `CurrencyRef=1` در نمونه‌های بررسی‌شده استفاده شده؛ تطبیق آن با ارز واقعی و واحد مبلغ الزامی است.
- `Creator=LastModifier=231` در نمونه‌ها دیده شده؛ کاربر `v.baraghani` با UserID=231 در بررسی قبلی فعال گزارش شده و کاربر آن را برای سناریوی نمونه تأیید کرده است. در پیاده‌سازی باید کاربر اجرایی از تنظیمات معتبر خوانده و اعتبارسنجی شود.
- `Fee` از نوع decimal(28,6) است؛ واحد پول و مقیاس مقدار در زمان ارسال کنترل شود.

## ۷. شواهد واقعی درباره State و ساختار پیش‌نویس

در بررسی تکمیلی دیتابیس آزمایشی:

| State | تعداد PriceList | تعداد Item | تعداد ConditionRow |
|---:|---:|---:|---:|
| 1 | 177 | 3,808 | 2 |
| 2 | 46,127 | 1,682,535 | 2,185,882 |

**توضیح حیاتی:** دو ConditionRow متعلق به State=1 فقط مربوط به اعلامیه آزمایشی **48143** بودند که در تست قدیمی‌تر با درج قوانین ساخته شده بود؛ نمونه‌های بومی بررسی‌شده State=1 فاقد ConditionRow بودند.

نمونه‌های پیش‌نویس بومی:

| PriceListID | Number | عنوان | Item | ResultRow | ConditionRow |
|---:|---:|---|---:|---:|---:|
| 48028 | 46548 | گونی نخی درجه یک ایرانی (یاردی)-نقد | 1 | 1 | 0 |
| 48024 | 46544 | گونی نخی درجه یک ایرانی (یاردی)- یک ماهه | 1 | 1 | 0 |
| 47964 | 46484 | پایان دادن | 2 | 1 | 0 |
| 47692 | 46257 | متر میکا | 1 | 4 | 0 |
| 47328 | 45898 | آچار هیوندا-نقد و15روزه | 1 | 2 | 0 |
| 47289 | 45859 | گونیا-نقدو15روزه | 1 | 2 | 0 |
| 46853 | 45436 | تک پلاست-یکماهه | 27 | 0 | 0 |

**برداشت مبتنی بر داده:** `State=1` با الگوی ثبت پیش‌نویس بدون قوانین قیمت سازگار است. معنای رسمی Enum و رفتار UI هنوز جداگانه اثبات نشده است. در سناریوی فعلی نیازی به تأیید یا تغییر State نیست.

## ۸. آزمون عملی ثبت پیش‌نویس — ۱۴۰۵/۰۸/۲۵

### ۸.۱. محیط و ورودی

- Server: `IT-Soleymani`
- Database: `Rahkaran_sg3`، نسخه بازیابی‌شده آزمایشی
- ProductCode: `1011160029`
- ProductID: `31567`
- ProductUnitRef: `12`
- Fee: `420001.000000`
- CurrencyRef: `1`
- PriceListParametersRef: `2`
- Creator/LastModifier: `231`
- PaymentAgreementRef: `20` و `21`
- State: `1`
- حالت اجرا: `@CommitChanges=0` (Rollback)

### ۸.۲. خروجی واقعی گزارش‌شده از اجرای اسکریپت

```text
NewPriceListID        48144
NewPriceListNumber    46660
NewItemID             2294828
CashResultRowID       79361
FifteenDayResultRowID 79362
NewFee                420001.000000
StartDate             2026-11-16 12:51:55.960
DraftState            1
ValidationStatus      READY_TO_ROLLBACK

PriceListID           48144
Number                46660
Title                 جاقفلی-نقد و15روزه-پیش نویس تست
State                 1
PriceListItemID       2294828
ProductRef            31567
ProductUnitRef        12
Fee                   420001.000000

PriceListResultRowID  PaymentAgreementRef
79361                 20
79362                 21

FinalStatus           ROLLED_BACK_SUCCESSFULLY
TestedPriceListID     48144
TestedPriceListNumber 46660
```

**نتیجه اثبات‌شده:** INSERTهای آزمون و اعتبارسنجی‌ها داخل تراکنش موفق بوده‌اند و سپس Rollback انجام شده است. شناسه 48144 و شماره 46660 در این اجرا **به معنی رکورد دائمی نیستند**. مصرف شناسه‌های Sequence می‌تواند پس از Rollback باقی بماند.

### ۸.۳. آزمون فقط‌خواندنی وضعیت شماره‌ها

کوئری زیر با تعریف کامل متغیرها اجرا شده است؛ نسخه ناقص فاقد `DECLARE` باعث خطای SQL Server 137 می‌شود:

```sql
USE Rahkaran_sg3;
GO
SET NOCOUNT ON;
DECLARE @MaxList BIGINT;
DECLARE @MaxReserved BIGINT;
DECLARE @NextSerial BIGINT;

SELECT @MaxList = MAX(TRY_CONVERT(BIGINT, Number))
FROM SLS3.PriceList;

SELECT @MaxReserved = MAX(ReservedSerial)
FROM SYS3.NumberingRangeReservation
WHERE NumberingMethodRangeRef = 77
  AND DomainKey = N'';

SET @NextSerial =
    CASE WHEN ISNULL(@MaxList, 0) > ISNULL(@MaxReserved, 0)
         THEN ISNULL(@MaxList, 0) + 1
         ELSE ISNULL(@MaxReserved, 0) + 1 END;

SELECT @MaxList AS MaxPriceListNumber,
       @MaxReserved AS MaxReservedSerial,
       @NextSerial AS SuggestedNextSerial,
       CASE WHEN @MaxList = @MaxReserved
            THEN N'هماهنگ' ELSE N'نیازمند بررسی اختلاف' END AS NumberingStatus;
```

خروجی گزارش‌شده در ۲۰۲۶/۱۱/۱۶ ساعت ۱۳:۰۸ به وقت +۰۳:۳۰:

| MaxPriceListNumber | MaxReservedSerial | SuggestedNextSerial | NumberingStatus |
|---:|---:|---:|---|
| 46660 | 46660 | 46661 | هماهنگ |

**تفسیر دقیق:** در لحظه بررسی، بزرگ‌ترین شماره عددی PriceList و بزرگ‌ترین رزرو Range=77/DomainKey خالی برابر بودند. `46661` فقط شماره **پیشنهادی تشخیصی** است، نه شماره تضمین‌شده برای INSERT بعدی. این خروجی به‌تنهایی ثابت نمی‌کند کدام اجرا شماره 46660 را دائمی ثبت کرده است؛ برای اثبات، باید رکورد مشخص PriceList و Reservation با SELECT بررسی شود. به‌ویژه این نتیجه را نباید به Rollback آزمایش 48144 نسبت داد.

### ۸.۴. آزمون قبلی که نباید با پیش‌نویس خالص اشتباه شود

در آزمون قبلی، PriceListID=`48143`، Number=`46659` و Fee=`420001` ثبت و COMMIT شده بود، **اما آن اسکریپت برخلاف دامنه فعلی قوانین قیمت را نیز ساخته و قوانین قدیمی را خاتمه داده بود**:

- PriceListItemID=`2294827`.
- PriceListResultRowIDها: `79359` و `79360`.
- PriceListConditionRowIDها: `2315574` و `2315575`.
- BusinessPolicyConditionRowIDها: `2361675` و `2361676`.
- OutputValueID=`70009`.
- قوانین قبلی مرتبط با PriceListID=`46716` خاتمه داده شدند.

این وضعیت در دیتابیس **آزمایشی** ممکن است با چرخه عادی پیش‌نویس ناسازگار باشد. هیچ اصلاح، حذف یا بازگشایی خودکار نباید بدون بررسی وابستگی‌ها و نسخه پشتیبان انجام شود. از این آزمون برای اثبات «ثبت پیش‌نویس بدون تغییر قوانین» استفاده نشود.

## ۹. قالب SQL آزمایشی و کنترل‌ها

این بخش **الگوی ساختار** است، نه اسکریپت مجاز تولید. نمونه کامل قبلی در آزمون اجرا شده و شامل کنترل سرور، تراکنش، قفل و چهار نوع درج بود.

```sql
-- الگوی مفهومی: شناسه‌ها و شماره باید پیش از اجرا
-- از مسیر معتبر و با کنترل‌های مرجع/هم‌زمانی تولید شوند.
BEGIN TRANSACTION;

-- 1) تخصیص/رزرو معتبر شماره تجاری (مسیر رسمی هنوز نامشخص)
-- 2) SYS3.spGetNextId برای سربرگ، کالا و ResultRowها
-- 3) INSERT SLS3.PriceList با State=1
-- 4) INSERT SLS3.PriceListItem برای کالاهای انتخاب‌شده
-- 5) INSERT SLS3.PriceListResultRow برای توافق‌های انتخاب‌شده
-- 6) کنترل تعداد، روابط و نبود ConditionRow
-- 7) COMMIT فقط در محیط مجاز و پس از تأیید

-- هیچ INSERT/UPDATE/DELETE در جداول BusinessPolicy
-- و هیچ UPDATE روی قوانین قیمت قبلی انجام نمی‌شود.
ROLLBACK TRANSACTION; -- صرفاً نمایش الگو
```

کنترل‌های اثبات‌شده در اسکریپت آزمون: `SET XACT_ABORT ON`، `TRY/CATCH`، `BEGIN TRANSACTION`، `sp_getapplock`، `UPDLOCK/HOLDLOCK`، تطبیق سرور/دیتابیس، بررسی کد و ProductID، کنترل تعداد رکوردها، Rollback پیش‌فرض و Rollback خطا.

محدودیت‌ها: `sp_getapplock` اختصاصی با موتور شماره‌گذاری ERP هماهنگی تضمین‌شده ندارد؛ وضعیت Restore با نام سرور به‌تنهایی اثبات نمی‌شود؛ Rollback Sequence را به عقب برنمی‌گرداند؛ کنترل زوج کالا/واحد و idempotency در نمونه فعلی ناقص است.

## ۱۰. یافته‌های تاریخی سیاست قیمت (خارج از دامنه اجرایی فعلی)

این بخش صرفاً برای حفظ تحقیق قبلی است؛ هیچ‌کدام از اشیای زیر نباید در جریان «ثبت پیش‌نویس» تغییر کنند.

- `GNR3.BusinessPolicy` شناسه ۱۸۵: «اعلامیه قیمت فروش 2».
- `GNR3.BusinessPolicyCondition` شناسه ۱۶۳؛ ConditionModel=1، Status=0 در نمونه.
- پارامترهای ورودی سیاست در ۱۲ جایگاه: ارز، محصول، طبقه حساب محصول، واحد، توافق پرداخت، مشتری، SalesArea، SalesType، Division، SalesChannel، SalesOrganization و Quantity.
- `GNR3.BusinessPolicyConditionRow` شامل `BusinessPolicyInputRef` و `BusinessPolicyOutputRef` است.
- `GNR3.BusinessPolicyInputValue` و `GNR3.BusinessPolicyOutputValue` هر دو `Value nvarchar(max)` و `HashedValue varchar(32)` دارند.
- در نمونه‌های بررسی‌شده، مقدار Output با `PriceListItem.Fee` هم‌راستا بوده است.
- در بررسی قبلی ۱۲٬۵۹۷ ردیف فعال Condition=163 و ۵٬۷۵۷ OutputRef متمایز گزارش شده بود؛ این آمار متعلق به همان زمان بررسی است.
- نمونه خروجی قیمت آزمون قدیمی: `<Outputs><Output><ValueWithDescription Type="Decimal" Value="420001.000000" Description="420001/000000" /></Output></Outputs>`.
- در آزمون تکمیلی، مقدار هش خروجی با `MD5` از بایت‌های UTF-16LE متن XML در ۲۰ نمونه تطبیق داده شد:

```sql
-- @XmlValue از نوع NVARCHAR(MAX) و حاوی متن دقیق XML است.
SELECT CONVERT(VARCHAR(32),
       HASHBYTES('MD5', CONVERT(VARBINARY(MAX), @XmlValue)), 2);
```

**حد اعتبار:** این یافته درباره نمونه‌های آزموده‌شده است؛ قواعد کلی نرمال‌سازی XML و هش ورودی‌ها به‌طور کامل اثبات نشده است. برای دامنه فعلی تولید هیچ Hash یا XML لازم نیست.

## ۱۱. معماری پیشنهادی قابلیت «ثبت در راهکاران»

1. نمایش گروه‌های کامل و تأییدشده در Pricing؛ صرفاً `Approved=1` برای تمام ردیف‌های گروه.
2. انتخاب یک یا چند کالا/گروه توسط کاربر و نمایش کد کالا، نام، قیمت، واحد، ارز، توافق پرداخت و تاریخ.
3. اعتبارسنجی سمت سرور: وضعیت تأیید، کامل‌بودن گروه، مجوز کاربر، زوج محصول/واحد، توافق‌ها، ارز، پارامتر PriceList و حساب ثبت‌کننده.
4. تولید پیش‌نمایش بدون DML و دریافت تأیید صریح کاربر.
5. اختصاص **کلید درخواست یکتا** در دیتابیس Pricing و قفل‌گذاری مناسب برای جلوگیری از ارسال مجدد.
6. تعیین شماره تجاری از مسیر معتبر راهکاران؛ **روش فعلی MAX+1 هنوز برای تولید تأیید نشده است**.
7. گرفتن IDها با `SYS3.spGetNextId` و درج `PriceList` با `State=1`، `PriceListItem` و `PriceListResultRow` در تراکنش کنترل‌شده.
8. عدم درج ConditionRow، عدم ساخت BusinessPolicy و عدم تغییر قوانین قبلی.
9. اعتبارسنجی رکوردهای درج‌شده؛ Commit فقط در صورت موفقیت.
10. ثبت audit در Pricing: شناسه درخواست، ID/Number اعلامیه، اقلام، وضعیت، زمان، خطای امن و وضعیت بازیابی.
11. مدیریت نتیجه نامعلوم (timeout/قطع اتصال بعد از Commit): پیش از retry، بررسی نتیجه قبلی با کلید درخواست و شناسه مقصد.
12. تغییر وضعیت «ارسال‌شده به راهکاران» در Pricing **مستقل از** `isexported` خروجی اکسل.

**ملاحظه:** اگر یک درخواست شامل چند کالا با توافق‌های متفاوت باشد، قواعد اینکه یک PriceList یا چند PriceList ساخته شود هنوز باید با نمونه‌های معتبر راهکاران و نیاز تجاری تعیین شود.

## ۱۲. الزامات ایمنی و اعتبارسنجی قبل از تولید

| موضوع | وضعیت فعلی | اقدام لازم |
|---|---|---|
| ساختار سربرگ/آیتم/ResultRow | آزمون تراکنشی موفق | مقایسه با UI روی نسخه مقصد |
| `State=1` و نبود ConditionRow | با نمونه‌های بومی هم‌راستا | تأیید معنای رسمی در UI/Enum |
| `spGetNextId` | آزمون موفق | کنترل نسخه و اثر Sequence |
| شماره‌گذاری تجاری | در تست هماهنگ؛ روش رسمی نامشخص | ردیابی مسیر رسمی و آزمون هم‌زمانی |
| Reservation | درج مستقیم در تست | تعیین چرخه رسمی رزرو/مصرف/آزادسازی |
| زوج محصول/واحد | ناقص | اعتبارسنجی با `SLS3.ProductUnit` |
| پارامترها و توافق‌ها | نمونه‌های ثابت | خواندن و کنترل از دیتابیس مقصد |
| Idempotency | پیاده‌سازی نشده | کلید یکتا و audit در Pricing |
| Retry و timeout | پیاده‌سازی نشده | بازیابی از نتیجه نامعلوم |
| ثبت چندکالا/چندتوافق | فقط نمونه تک‌کالا تست شده | تعیین قاعده گروه‌بندی و آزمون |
| محیط عملیاتی | هیچ تأیید اجرای DML ندارد | تأیید مستقل، پشتیبان و آزمون نهایی |

## ۱۳. آزمون‌های فقط‌خواندنی پیشنهادی

### ۱۳.۱. کنترل ثبت دائمی یک PriceList مشخص

```sql
USE Rahkaran_sg3;
GO
DECLARE @PriceListID BIGINT = 48144; -- صرفاً مثال؛ ID واقعی COMMIT را وارد کنید
SELECT p.PriceListID, p.Number, p.Title, p.State,
       COUNT(DISTINCT i.PriceListItemID) AS ItemCount,
       COUNT(DISTINCT r.PriceListResultRowID) AS ResultRowCount,
       COUNT(DISTINCT c.PriceListConditionRowID) AS ConditionCount
FROM SLS3.PriceList p
LEFT JOIN SLS3.PriceListItem i ON i.PriceListRef = p.PriceListID
LEFT JOIN SLS3.PriceListResultRow r ON r.PriceListRef = p.PriceListID
LEFT JOIN SLS3.PriceListConditionRow c ON c.PriceListRef = p.PriceListID
WHERE p.PriceListID = @PriceListID
GROUP BY p.PriceListID, p.Number, p.Title, p.State;
```

**هشدار:** ID=48144 در خروجی گزارش‌شده Rollback شده بود؛ بنابراین عدم یافتن آن طبیعی است. این کوئری فقط پس از درج ID واقعی اجرای Commit معنی دارد.

### ۱۳.۲. تطبیق شماره 46660 با رکوردهای موجود

```sql
USE Rahkaran_sg3;
GO
SELECT PriceListID, Number, Title, State, CreationDate
FROM SLS3.PriceList
WHERE Number = N'46660';

SELECT NumberingRangeReservationID,
       NumberingMethodRangeRef, DomainKey, ReservedSerial
FROM SYS3.NumberingRangeReservation
WHERE NumberingMethodRangeRef = 77
  AND DomainKey = N''
  AND ReservedSerial = 46660;
```

این دو SELECT برای تعیین وجود **رکورد دائمی** و رزرو متناظر لازم‌اند؛ برابری دو مقدار MAX به‌تنهایی چنین چیزی را اثبات نمی‌کند.

### ۱۳.۳. بررسی پیش‌نویس‌های بومی

```sql
USE Rahkaran_sg3;
GO
SELECT p.State,
       COUNT(DISTINCT p.PriceListID) AS PriceListCount,
       COUNT(DISTINCT i.PriceListItemID) AS ItemCount,
       COUNT(DISTINCT c.PriceListConditionRowID) AS ConditionCount
FROM SLS3.PriceList p
LEFT JOIN SLS3.PriceListItem i ON i.PriceListRef = p.PriceListID
LEFT JOIN SLS3.PriceListConditionRow c ON c.PriceListRef = p.PriceListID
WHERE p.State IN (1,2)
GROUP BY p.State;
```

## ۱۴. موارد باز و تصمیم‌های ثبت‌شده

### قطعی برای دامنه فعلی

- [x] هدف فقط **ثبت پیش‌نویس** است؛ تأیید راهکاران حذف شده است.
- [x] ساختار پایه `PriceList` + `PriceListItem` + `PriceListResultRow` مشخص است.
- [x] در نمونه‌های بومی State=1، ایجاد ConditionRow مشاهده نشده است.
- [x] اسکریپت آزمایشی با یک کالا و دو توافق پرداخت تا مرحله اعتبارسنجی موفق و سپس Rollback شده است.
- [x] `SYS3.spGetNextId` برای شناسه‌های داخلی در آزمون استفاده شده است.
- [x] در بررسی ۱۳:۰۸، `MaxPriceListNumber=MaxReservedSerial=46660` بوده است.
- [x] آزمون قدیمی 48143 که قوانین قیمت را تغییر داد، از آزمون پیش‌نویس خالص تفکیک شده است.

### هنوز باز

- [ ] شناسایی مسیر رسمی تولید و رزرو شماره تجاری و آزمون رقابت هم‌زمان با UI.
- [ ] تعیین قواعد DomainKey، شماره‌گذاری قالب‌دار و رفتار خطا/timeout.
- [ ] بررسی وجود واقعی PriceList شماره 46660 و منشأ ثبت دائمی آن.
- [ ] تأیید UI/Enum برای State=1 در نسخه مقصد.
- [ ] تکمیل اعتبارسنجی زوج محصول/واحد، ارز، توافق‌ها، پارامتر و کاربر.
- [ ] تعیین سیاست گروه‌بندی چند کالا و چند توافق پرداخت.
- [ ] طراحی idempotency، audit، retry امن و بازیابی نتیجه نامعلوم در Pricing.
- [ ] آزمون Commit پیش‌نویس خالص و بررسی آن در UI متصل به دیتابیس آزمایشی، در صورت امکان.
- [ ] بازبینی تغییرات آزمون قدیمی 48143 پیش از هر آزمون وابسته به قوانین قیمت.
- [ ] اخذ مجوز و طراحی کنترل‌های تولید قبل از هر INSERT در دیتابیس عملیاتی.

## ۱۵. تاریخچه اصلاحات و تفاوت با نسخه قبلی

| موضوع | اصلاح انجام‌شده |
|---|---|
| دامنه پروژه | حذف تأیید و ساخت قوانین از عملیات موردنیاز |
| وضعیت State=1 | افزودن شواهد ۱۷۷ پیش‌نویس و تفکیک دو ConditionRow مصنوعی |
| نتیجه آزمون | ثبت خروجی واقعی 48144 و `ROLLED_BACK_SUCCESSFULLY` |
| شماره‌گذاری | ثبت نتیجه 46660/46660/46661 و تفکیک مشاهده از تضمین عملیاتی |
| خطای Msg 137 | افزودن `DECLARE`های کامل در کوئری تشخیصی |
| آزمون قدیمی 48143 | ثبت صریح تغییر قوانین قبلی و ممنوعیت تعمیم به پیش‌نویس خالص |
| الگوریتم هش | افزودن نتیجه آزمون MD5 خروجی؛ خارج از دامنه فعلی |
| جریان Pricing | جداکردن تأیید داخلی Pricing از تأیید ERP و جداکردن ارسال ERP از خروجی اکسل |
| ایمنی | حفظ هشدار شماره‌گذاری رسمی، idempotency، هم‌زمانی و اعتبار مرجع |

---

**مرز نهایی:** این سند شرح تحقیق و آزمون کنترل‌شده است. موفقیت INSERT در دیتابیس بازیابی‌شده و هماهنگی دو MAX، به‌تنهایی صحت شماره‌گذاری رسمی، سازگاری کامل UI یا مجوز اجرای ثبت روی دیتابیس عملیاتی را ثابت نمی‌کند.
