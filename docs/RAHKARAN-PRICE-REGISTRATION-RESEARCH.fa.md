# مستند بررسی اتصال ثبت قیمت در راهکاران

> وضعیت سند: پژوهش فنی و فقط‌خواندنی  
> پروژه مصرف‌کننده: `cognitive-kernel/Pricing`  
> هدف: طراحی قابلیت «ثبت در راهکاران» برای انتقال قیمت‌های تأییدشده به دیتابیس SQL Server راهکاران  
> آخرین به‌روزرسانی: ۱۴۰۵/۰۸/۲۳  
>
> **محدودیت مهم:** این سند حاصل بررسی ساختار و نمونه‌داده‌های مشاهده‌شده است؛ هنوز مجوزی برای اجرای `INSERT`، `UPDATE`، `DELETE` یا رزرو شماره در دیتابیس عملیاتی صادر نشده است. هیچ مقدار نمونه‌ای نباید بدون تأیید و اعتبارسنجی در محیط مقصد به‌عنوان مقدار قطعی استفاده شود.

## ۱. هدف و دامنه

در پروژه Pricing قرار است گزینه‌ای با عنوان «ثبت در راهکاران» اضافه شود. این گزینه باید صفحه‌ای برای مشاهده محصولات تأییدشده باز کند، امکان انتخاب یک یا چند محصول را بدهد و پس از تکمیل بررسی‌ها، اطلاعات قیمت را با تراکنش کنترل‌شده در ساختار راهکاران ثبت کند.

شرایط قطعی طراحی:

- فقط محصولات تأییدشده و آماده ارسال باید در فهرست قرار بگیرند.
- وضعیت تأیید، کامل‌بودن گروه و جلوگیری از ثبت مجدد باید در سمت سرور کنترل شود.
- داده‌های راهکاران و شناسه‌های وابسته باید از دیتابیس و منطق رسمی خود راهکاران استخراج شوند؛ شناسه ثابت یا نمونه ساختگی ممنوع است.
- برای تولید شناسه از روش رسمی راهکاران استفاده شود؛ استفاده از `MAX()+1` ممنوع است.
- عملیات ثبت نهایی باید تراکنشی، قابل ممیزی و دارای کنترل هم‌زمانی و idempotency باشد.
- توسعه کد و انتشار قابلیت از اجرای ثبت واقعی در دیتابیس عملیاتی جدا است.
- پیش از هر ثبت واقعی، پارامترها، شناسه‌ها، روابط و مقادیر نهایی باید به کاربر گزارش و تأیید شوند.

## ۲. وضعیت فعلی پروژه Pricing

بر اساس بررسی قبلی کد پروژه:

- اتصال راهکاران در `app/Services/RahkaranPriceService.php` با PDO_SQLSRV انجام می‌شود.
- تنظیمات اتصال از پیکربندی محیطی دریافت می‌شوند؛ از جمله `RAHKARAN_SERVER`، `RAHKARAN_DATABASE`، `RAHKARAN_UID`، `RAHKARAN_PWD`، `RAHKARAN_CHARSET` و `RAHKARAN_TRUST_SERVER_CERTIFICATE`.
- سرویس موجود عملیات خواندن اطلاعات محصول، واحد، توافق پرداخت و آخرین قیمت‌ها را پوشش می‌دهد؛ در بررسی انجام‌شده متد ثبت Price List وجود نداشت.
- `app/Controllers/PriceApprovalController.php` صف تأیید را مدیریت می‌کند: مقدار `Approved=0` قابل انتخاب است، تأیید مقدار ۱ و رد مقدار ۲ را ثبت می‌کند.
- منطق خروجی اکسل فقط گروه‌هایی را صادر می‌کند که تمام ردیف‌های گروه تأیید شده باشند (`Approved=1`) و هنوز صادر نشده باشند (`isexported=0`).
- گروه‌بندی قیمت‌ها در `MarketPriceCalculator::groupByPaymentAgreement()` بر اساس `PaymentAgreementID` است.
- `dbo.PriceApprove` برای ثبت خروجی اکسل استفاده می‌شود و پس از خروجی، `ProductPriceMonitor.isexported` به‌روزرسانی می‌شود.
- مسیرها در `routes/web.php`، منوها در `views/layout.php` و مجوزها در `database/seed.php` و `PermissionService` تعریف می‌شوند.

این مسیر اکسل را نباید با ثبت در راهکاران یکی دانست. ثبت در راهکاران باید جریان کاری و وضعیت مستقل و قابل ردیابی داشته باشد تا ارسال موفق به ERP با خروجی اکسل اشتباه نشود.

## ۳. جداول اصلی شناسایی‌شده در راهکاران

### ۳.۱. `SLS3.PriceList`

ستون‌های مشاهده‌شده:

| ستون | نوع / وضعیت |
|---|---|
| `PriceListID` | bigint، کلید اصلی، بدون Identity |
| `PriceListParametersRef` | bigint، اجباری |
| `Number` | nvarchar(100)، اجباری |
| `Title` | nvarchar(512)، اجباری |
| `StartDate` | datetime، اجباری |
| `EndDate` | datetime، nullable |
| `PriceListType` | int، اجباری |
| `SalesDocumentType` | int، اجباری |
| `CurrencyRef` | bigint، اجباری |
| `State` | int، اجباری |
| `CreationDate` | datetime، اجباری |
| `LastModificationDate` | datetime، اجباری |
| `Creator` | bigint، اجباری |
| `LastModifier` | bigint، اجباری |
| `Version` | timestamp/rowversion؛ نباید در INSERT مقداردهی شود |
| `FeeMapperPolicyRef` | bigint، nullable |
| `FeePolicyRef` | bigint، nullable |
| `ConfirmationDate` | datetime، nullable |

### ۳.۲. `SLS3.PriceListItem`

| ستون | نوع / وضعیت |
|---|---|
| `PriceListItemID` | bigint، کلید اصلی، بدون Identity |
| `PriceListRef` | bigint، اجباری |
| `RowNumber` | int، اجباری |
| `ProductRef` | bigint، nullable |
| `ProductGroupRef` | bigint، nullable |
| `ProductUnitRef` | bigint، اجباری |
| `PackageRef` | bigint، nullable |
| `Quantity` | decimal(28,6)، nullable |
| `CurrencyRef` | bigint، اجباری |
| `Fee` | decimal(28,6)، اجباری |
| `Definitiveness` | int، اجباری |
| `ReductionTolerance` | decimal(28,6)، nullable |
| `AdditionTolerance` | decimal(28,6)، nullable |
| `TrackingFactor1..5` | nvarchar(512)، nullable |
| `PartTrackingFactorRef1..5` | bigint، nullable |
| `TrackingFactorInputMode1..5` | int، nullable |
| `TrackingFactorValue` | nvarchar(512)، nullable |
| `BatchRef` | bigint، nullable |
| `Version` | timestamp/rowversion؛ نباید در INSERT مقداردهی شود |

### ۳.۳. `SLS3.PriceListResultRow`

- `PriceListResultRowID`: bigint، کلید اصلی.
- `PriceListRef`: bigint، اجباری.
- سایر ستون‌های مشاهده‌شده عمدتاً nullable هستند.
- `PaymentAgreementRef` یکی از ستون‌های مهم این جدول است.
- `Version` از نوع rowversion است و نباید مقداردهی شود.

### ۳.۴. `SLS3.PriceListConditionRow`

- `PriceListConditionRowID`: bigint، کلید اصلی.
- `BusinessPolicyRef`: bigint، اجباری.
- `BusinessPolicyConditionRef`: bigint، اجباری.
- `BusinessPolicyConditionRowRef`: bigint، اجباری.
- `PriceListRef`: bigint، اجباری.
- `PriceListItemRef`: bigint، اجباری.
- `PriceListResultRowRef`: bigint، nullable.
- `EndDate`: datetime، nullable.
- `Version`: rowversion.

**نکته:** فهرست کامل ستون‌ها و تمام constraintهای این جدول‌ها باید پیش از پیاده‌سازی نهایی دوباره از همان نسخه دیتابیس مقصد استخراج شود. این سند جایگزین بررسی schema واقعی محیط مقصد نیست.

## ۴. روابط کلید خارجی مشاهده‌شده

- `PriceList.Creator` و `PriceList.LastModifier` به `SYS3.User.UserID` اشاره می‌کنند.
- `PriceList.CurrencyRef` به `GNR3.Currency` اشاره می‌کند.
- `PriceList.FeeMapperPolicyRef` و `FeePolicyRef` به `GNR3.BusinessPolicy` اشاره می‌کنند.
- `PriceList.PriceListParametersRef` به `SLS3.PriceListParameters` اشاره می‌کند.
- `PriceListItem.PriceListRef` به `PriceList`، `ProductRef` به محصول، `ProductUnitRef` به `GNR3.Unit`، `ProductGroupRef` به `GNR3.EntityGroup` و `CurrencyRef` به ارز اشاره می‌کند.
- `PriceListResultRow.PriceListRef` به `PriceList` و `PaymentAgreementRef` به توافق پرداخت اشاره می‌کند.
- `PriceListConditionRow` به BusinessPolicy، BusinessPolicyCondition، PriceList، PriceListItem و در صورت وجود به PriceListResultRow مرتبط است.

رابطه و قید مرجع دقیق `BusinessPolicyConditionRowRef` هنوز نیازمند بررسی مستقل است؛ از روی نام ستون نباید مقصد FK را حدس زد.

## ۵. تولید شناسه رکوردها

چهار جدول اصلی دارای شناسه‌هایی هستند که در بررسی انجام‌شده Identity نبودند. Sequenceهای رسمی مرتبط شناسایی شده‌اند:

- `dbo.SYS3_TableIdGen__SLS3.PriceList`
- `dbo.SYS3_TableIdGen__SLS3.PriceListItem`
- `dbo.SYS3_TableIdGen__SLS3.PriceListResultRow`
- `dbo.SYS3_TableIdGen__SLS3.PriceListConditionRow`

روش رسمی مشاهده‌شده:

```sql
SYS3.spGetNextId
    @TableName,
    @Id OUTPUT,
    @IncValue = 1,
    @IsLegacy = 0
```

یادداشت‌ها:

- این Stored Procedure شناسه را با منطق داخلی راهکاران تولید می‌کند و اطلاعات `ReplicationSiteId` را در شناسه لحاظ می‌کند.
- مقدار `@IncValue=0` در بررسی قبلی برای مشاهده مقدار فعلی بدون رزرو شناسه استفاده شده است.
- برای هر جدول باید نام جدول متناظر به روش رسمی ارائه شود.
- فراخوانی واقعی برای رزرو شناسه هنوز انجام نشده و نباید در مرحله پژوهش اجرا شود.
- استفاده از `MAX()+1`، تولید دستی شناسه یا فراخوانی مستقیم Sequence به‌جای روش رسمی ممنوع است.

## ۶. پارامترهای Price List

جدول `SLS3.PriceListParameters` بررسی شده است. دو رکورد مشاهده‌شده:

| PriceListParametersID | StartDate | EndDate | PriceListType | SalesDocumentType | Model |
|---:|---|---|---:|---:|---:|
| 1 | 2022-03-21 12:08 | 2022-10-14 23:59 | 1 | 2 | 1 |
| 2 | 2022-10-15 00:01 | NULL | 1 | 2 | 1 |

برای پارامتر ۲، آیتم‌های زیر مشاهده شده‌اند:

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

این مقادیر صرفاً داده‌های مشاهده‌شده‌اند. انتخاب پارامتر فعال و معنای دقیق تمام آیتم‌ها باید با نسخه و تنظیمات دیتابیس مقصد تطبیق داده شود.

## ۷. نمونه داده‌های واقعی مشاهده‌شده

در بازه PriceListIDهای 48076 تا 48095، نمونه‌های زیر تکرار شده‌اند:

- `PriceListParametersRef=2`
- `PriceListType=1`
- `SalesDocumentType=2`
- `CurrencyRef=1`
- `State=2`
- `Creator=LastModifier=231`
- `FeeMapperPolicyRef` و `FeePolicyRef` در نمونه‌های مشاهده‌شده NULL بوده‌اند.
- `ConfirmationDate` در نمونه‌های مشاهده‌شده NULL بوده است.
- Numberهای متناظر 46596 تا 46615 بوده‌اند؛ بنابراین Number با PriceListID یکسان نیست.
- Title معمولاً نام محصول یا نام محصول همراه با توافق پرداخت بوده است.
- StartDate زمان ایجاد رکورد بوده و EndDate معمولاً NULL بوده است.
- رکورد 48095 نمونه‌ای با EndDate ثبت‌شده و مفهوم پایان‌دادن به اعتبار قیمت در زمان 2026-11-14 15:53 بوده است.

در PriceListItem نمونه‌ها، ProductRef از شناسه محصول در `SLS3.Product.ProductID`، واحد از واحد محصول، CurrencyRef برابر ۱ در نمونه‌های بررسی‌شده، Fee برابر قیمت و Definitiveness برابر ۱ مشاهده شده است. Quantity در نمونه‌های بررسی‌شده NULL بوده و RowNumber از ۱ شروع شده است.

این مقادیر «پیش‌فرض قطعی» نیستند و نباید بدون تطبیق با محصول، توافق پرداخت و سیاست جاری استفاده شوند.

## ۸. توافق‌های پرداخت مشاهده‌شده

نمونه PaymentAgreementRefها:

| PaymentAgreementRef | عنوان مشاهده‌شده |
|---:|---|
| 20 | نقد کارتکش |
| 21 | نقد 1 الی 15 روزه |
| 23 | چک یک ماهه |
| 24 | چک سه الی چهار ماهه |
| 30 | چک دوماهه |

برای هر محصول ممکن است چند توافق پرداخت و در نتیجه چند ردیف نتیجه/شرط قیمت وجود داشته باشد. منطق گروه‌بندی پروژه Pricing نیز بر اساس `PaymentAgreementID` است. نگاشت نهایی باید از جدول مرجع فعال و داده جاری خوانده شود، نه از این جدول نمونه.

## ۹. سیاست قیمت و ردیف‌های شرط

موارد زیر در بررسی قبلی مشاهده شده‌اند:

- `BusinessPolicyRef=185` با عنوان «اعلامیه قیمت فروش 2».
- نوع سیاست Fee و TargetBaseConcept برابر SalesDocument و TargetConcept برابر SalesDocumentItem گزارش شده است.
- `BusinessPolicyConditionRef=163` با ConditionModel=1 و Status=0 مشاهده شده است.
- ردیف‌های قدیمی Condition 163 با شناسه‌های 1290060 تا 1290079 دارای EndDate برابر 2022-11-30 بوده‌اند؛ این ردیف‌ها منقضی‌اند و نباید برای ثبت جدید استفاده شوند.
- ردیف‌های جدید Condition 163 در بازه 2361475 تا 2361574 مشاهده شده‌اند؛ بیشتر آن‌ها EndDate=NULL بوده‌اند. دو ردیف 2361573 و 2361574 دارای EndDate برابر 2026-11-14 15:53 گزارش شده‌اند.
- هر ConditionRow به Input و Output اختصاصی متصل است.
- در ۲۰ ردیف نمونه بررسی‌شده، مقدار خروجی سیاست با `PriceListItem.Fee` برابر بوده است.

### ۹.۱. پارامترهای ورودی سیاست ۱۸۵

۱۲ پارامتر در بررسی قبلی گزارش شده‌اند:

1. ارز / CurrencyRef؛ نمونه مقدار ۱
2. محصول / ProductRef
3. طبقه حساب محصول / ProductRef؛ نمونه NULL
4. واحد سنجش محصول / ProductUnitRef
5. روش تسویه حساب / PaymentAgreementRef
6. مشتری / CustomerRef؛ نمونه NULL
7. SalesAreaRef؛ نمونه NULL
8. SalesTypeRef؛ نمونه NULL
9. DivisionRef؛ نمونه NULL
10. SalesChannelRef؛ نمونه NULL
11. SalesOrganizationRef؛ نمونه NULL
12. Quantity؛ نمونه NULL

ساختار XML ورودی مشاهده‌شده:

```xml
<Inputs>
  <Input>
    <ValueWithDescription Type="Int64" Value="..." Description="" />
  </Input>
</Inputs>
```

برای مقادیر NULL نیز Type مربوطه به‌صورت null گزارش شده است. ترتیب، نوع و معنای پارامترها باید از رکوردهای فعال و مرتبط با همان محصول/توافق پرداخت استخراج شود؛ ساخت XML با مقادیر ثابت مجاز نیست.

## ۱۰. کاربر ثبت‌کننده

کاربر با شناسه ۲۳۱ در نمونه‌های آخر PriceList به‌عنوان Creator و LastModifier دیده شده است. اطلاعات غیرحساس مشاهده‌شده:

- UserID: `231`
- نام کاربری: `v.baraghani`
- Status: فعال
- PartyRef: `14499`
- Type: `1`
- IsAdministrator: `0`
- IsLocked: `0`

کاربر تأیید کرده است که ثبت Price List در راهکاران با همین حساب انجام می‌شود؛ بنابراین شناسه ۲۳۱ برای Creator و LastModifier در نمونه‌های بررسی‌شده تأیید شده است. با این حال، پیاده‌سازی باید کاربر/حساب اجرایی پیکربندی‌شده را از منبع معتبر بخواند و در زمان ثبت اعتبار آن را بررسی کند. اطلاعات احراز هویت یا هش گذرواژه نباید در این مستند یا logها درج شود.

## ۱۱. شماره‌گذاری PriceList

### ۱۱.۱. روش شماره‌گذاری مرتبط

در `SYS3.NumberingMethod` و `SYS3.NumberingMethodEntityAttribute` سه روش با نام/مؤلفه مرتبط مشاهده شده‌اند:

| NumberingMethodID | عنوان | AttributeComponentName | AttributeEntityName |
|---:|---|---|---|
| 20 | فهرست بها | SystemGroup.Logistics.VoucherPricing | PriceList |
| 77 | اعلامیه قیمت | SystemGroup.Sales.PriceList | PriceList |
| 307 | اعلامیه قیمت سه گانه | SystemGroup.PDS.PharmaDistribution | TriplePriceList |

برای قابلیت مورد بررسی در پروژه Pricing، روش ۷۷ با مؤلفه `SystemGroup.Sales.PriceList` مرتبط‌ترین گزینه مشاهده‌شده است. روش ۲۰ متعلق به مؤلفه دیگری است و روش ۳۰۷ برای TriplePriceList است.

تنظیمات مشاهده‌شده روش ۷۷:

| فیلد | مقدار |
|---|---|
| NumberingMethodID | 77 |
| Title | اعلامیه قیمت |
| EffectiveDate | 2022-01-10 15:03:10.097 |
| NumberType | 1 |
| NumberDigitsType | 2 |
| NumberOfDigits | 0 |
| NumberTemplate | `*سریال` |
| AssignApproch | 1 |
| NumberingMethodState | 1 |
| FixedLength | NULL |
| CheckUniqueness | 1 |
| MissedNumberBehavior | 0 |
| AttributeComponentName | `SystemGroup.Sales.PriceList` |
| AttributeEntityName | `PriceList` |
| IsForSecondNumber | 0 |

### ۱۱.۲. بازه شماره‌گذاری روش ۷۷

از `SYS3.NumberingMethodRange` رکورد زیر مشاهده شده است:

| NumberingMethodRangeID | NumberingMethodRef | Priority | AttributeName | AttributeValues | StartSerial | FinishSerial | SerialStep |
|---:|---:|---:|---|---|---:|---|---:|
| 77 | 77 | 1 | NULL | NULL | 1 | NULL | 1 |

برای این بازه، `SYS3.NumberingRangeReservation` شماره‌های رزروشده‌ای را نشان داده که در خروجی مشاهده‌شده از 46506 تا 46615 ادامه دارند. در این خروجی DomainKey خالی بوده است. برخی شماره‌ها در این توالی دیده نشده‌اند؛ بنابراین نباید فرض کرد شماره‌ها بدون شکاف یا دقیقاً متوالی‌اند.

آخرین شماره‌ای که در خروجی ارائه‌شده مشاهده شد 46615 است. این صرفاً آخرین شماره موجود در همان نتیجه است و اثبات نمی‌کند شماره بعدی 46616 خواهد بود. تخصیص شماره باید فقط با سازوکار رسمی خود راهکاران انجام شود.

### ۱۱.۳. Stored Procedureهای شناسایی‌شده

جست‌وجوی متون ماژول‌های SQL، پنج Stored Procedure زیر را پیدا کرده است:

| Schema | نام Procedure | نوع |
|---|---|---|
| SYS3 | `CreateNumberingMethod` | SQL_STORED_PROCEDURE |
| SYS3 | `CreateNumberingMethodReservation` | SQL_STORED_PROCEDURE |
| SYS3 | `spAddNumberingMethodReservation` | SQL_STORED_PROCEDURE |
| SYS3 | `spRemoveNumberingMethodReservation` | SQL_STORED_PROCEDURE |
| SYS3 | `spRemoveOldNumberingReservations` | SQL_STORED_PROCEDURE |

وجود این Procedureها شناسایی شده، اما متن، پارامترها، ترتیب فراخوانی و رفتار تراکنشی آن‌ها هنوز بررسی نشده است. **هیچ‌کدام را تا زمان بررسی کامل تعریف و فراخوانی رسمی آن‌ها اجرا نکنید.** مرحله بعد پژوهش، استخراج متن کامل Procedureها و بررسی وابستگی‌هایشان به جداول Numbering است.



### ۱۱.۴. بررسی متن و پارامترهای Procedureها — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** اجرای دو SELECT فقط‌خواندنی روی `sys.procedures`، `sys.parameters` و `OBJECT_DEFINITION`. هیچ Procedureای اجرا نشده است.

**پارامترهای مشاهده‌شده:**

| Procedure | پارامترها |
|---|---|
| `CreateNumberingMethod` | `@title nvarchar(128)`، `@componentName nvarchar(500)`، `@entityName nvarchar(200)`، `@numberType int`، `@assignApproach int`، `@checkUniqueness bit`، `@NumberingRangeID bigint OUTPUT`، `@MissedNumberBehavior int`، `@newNumberingId bigint`، `@newAttributeID bigint`، `@newRangeID bigint`، سه پارامتر resetter از نوع `nvarchar(50)` |
| `CreateNumberingMethodReservation` | `@NumberingMethodRangeID bigint`، `@DomainKey nvarchar(500)`، `@ReservedValue bigint` |
| `spAddNumberingMethodReservation` | `@NumberingMethodRangeID bigint`، `@DomainKey nvarchar(500)`، `@ReservedValue bigint` |
| `spRemoveNumberingMethodReservation` | `@NumberingMethodRangeID bigint`، `@DomainKey nvarchar(500)`، `@ReservedValue bigint` |
| `spRemoveOldNumberingReservations` | پارامتری ندارد |

توجه: `sys.parameters.max_length` برای nvarchar بر حسب بایت است؛ برای نمونه 256 بایت معادل nvarchar(128) است. تعریف متنی Procedureها نیز طول کاراکتری را نشان می‌دهد.

**متن و رفتار مستقیم قابل مشاهده:**

1. **`SYS3.CreateNumberingMethod`**
   - ابتدا بر اساس `AttributeComponentName` و `AttributeEntityName` وجود روش شماره‌گذاری را بررسی می‌کند و در صورت یافتن، Range موجود را در پارامتر خروجی برمی‌گرداند.
   - اگر Range پیدا نشود، تراکنش آغاز می‌کند؛ شناسه‌های Method، Attribute و Range را در صورت ندادن ورودی با `SYS3.spGetNextID` می‌گیرد و در سه جدول `NumberingMethod`، `NumberingMethodEntityAttribute` و `NumberingMethodRange` درج می‌کند.
   - در صورت ارسال resetterها، برای هرکدام شناسه گرفته و در `NumberingReseterAttribute` درج می‌کند؛ سپس Commit می‌کند.
   - این Procedure سازنده/پیکربندی‌کننده روش شماره‌گذاری است، نه Procedure تخصیص شماره برای PriceList. اجرای آن در جریان ثبت قیمت ممنوع است.
   - **نکته نیازمند بررسی:** در متن ارائه‌شده برای این Procedure، مدیریت خطا و TRY/CATCH پیرامون تراکنش دیده نمی‌شود؛ رفتار آن در خطا و نیز ایمنی اجرای هم‌زمان باید جداگانه بررسی شود. همچنین بررسی اولیه وجود روش خارج از تراکنش است.

2. **`SYS3.CreateNumberingMethodReservation`**
   - اگر برای Range موردنظر هیچ رکوردی در `NumberingRangeReservation` وجود نداشته باشد، یک رزرو با DomainKey و ReservedValue درج می‌کند.
   - شرط `NOT EXISTS` فقط وجود هر رزروی برای همان Range را می‌سنجد و ترکیب سه‌تایی Range/DomainKey/ReservedValue را بررسی نمی‌کند.
   - در صورت وجود حتی یک رزرو برای Range، این Procedure رزرو جدیدی ایجاد نمی‌کند؛ بنابراین برای تخصیص عادی شماره بعدی مناسب فرض نمی‌شود.
   - در متن مشاهده‌شده، تراکنش صریح یا مدیریت خطا وجود ندارد.

3. **`SYS3.spAddNumberingMethodReservation`**
   - رزرو را فقط در صورت نبود ترکیب دقیق `NumberingMethodRangeRef + DomainKey + ReservedSerial` درج می‌کند.
   - خطای 2601 را فقط وقتی نادیده می‌گیرد که متن خطا نام Unique Index مشخص‌شده `UIX_SYS3_NumberingRangeReservation_NumberingMethodRangeRef_DomainKey_ReservedSerial` را داشته باشد؛ خطاهای دیگر را دوباره پرتاب می‌کند.
   - در Catch، اگر `@@TRANCOUNT > 0` باشد، `ROLLBACK` اجرا می‌کند. این رفتار ممکن است تراکنش فراخواننده را نیز Rollback کند؛ اثر آن باید در طراحی مرز تراکنش و فراخوانی بررسی شود.
   - این Procedure به‌تنهایی الگوریتم انتخاب ReservedValue یا شماره بعدی را نشان نمی‌دهد.

4. **`SYS3.spRemoveOldNumberingReservations`**
   - با `ROW_NUMBER()` رزروهای مثبت را به تفکیک Range و DomainKey بر اساس ReservedSerial نزولی مرتب می‌کند.
   - تمام رزروهای بعد از ۵۰ رکورد جدیدتر هر گروه را از `NumberingRangeReservation` حذف می‌کند.
   - این Procedure عملیات DELETE و پاک‌سازی نگهداری است؛ برای پژوهش یا ثبت قیمت نباید اجرا شود.

5. **`SYS3.spRemoveNumberingMethodReservation`**
   - پارامترهای آن از `sys.parameters` استخراج شده‌اند، اما متن تعریف آن در خروجی ارسالی موجود نیست؛ کوئری بخش تعریف‌ها به‌جای نام واقعی، نام `spRemoveNumberingReservation` را جست‌وجو کرده بود.
   - بنابراین رفتار حذف یک رزرو مشخص هنوز **مشاهده نشده و نیازمند استخراج متن دقیق** است.

**تفکیک سطح اطمینان:**
- مشاهده مستقیم: امضاها و رفتارهای بالا مطابق متن Procedureهایی است که در خروجی SQL ارائه شده‌اند.
- استنباط: `spAddNumberingMethodReservation` از نظر جلوگیری از درج تکراریِ همان ترکیب، از `CreateNumberingMethodReservation` دقیق‌تر است؛ اما هنوز اثبات نشده که این Procedure مسیر رسمی و کامل تخصیص شماره PriceList باشد.
- نیازمند تأیید: الگوریتم تعیین شماره بعدی، نحوه ساخت DomainKey، اتصال Reservation به INSERT در PriceList، مدیریت rollback و قفل‌گذاری در کد/Procedure فراخواننده، و رفتار Procedure حذف رزرو.

**اثر بر طراحی:** هیچ‌یک از این تعریف‌ها به‌تنهایی مسیر کامل و امن تولید Number برای PriceList را ثابت نمی‌کند. تا زمان ردیابی فراخواننده‌ها و منطق رسمی برنامه راهکاران، نباید مستقیماً Reservation درج/حذف شود یا شماره بعدی محاسبه شود. `CreateNumberingMethod` و `spRemoveOldNumberingReservations` به‌وضوح خارج از مسیر ثبت عادی هستند.



**تکمیل تعریف Procedure حذف رزرو — ۱۴۰۵/۰۸/۲۳**

تعریف کامل `SYS3.spRemoveNumberingMethodReservation` با جست‌وجوی نام صحیح استخراج شد:

- پارامترها: `@NumberingMethodRangeID bigint`، `@DomainKey nvarchar(500)` و `@ReservedValue bigint`.
- رفتار مستقیم: فقط رکورد(های) منطبق با هر سه شرط `NumberingMethodRangeRef`، `DomainKey` و `ReservedSerial` را از `SYS3.NumberingRangeReservation` حذف می‌کند.
- در متن مشاهده‌شده، تراکنش صریح، TRY/CATCH یا کنترل تعداد ردیف حذف‌شده وجود ندارد.
- مشاهده مستقیم: این Procedure حذف هدفمند یک رزرو مشخص را انجام می‌دهد؛ با پاک‌سازی قدیمی `spRemoveOldNumberingReservations` متفاوت است.
- نیازمند تأیید: اینکه چه بخش‌هایی از نرم‌افزار راهکاران آن را فراخوانی می‌کنند، چه زمانی حذف رزرو مجاز است و آیا حذف در همان تراکنش ایجاد PriceList انجام می‌شود یا خیر.
- اثر طراحی: این Procedure نیز نباید مستقیماً از پروژه Pricing فراخوانی شود تا زمانی که چرخه رسمی رزرو/مصرف/آزادسازی شماره و قواعد بازیابی از شکست از کد یا مسیر رسمی راهکاران اثبات شود.



### ۱۱.۵. جست‌وجوی ارجاع از سایر ماژول‌های SQL — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی متنی در `sys.sql_modules` برای پنج نام Procedure شماره‌گذاری، با حذف خود همان پنج Procedure از نتایج. کوئری فقط‌خواندنی بود.

**خروجی مستقیم:** نتیجه ارسالی کاربر فاقد هرگونه ردیف بود؛ یعنی در متن ماژول‌های قابل مشاهده در `sys.sql_modules` هیچ ارجاع متنی دیگری به نام این پنج Procedure پیدا نشد.

**محدودیت تفسیر:**
- این نتیجه اثبات نمی‌کند که Procedureها در زمان اجرا فراخوانی نمی‌شوند.
- فراخوانی ممکن است از کد برنامه/کلاینت، ماژول رمزگذاری‌شده، SQL پویا با نام ساخته‌شده، SQL Agent Job، اسمبلی CLR یا مؤلفه‌ای خارج از `sys.sql_modules` انجام شود.
- جست‌وجوی متنی ممکن است به دلیل تفاوت نام، schema qualification، یا محدودیت دسترسی Metadata برخی ارجاع‌ها را نبیند.
- خروجی خالی همچنین نشان نمی‌دهد کدام Procedure مسیر رسمی تولید شماره PriceList است.

**وضعیت:** مشاهده مستقیمِ «عدم یافتن ارجاع متنی در ماژول‌های قابل مشاهده»؛ علت و مسیر واقعی فراخوانی همچنان نیازمند تأیید است.

**اثر بر طراحی:** از روی این پنج تعریف و جست‌وجوی وابستگی متنی هنوز نمی‌توان الگوریتم تولید شماره یا چرخه رزرو/مصرف/آزادسازی را بازسازی کرد. فراخوانی مستقیم این Procedureها از پروژه Pricing همچنان تأیید نشده و نباید پیاده‌سازی شود.



### ۱۱.۶. جست‌وجوی SQL Agent Jobها — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی فقط‌خواندنی در `msdb.dbo.sysjobs` و `msdb.dbo.sysjobsteps` برای فرمان‌هایی که شامل `Numbering`، `Reservation` یا `PriceList` باشند.

**خروجی مستقیم:** نتیجه ارسالی کاربر فاقد هرگونه ردیف بود؛ بنابراین در Job Stepهای قابل مشاهده با این عبارات، تطبیقی پیدا نشد.

**محدودیت تفسیر:**
- این نتیجه نبود هرگونه Job مرتبط را اثبات نمی‌کند؛ نام/فرمان ممکن است از عبارات دیگری استفاده کند یا فراخوانی در Stored Procedure، فایل، PowerShell، SSIS، CmdExec یا سامانه بیرونی پنهان باشد.
- دسترسی محدود به متادیتای msdb نیز می‌تواند نتایج قابل مشاهده را محدود کند.
- این بررسی مسیر واقعی تولید یا رزرو شماره PriceList را مشخص نکرد.

**وضعیت:** در جست‌وجوی متنی محدودشده به سه عبارت فوق، Job Step منطبقی مشاهده نشد. هیچ Job یا Procedure اجرا یا تغییری داده نشد.

**اثر بر طراحی:** همچنان شواهد کافی برای پیاده‌سازی تولید شماره یا فراخوانی مستقیم Procedureهای رزرو در Pricing وجود ندارد.


### ۱۱.۷. جست‌وجوی Triggerهای مرتبط — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی فقط‌خواندنی در `sys.triggers` و `sys.sql_modules` برای Triggerهای متصل به جداول PriceList و شماره‌گذاری، یا تعریف‌هایی که شامل `NumberingMethod` یا `PriceList` باشند.

**خروجی مستقیم:** نتیجه ارسالی کاربر فاقد هرگونه ردیف بود؛ بنابراین در محدوده جست‌وجو هیچ Trigger منطبقی مشاهده نشد.

**محدودیت تفسیر:**
- نتیجه خالی، نبود Trigger در کل پایگاه داده را ثابت نمی‌کند؛ ممکن است نام جدول‌ها متفاوت باشد یا Triggerهای موردنظر در محدوده متادیتای قابل مشاهده نباشند.
- این کوئری فقط Triggerهای قابل مشاهده و تعریف‌های متنی آن‌ها را پوشش می‌دهد؛ منطق برنامه، ماژول‌های رمزگذاری‌شده و رویدادهای بیرون از SQL را پوشش نمی‌دهد.
- مسیر رسمی تولید شماره و چرخه رزرو/مصرف/آزادسازی همچنان مشخص نشده است.

**وضعیت:** در محدوده جست‌وجوی تعریف‌شده، Trigger منطبقی پیدا نشد. هیچ Trigger اجرا یا تغییری داده نشد.


### ۱۱.۸. کلید خارجی جدول رزرو شماره — ۱۴۰۵/۰۸/۲۳

**خروجی مستقیم بررسی:** تنها رابطه برگشتی/ورودی پیدا‌شده در محدوده کوئری:

- Constraint: `FK_sys3_NumberingRangeReservation_NumberingMethodRangeRef`
- جدول فرزند: `SYS3.NumberingRangeReservation`
- ستون فرزند: `NumberingMethodRangeRef`
- جدول مرجع: `SYS3.NumberingMethodRange`
- ستون مرجع: `NumberingMethodRangeID`

**نتیجه:** رزرو شماره به یک Range مشخص از روش شماره‌گذاری متصل است. در خروجی این بررسی FK دیگری به/از جدول `NumberingRangeReservation` مشاهده نشد.

**محدودیت:** نبود FKهای دیگر در خروجی به معنی نبود قیود یکتا، ایندکس‌ها یا قواعد اعتبارسنجی در کد Procedureها نیست. همچنین این FK به‌تنهایی نحوه انتخاب Serial، DomainKey یا چرخه مصرف رزرو را تعیین نمی‌کند.

**وضعیت:** رابطه FK فوق مستند شد؛ هیچ داده یا ساختار دیتابیس تغییر نکرد.
## ۱۲. Triggerها، Indexها و یکتایی

در بررسی قبلی روی چهار جدول اصلی PriceList، PriceListItem، PriceListResultRow و PriceListConditionRow، Trigger مشاهده نشده است. برای هر جدول، PK یکتا و clustered گزارش شده و Unique Index دیگری برای Number یا ترکیب محصول/توافق پرداخت مشاهده نشده است.

نتیجه طراحی:

- اتکا به یکتایی دیتابیس برای جلوگیری از ثبت تکراری کافی نیست.
- بررسی تکراری‌بودن باید در منطق برنامه و داخل مرز تراکنش انجام شود.
- کنترل هم‌زمانی لازم است تا دو درخواست موازی نتوانند یک درخواست تجاری را دوبار ثبت کنند.
- یک کلید idempotency پایدار برای هر عملیات انتقال باید در دیتابیس خود Pricing نگهداری شود.
- قبل از انتخاب قفل یا constraint جدید باید اثر آن بر دیتابیس راهکاران و استانداردهای محصول بررسی شود.

## ۱۳. طرح پیشنهادی جریان ثبت (هنوز پیاده‌سازی نشده)

۱. دریافت محصولات آماده ثبت از صف تأیید پروژه Pricing، با کنترل کامل‌بودن گروه و وضعیت تأیید همه ردیف‌ها.

۲. نمایش محصول، قیمت، واحد، ارز، توافق پرداخت، تاریخ اعتبار و اطلاعات لازم برای بازبینی به کاربر.

۳. دریافت داده‌های مرجع از راهکاران در لحظه یا از cache معتبر با کنترل تازگی: ProductID، ProductUnit، Currency، PaymentAgreement، PriceListParameters، BusinessPolicy و ConditionRow فعال.

۴. تولید پیش‌نمایش عملیات شامل تمام مقادیر و شناسه‌های مقصد، بدون هیچ تغییر در دیتابیس.

۵. ارائه پیش‌نمایش به کاربر و دریافت تأیید صریح.

۶. اجرای ثبت در یک تراکنش SQL Server، با استفاده از سازوکار رسمی تولید ID و شماره‌گذاری راهکاران.

۷. ثبت PriceList، PriceListItem، PriceListResultRow و PriceListConditionRow مطابق الگوی معتبر و کامل راهکاران.

۸. اعتبارسنجی تعداد و ارتباط رکوردهای ایجادشده، قیمت‌ها، توافق پرداخت‌ها و شماره تخصیص‌یافته.

۹. Commit فقط پس از موفقیت تمام کنترل‌ها؛ در غیر این صورت Rollback کامل.

۱۰. ثبت نتیجه در جدول مستقل audit/integration در Pricing، شامل شناسه درخواست، شناسه‌های مقصد، وضعیت، زمان و خطای پاک‌سازی‌شده از اطلاعات حساس.

۱۱. علامت‌گذاری منبع به‌عنوان ارسال‌شده فقط پس از تأیید موفقیت تراکنش مقصد و ثبت نتیجه قابل اتکا؛ شکست یا timeout باید قابل بازیابی و بررسی باشد.

این طرح تا زمان تکمیل بررسی Procedureها، قیود، نمونه‌های معتبر و تأیید پارامترها، مجوز پیاده‌سازی INSERT عملیاتی نیست.

## ۱۴. موارد باز و اقدامات بعدی پژوهش

- [x] استخراج پارامترهای پنج Procedure و متن چهار تعریف؛ تعریف `spRemoveNumberingMethodReservation` به علت اشتباه در نام جست‌وجو در خروجی نبود و باید جداگانه استخراج شود.
- [x] استخراج تعریف کامل `SYS3.spRemoveNumberingMethodReservation`؛ رفتار حذف هدفمند رزرو ثبت شد.\n- [x] جست‌وجوی ارجاع متنی پنج Procedure در سایر ماژول‌های SQL؛ هیچ ردیفی در `sys.sql_modules` برنگشت. این نتیجه مسیر فراخوانی از برنامه، Job یا ماژول‌های غیرقابل مشاهده را رد نمی‌کند.\n- [x] جست‌وجوی فرمان Job Stepها در `msdb` برای عبارات `Numbering`، `Reservation` و `PriceList`؛ هیچ ردیفی برنگشت. این نتیجه نبود Job مرتبط با نام‌ها/فرمان‌های دیگر یا فراخوانی غیرمستقیم را ثابت نمی‌کند.\n- [x] جست‌وجوی Triggerهای متصل به جداول PriceList/شماره‌گذاری و تعریف‌های حاوی NumberingMethod یا PriceList؛ هیچ ردیفی برنگشت. این نتیجه محدود به متادیتای قابل مشاهده و معیارهای جست‌وجو است.\n- [ ] بررسی ماژول‌های رمزگذاری‌شده و کد/لاگ برنامه راهکاران برای یافتن فراخوانی واقعی و مسیر رسمی تخصیص شماره.
- [ ] بررسی رفتار رزرو شماره در حالت موفق، خطا، Rollback، timeout و درخواست‌های هم‌زمان؛ بدون اجرای عملیات مخرب روی محیط عملیاتی.
- [ ] شناسایی اینکه آیا `CreateNumberingMethodReservation` و `spAddNumberingMethodReservation` بخشی از یک زنجیره‌اند یا کاربردهای جدا دارند.
- [ ] بررسی دقیق DomainKey و چرایی خالی بودن آن در رزروهای نمونه.
- [ ] تعیین نحوه تبدیل `*سریال` به مقدار Number و قواعد قالب‌بندی.
- [ ] استخراج کامل FKها، CHECK constraintها، DEFAULTها، computed columnها و تمام ستون‌های اجباری چهار جدول.
- [ ] بررسی همه جدول‌ها/روابط جانبی که نرم‌افزار راهکاران هنگام ذخیره PriceList ایجاد یا به‌روزرسانی می‌کند.
- [ ] بررسی وضعیت‌های مجاز PriceList و معنای State=2 در نسخه جاری.
- [ ] تأیید قطعی پارامترهای فعال PriceListParameters و آیتم‌های آن.
- [ ] تعیین دقیق BusinessPolicyConditionRow فعال برای هر ترکیب محصول/واحد/توافق پرداخت.
- [ ] تعیین رفتار ثبت چند محصول و چند توافق پرداخت: یک PriceList برای هر محصول، یا گروه‌بندی دیگری که با رفتار نرم‌افزار مقصد منطبق باشد.
- [ ] تعیین NumberingMethod مناسب برای تمام حالت‌های ثبت مورد نیاز.
- [ ] طراحی idempotency، کنترل هم‌زمانی، retry امن و بازیابی از نتیجه نامعلوم.
- [ ] آماده‌سازی آزمون یکپارچه در محیط غیرعملیاتی با داده کنترل‌شده و مقایسه رکوردهای ایجادشده با رکوردهای ساخته‌شده توسط UI رسمی راهکاران.
- [ ] ارائه خلاصه شناسه‌ها و پارامترهای نهایی به کاربر برای تأیید پیش از هر ثبت واقعی.

## ۱۵. قواعد نگهداری و تکمیل این سند

هر یافته جدید باید در همین سند و در بخش مرتبط ثبت شود؛ از ایجاد مستندات پراکنده و متناقض خودداری شود.

برای هر یافته جدید این اطلاعات درج شود:

- تاریخ بررسی و محیط/نسخه دیتابیس، در صورت معلوم بودن.
- کوئری یا روش read-only مورد استفاده.
- نام جدول، ستون، Procedure یا شیء مرتبط.
- خروجی مشاهده‌شده یا خلاصه دقیق آن.
- تفکیک روشن بین «مشاهده مستقیم»، «استنباط» و «نیازمند تأیید».
- اثر یافته بر طراحی ثبت قیمت.
- مرحله بعدی لازم برای راستی‌آزمایی.

هر مقدار نمونه باید با برچسب «نمونه مشاهده‌شده» مشخص شود. مقادیر تأییدنشده نباید به‌عنوان ثابت برنامه‌نویسی وارد کد شوند. هر عملیات تغییردهنده دیتابیس باید قبل از اجرا جداگانه و صریحاً تأیید شود.

## ۱۶. تاریخچه تغییرات

| تاریخ | تغییر |
|---|---|
| ۱۴۰۵/۰۸/۲۳ | ایجاد مستند اولیه از یافته‌های ساختار PriceList، روابط، شناسه‌ها، سیاست قیمت، کاربر ثبت‌کننده، روش شماره‌گذاری ۷۷، بازه و رزروهای مشاهده‌شده و فهرست Procedureهای شماره‌گذاری. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت پارامترها و تحلیل متن چهار Procedure شماره‌گذاری؛ مشخص‌کردن تفاوت دو روش ایجاد Reservation، رفتار rollback در `spAddNumberingMethodReservation`، حذف رزروهای قدیمی و باقی‌ماندن تعریف Procedure حذف رزرو برای استخراج. |\n| ۱۴۰۵/۰۸/۲۳ | تکمیل تعریف `spRemoveNumberingMethodReservation`؛ ثبت رفتار حذف بر اساس Range، DomainKey و ReservedValue و به‌روزرسانی فهرست اقدامات پژوهش. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت نتیجه جست‌وجوی ارجاع متنی پنج Procedure در `sys.sql_modules`؛ هیچ ارجاع دیگری یافت نشد و محدودیت‌های این نتیجه مستند شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت نتیجه جست‌وجوی Job Stepهای SQL Agent برای عبارات Numbering، Reservation و PriceList؛ هیچ تطبیقی یافت نشد و محدودیت‌های نتیجه مستند شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت نتیجه جست‌وجوی Triggerهای مرتبط با PriceList و شماره‌گذاری؛ هیچ ردیفی برنگشت و محدودیت‌های نتیجه مستند شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت FK جدول `SYS3.NumberingRangeReservation` به `SYS3.NumberingMethodRange` از طریق `NumberingMethodRangeRef`؛ محدودیت استنباط از این رابطه نیز ثبت شد. |
