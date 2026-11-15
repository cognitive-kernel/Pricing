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
- مقدار `@IncValue=0` فقط در صورت وجود قبلی Sequence، مقدار فعلی را بدون پیشروی می‌خواند؛ اگر Sequence وجود نداشته باشد، خود Procedure آن را ایجاد کرده و یک مقدار اولیه مصرف می‌کند. بنابراین این فراخوانی بدون بررسی وجود Sequence، read-only تضمین‌شده نیست (بخش ۱۱.۲۴).
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


### ۱۱.۹. ایندکس‌ها و یکتایی جدول رزرو شماره — ۱۴۰۵/۰۸/۲۳

**خروجی مستقیم بررسی ایندکس‌های `SYS3.NumberingRangeReservation`:**

1. کلید اصلی خوشه‌ای:
   - نام: `PK_SYS3_NumberingRangeReservation`
   - ستون کلید: `NumberingRangeReservationID`
   - `IsUnique=1` و `IsPrimaryKey=1`

2. ایندکس یکتای غی خوشه‌ای:
   - نام: `UIX_SYS3_NumberingRangeReservation_NumberingMethodRangeRef_DomainKey_ReservedSerial`
   - ترتیب کلیدها:
     1. `NumberingMethodRangeRef`
     2. `DomainKey`
     3. `ReservedSerial`
   - `IsUnique=1`، بدون فیلتر و بدون ستون Included در خروجی.

**نتیجه فنی:** دیتابیس یکتایی ترکیب سه‌تایی Range + DomainKey + ReservedSerial را enforce می‌کند. این با شرط بررسی تکراری در `spAddNumberingMethodReservation` و مدیریت خطای 2601 هم‌راستا است. این قید فقط از تکرار همان ترکیب جلوگیری می‌کند؛ به‌تنهایی انتخاب Serial آزاد، ترتیب تخصیص، یا مصرف/آزادسازی رزرو را تعیین نمی‌کند.

**محدودیت:** کوئری ستون‌های کلیدی و Included را نشان داد؛ در خروجی حاضر ستون Included وجود نداشت. این بررسی صرفاً ساختار ایندکس را خواند و هیچ داده‌ای تغییر نکرد.


### ۱۱.۱۰. ساختار ستون‌های سه جدول شماره‌گذاری — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** استخراج ستون‌ها، نوع داده، Nullable و Identity از کاتالوگ سیستمی برای `SYS3.NumberingMethod`، `SYS3.NumberingMethodRange` و `SYS3.NumberingRangeReservation`.

**یافته‌های مستقیم:**

- `SYS3.NumberingMethod`:
  - شناسه `NumberingMethodID bigint` اجباری و غیر Identity است.
  - تنظیمات اصلی شامل `EffectiveDate`، `NumberType`، `NumberDigitsType`، `NumberOfDigits`، `NumberTemplate`، `AssignApproch`، `NumberingMethodState`، `FixedLength`، `CheckUniqueness` و `MissedNumberBehavior` است.
  - `Version` از نوع `timestamp/rowversion` است و نباید در INSERT مقداردهی شود.

- `SYS3.NumberingMethodRange`:
  - `NumberingMethodRangeID bigint` و `NumberingMethodRef bigint` اجباری و غیر Identity هستند.
  - `Priority`، `StartSerial` و `SerialStep` اجباری‌اند؛ `FinishSerial`، `AttributeName` و `AttributeValues` nullable هستند.
  - `Version` از نوع `timestamp/rowversion` است.

- `SYS3.NumberingRangeReservation`:
  - `NumberingRangeReservationID bigint` دارای `Identity=1` است؛ برخلاف شناسه‌های Method و Range که در خروجی Identity نبودند.
  - `NumberingMethodRangeRef bigint`، `DomainKey nvarchar(1000)` و `ReservedSerial bigint` همگی اجباری و غیر Identity هستند.
  - جدول در خروجی ستون Version ندارد.

**نتیجه طراحی:** برای جدول Reservation، مقدار `NumberingRangeReservationID` باید توسط Identity دیتابیس تولید شود و نباید توسط برنامه مقداردهی شود. در مقابل، این مشاهده به‌تنهایی روش تولید ID برای Method و Range را تغییر نمی‌دهد؛ برای آن‌ها شواهد قبلی درباره `SYS3.spGetNextId` معتبر باقی می‌ماند. ستون‌های `DomainKey` و `ReservedSerial` باید دقیقاً با دامنه و الگوریتم رسمی راهکاران مقداردهی شوند؛ مقدار فرضی یا DomainKey خالی نباید از روی نمونه‌ها تعمیم داده شود.

**محدودیت:** این بررسی فقط metadata ستون‌ها را نشان می‌دهد؛ مقدارهای مجاز، قواعد DomainKey، انتخاب Serial بعدی و رفتار هم‌زمانی از این خروجی قابل استنتاج نیست.


### ۱۱.۱۱. خلاصه الگوی داده‌های رزرو واقعی — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** تجمیع فقط‌خواندنی جدول `SYS3.NumberingRangeReservation` بر اساس `NumberingMethodRangeRef` و `DomainKey` و محاسبه تعداد، کمینه/بیشینه `ReservedSerial` و بیشینه شناسه رزرو.

**یافته‌های مستقیم از خروجی ارسالی:**

- Range شماره `77` دارای `DomainKey` خالی است و ۱۰۷ رزرو دارد؛ کمینه Serial برابر ۴۶۵۰۶، بیشینه ۴۶۶۱۵ و بیشینه `NumberingRangeReservationID` برابر ۱۸۵۹۰۲۸ است.
- چون ایندکس یکتای سه‌ستونی قبلاً تأیید شده، در این گروه هر Serial حداکثر یک‌بار در همان Range و DomainKey ثبت می‌شود. بازه ۴۶۵۰۶ تا ۴۶۶۱۵ شامل ۱۱۰ عدد است، در حالی که ۱۰۷ رزرو گزارش شده؛ بنابراین سه مقدار در این بازه در حال حاضر در جدول رزرو وجود ندارند. این مشاهده به‌تنهایی ثابت نمی‌کند آن شماره‌ها قابل تخصیص‌اند یا قبلاً مصرف نشده‌اند.
- Range شماره `33` تعداد زیادی DomainKey متفاوت دارد؛ برای بسیاری از گروه‌ها تعداد رزروها نزدیک یا برابر ۵۰ است و برای برخی گروه‌ها تعدادهای دیگری دیده می‌شود. این الگو با رزروهای دسته‌ای/چنددامنه‌ای سازگار است، اما معنای DomainKey و علت اندازه دسته از این تجمیع قابل اثبات نیست.
- سایر Rangeها نیز DomainKey خالی یا غیرخالی و تعداد/بازه‌های متفاوت دارند؛ بنابراین DomainKey بخشی از کلید یکتایی و دامنه جداسازی رزروهاست و نباید برای همه Rangeها یک مقدار ثابت فرض شود.
- `MAX(NumberingRangeReservationID)` فقط بیشترین شناسه ردیف رزرو در هر گروه است و ترتیب زمانی یا آخرین شماره مصرف‌شده را اثبات نمی‌کند.

**نتیجه مهم برای Range 77:** نمونه‌های واقعی رزرو با `NumberingMethodRangeID=77` و DomainKey خالی با شماره‌های ۴۶۵۰۶ تا ۴۶۶۱۵ مشاهده شده‌اند؛ این با نمونه‌های PriceList دارای Numberهای ۴۶۵۹۶ تا ۴۶۶۱۵ هم‌پوشانی دارد. این هم‌پوشانی قرینه‌ای قوی از ارتباط Range 77 با شماره‌گذاری PriceList است، اما برای اثبات چرخه کامل رزرو تا درج PriceList باید ترتیب عملیات و کد فراخواننده بررسی شود.

**محدودیت و ایمنی:** این خروجی تجمیعی شامل تاریخچه تغییرات، وضعیت مصرف/آزادسازی یا ارتباط هر رزرو با PriceList نیست. از روی MAX، MIN یا شکاف‌ها نباید شماره بعدی را محاسبه یا رزرو کرد. هیچ رزروی ایجاد/حذف و هیچ Procedure اجرا نشده است.

### ۱۱.۱۲. محدودسازی جست‌وجوی ارجاع مستقیم به جدول رزرو — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی فقط‌خواندنی در `sys.sql_modules` با دو عبارت `NumberingRangeReservation` و `NumberingMethodRangeRef`؛ این بار فقط نام شیء، نوع و طول تعریف نمایش داده شد تا خروجی حجیم متن Procedureها تکرار نشود.

**خروجی مستقیم:**

| Schema | Object | نوع | طول تعریف |
|---|---|---|---:|
| SYS3 | CreateNumberingMethodReservation | SQL_STORED_PROCEDURE | 508 |
| SYS3 | spAddNumberingMethodReservation | SQL_STORED_PROCEDURE | 852 |
| SYS3 | spRemoveNumberingMethodReservation | SQL_STORED_PROCEDURE | 373 |
| SYS3 | spRemoveOldNumberingReservations | SQL_STORED_PROCEDURE | 491 |

**نتیجه:** در ماژول‌های SQL قابل مشاهده و مطابق این دو معیار جست‌وجو، فقط همین چهار Procedure مستقیماً به جدول/ستون رزرو اشاره دارند. این نتیجه با بررسی قبلی تعریف همین چهار Procedure سازگار است و شیء SQL دیگری با ارجاع مستقیم در این جست‌وجو پیدا نشد.

**محدودیت:** این نتیجه فراخوانی غیرمستقیم، SQL پویا با نام/عبارت ساخته‌شده در زمان اجرا، کد برنامه راهکاران، ماژول رمزگذاری‌شده یا مؤلفه خارجی را رد نمی‌کند. همچنین به‌تنهایی الگوریتم انتخاب شماره، تولید مقدار `Number` یا چرخه موفقیت/شکست رزرو را مشخص نمی‌کند.

**اثر بر طراحی:** شواهد فعلی برای نوشتن INSERT عملیاتی PriceList و رزرو شماره کافی نیست. باید مسیر فراخوانی در کد برنامه/لایه سرویس راهکاران یا مستندات رسمی آن پیدا شود. هیچ Procedure اجرا و هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۳. تطبیق رزروهای Range 77 با شماره PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** اتصال فقط‌خواندنی `SYS3.NumberingRangeReservation` به `SLS3.PriceList` با شرط `TRY_CONVERT(BIGINT, PriceList.Number) = ReservedSerial`، محدود به `NumberingMethodRangeRef=77` و `DomainKey=N''`.

**خروجی مستقیم کوئری:**

| شاخص | مقدار |
|---|---:|
| TotalReservations | 107 |
| MatchedPriceLists | 107 |
| ReservationsWithoutPriceList | 0 |
| DistinctMatchedPriceLists | 107 |

**نتیجه قطعی در محدوده داده‌های بررسی‌شده:** هر ۱۰۷ رزرو موجود برای Range 77 و DomainKey خالی، دقیقاً به یک رکورد متمایز `SLS3.PriceList` از طریق برابری `ReservedSerial` و مقدار عددی `PriceList.Number` متصل شده است. در این مجموعه هیچ رزروی بدون PriceList و هیچ تطبیق چندگانه‌ای گزارش نشده است. این شواهد ارتباط عملیاتی شماره رزرو با فیلد `PriceList.Number` را به‌طور قوی و مستقیم تأیید می‌کند.

**محدودیت:** این تطبیق فقط وضعیت فعلی ۱۰۷ رزرو موجود را نشان می‌دهد. از آن نمی‌توان الگوریتم انتخاب شماره بعدی، ترتیب فراخوانی رزرو و INSERT، رفتار در تراکنش ناموفق/rollback، یا نحوه بازیابی پس از timeout را استنتاج کرد. همچنین درباره رزروهای حذف‌شده یا سوابقی که دیگر در جدول نیستند اطلاعاتی نمی‌دهد.

**اثر بر طراحی:** در مسیر ثبت PriceList باید مقدار نهایی `Number` با Serial رزروشده توسط سازوکار رسمی راهکاران منطبق باشد؛ تولید دستی Number یا استنتاج شماره بعدی از MAX/MIN همچنان ممنوع است. هیچ Procedure اجرا و هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۴. بررسی نمونه‌های اخیر ترتیب رزرو و ایجاد PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** نمایش ۳۰ رزرو با بزرگ‌ترین `ReservedSerial` در Range 77 و DomainKey خالی، همراه با شناسه PriceList، مقدار Number، CreationDate، Creator و State؛ اتصال از طریق برابری Serial و Number و مرتب‌سازی نزولی بر اساس Serial.

**یافته‌های مستقیم از خروجی ارسالی:**

- هر ۳۰ ردیف نمایش‌داده‌شده دارای تطبیق `ReservedSerial = PriceList.Number` هستند.
- شماره‌ها از 46586 تا 46615 به ترتیب نزولی نمایش داده شده‌اند و زمان‌های `CreationDate` نیز در همین خروجی به‌صورت نزولی متناظرند؛ یعنی در این نمونه، شماره بزرگ‌تر با زمان ایجاد جدیدتر همراه است.
- شناسه‌های `PriceListID` از 48066 تا 48095 نیز در این نمونه با افزایش شماره افزایش یافته‌اند.
- تمام ۳۰ ردیف `Creator=231` و `State=2` دارند.
- شناسه‌های `NumberingRangeReservationID` دارای فاصله هستند و نباید آن‌ها را شمارنده پیوسته یا مبنای محاسبه Serial دانست.

**نتیجه:** نمونه اخیر با این الگو سازگار است که رزرو شماره قبل یا در جریان ایجاد PriceList انجام می‌شود و Serial رزروشده در فیلد Number همان PriceList ثبت می‌شود. همراه با تطبیق کامل ۱۰۷ رکورد در بخش ۱۱.۱۳، شواهد ارتباط رزرو و PriceList قوی‌تر شده است.

**محدودیت:** این نمونه صرفاً ترتیب مقادیر در داده نهایی را نشان می‌دهد، نه ترتیب دقیق دستورات در زمان اجرا. از آن نمی‌توان ثابت کرد رزرو حتماً پیش از INSERT رخ داده، هر دو در یک تراکنش‌اند، یا در شکست عملیات رزرو آزاد/حفظ می‌شود. همچنین ثابت‌بودن Creator و State در این ۳۰ نمونه، قاعده عمومی تمام سناریوها را اثبات نمی‌کند.

**اثر بر طراحی:** هنگام پیاده‌سازی باید مسیر واقعی اجرای UI/سرویس راهکاران و رفتار تراکنش و خطا مشخص شود؛ ترتیب مشاهده‌شده به‌تنهایی برای بازسازی الگوریتم تخصیص شماره کافی نیست. هیچ Procedure اجرا و هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۵. سنجش ترتیب Serial، زمان ایجاد و شناسه‌ها در تمام ۱۰۷ رکورد — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** استفاده از CTE و توابع پنجره‌ای `LAG` روی تمام رزروهای Range 77 با DomainKey خالی که به PriceList متناظر متصل شده‌اند؛ مرتب‌سازی بر اساس `ReservedSerial` و شمارش موارد نقض ترتیب در CreationDate، PriceListID و NumberingRangeReservationID.

**خروجی مستقیم:**

| شاخص | مقدار |
|---|---:|
| TotalRows | 107 |
| DateOrderViolations | 0 |
| PriceListIDOrderViolations | 0 |
| ReservationIDOrderViolations | 0 |

**نتیجه:** در هر ۱۰۷ ردیف، با افزایش `ReservedSerial` هیچ کاهش/وارونگی در `CreationDate`، `PriceListID` یا `NumberingRangeReservationID` مشاهده نشده است. بنابراین در مجموعه بررسی‌شده، هر سه مقدار در کنار Serial به‌صورت یکنواخت و هم‌جهت مرتب می‌شوند. این یافته، همراه با تطبیق یک‌به‌یک بخش ۱۱.۱۳، شاهد قوی‌تری برای ارتباط چرخه رزرو شماره با ایجاد PriceList فراهم می‌کند.

**محدودیت:** این آزمون فقط ترتیب نهایی داده‌های باقی‌مانده را می‌سنجد؛ هم‌زمانی دقیق دستورات، تقدم قطعی INSERT رزرو بر INSERT PriceList، اتمیک‌بودن دو عملیات، و رفتار خطا، Rollback یا timeout را اثبات نمی‌کند. همچنین درباره رزروهای حذف‌شده یا رکوردهای خارج از مجموعه تطبیق‌یافته نتیجه‌ای نمی‌دهد.

**اثر بر طراحی:** همچنان باید مسیر فراخوانی و مدیریت تراکنش در برنامه رسمی راهکاران مشخص شود. از این الگوی مرتب، الگوریتم تولید Serial بعدی یا مجوز ساخت رزرو دستی قابل استخراج نیست. هیچ Procedure اجرا و هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۶. فهرست جدول‌های مرتبط با شماره‌گذاری و تاریخچه — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی نام جدول‌ها در `sys.tables` با الگوهای `%Numbering%`، `%Reservation%`، `%Audit%` و `%History%`.

**یافته‌های مرتبط مستقیم یا بالقوه از خروجی:**

- `SYS3.NumberingRangeReservation`: جدول رزرو مورد بررسی؛ طبق بخش ۱۱.۱۰ فقط چهار ستون دارد و ستون زمانی ندارد.
- `SLS3.SalesNumberReservation`: جدول رزرو شماره در حوزه فروش؛ ارتباط احتمالی آن با شماره‌گذاری PriceList هنوز اثبات نشده است.
- `ECM.NumberingReservation`: جدول رزرو در زیرسامانه ECM؛ ارتباط آن با روش شماره‌گذاری فروش مشخص نیست.
- `SYS3.EntityHistoryRecord` و `SYS3.StateHistory`: جدول‌های تاریخچه عمومی سیستم؛ هنوز مشخص نیست رخدادهای مربوط به رزرو یا PriceList را ثبت می‌کنند یا خیر.

**محدودیت:** تشابه نام جدول به‌تنهایی اثبات‌کننده ارتباط عملیاتی نیست. صرفاً نام جدول‌ها بررسی شده و هنوز ساختار، FKها، داده‌ها یا سیاست ثبت تاریخچه این جدول‌های بالقوه بررسی نشده‌اند.

**مرحله بعد:** ابتدا ستون‌ها و کلیدهای `SLS3.SalesNumberReservation` و `ECM.NumberingReservation` را فقط‌خواندنی استخراج می‌کنیم تا مشخص شود کدام‌یک از نظر ساختار به شماره‌گذاری فروش/PriceList نزدیک‌تر است. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۷. ساختار دو جدول رزرو بالقوه — ۱۴۰۵/۰۸/۲۳

**۱) `ECM.NumberingReservation`**

این جدول دارای ستون‌های `NumberingMethodRef`، `Count`، `DateType`، `FromDate`، `ToDate`، `Type`، `AssignType`، `State`، `Creator`، `CreationDate` و `LastModificationDate` است. بنابراین زمان ایجاد رکورد در این جدول ثبت می‌شود؛ اما ساختار آن بیشتر نشان‌دهنده یک رزرو/تعریف بازه‌ای وابسته به `NumberingMethod` است و هنوز ارتباط آن با `SYS3.NumberingMethodRange` یا Range=77 اثبات نشده است.

**۲) `SLS3.SalesNumberReservation`**

این جدول شامل `SourceType`، `SourceRef`، `TargetType`، `TargetNumber` و ستون زمانی nullable به نام `Date` است. این ساختار می‌تواند برای ردیابی ارتباط شماره فروش با موجودیت مبدأ/مقصد مفید باشد؛ اما هنوز معلوم نیست رکوردهای آن برای PriceList یا NumberingMethodID=77 استفاده می‌شوند.

**نتیجه:** هر دو جدول ستون‌های بالقوه مفیدی دارند، ولی هیچ‌کدام را نمی‌توان بدون بررسی داده و روابط به‌عنوان تاریخچه قطعی رزروهای `SYS3.NumberingRangeReservation` معرفی کرد. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۸. روابط کلید خارجی جدول‌های رزرو — ۱۴۰۵/۰۸/۲۳

**خروجی بررسی FKها:**

- `ECM.NumberingReservation.NumberingMethodRef` به `ECM.NumberingMethod.NumberingMethodID` متصل است.
- `ECM.NumberingReservation.Creator` و `LastModifier` به `SYS3.User.UserID` متصل‌اند.
- جدول `ECM.AssignedSerial` از طریق ستون `NumberingReservationRef` به `ECM.NumberingReservation.NumberingReservationID` متصل است.
- برای `SLS3.SalesNumberReservation` در این بررسی FK مرتبطی گزارش نشد.

**نتیجه:** وجود `ECM.AssignedSerial` مسیر داده‌ای مهمی برای بررسی شماره‌های تخصیص‌یافته تحت یک رزرو ECM فراهم می‌کند. بااین‌حال، هنوز هیچ FK مستقیمی میان این زنجیره و `SYS3.NumberingMethodRange` یا `SYS3.NumberingRangeReservation` مشاهده نشده است؛ ارتباط احتمالی با PriceList باید از داده‌ها و تعریف روش‌ها بررسی شود.

**محدودیت:** نبود FK در خروجی به‌تنهایی نبود ارتباط منطقی یا ارتباط از طریق کد برنامه را ثابت نمی‌کند. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۱۹. ساختار جدول ECM.AssignedSerial — ۱۴۰۵/۰۸/۲۳

جدول `ECM.AssignedSerial` دارای ستون‌های کلیدی زیر است:

- `NumberingMethodRef`: روش شماره‌گذاری
- `SerialPoolRef`: مخزن سریال
- `EntityCode` و `EntityRef`: نوع و شناسه موجودیت مقصد؛ `EntityRef` nullable است
- `Value`: مقدار سریال تخصیص‌یافته
- `Status` و `CreationType`: وضعیت و نوع ایجاد
- `Creator`، `CreationDate`، `LastModifier` و `LastModificationDate`
- `NumberingReservationRef`: ارجاع nullable به `ECM.NumberingReservation`

**نتیجه:** این جدول برخلاف `SYS3.NumberingRangeReservation` زمان ایجاد، شماره تخصیص‌یافته و ارجاع اختیاری به رزرو را در یک رکورد نگه می‌دارد. این ساختار برای بررسی مسیر شماره‌گذاری ECM مفید است؛ اما هنوز ثابت نشده که شماره‌های PriceList در روش ۷۷ از این جدول عبور می‌کنند یا خیر.

**محدودیت:** وجود `NumberingMethodRef` در این جدول به‌تنهایی به معنی یکسان بودن شناسه روش ECM با `SYS3.NumberingMethod` یا ارتباط آن با Range=77 نیست. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۲۰. عدم تطبیق شماره‌های PriceList با دو مسیر رزرو جایگزین — ۱۴۰۵/۰۸/۲۳

دو آزمون read-only روی شماره‌های PriceList در بازه 46506 تا 46615 نتیجه‌ای برنگرداندند:

1. اتصال `ECM.AssignedSerial.Value` به `SLS3.PriceList.Number` هیچ رکورد منطبقی پیدا نکرد؛ همچنین برای `ECM.AssignedSerial.NumberingMethodRef=77` رکوردی مشاهده نشد.
2. جست‌وجوی `SLS3.SalesNumberReservation.TargetNumber` در همان بازه نیز هیچ ردیفی برنگرداند.

**نتیجه محدود:** این دو جدول، با معیارهای اتصال و بازه آزموده‌شده، مسیر ثبت شماره‌های ۱۰۷ PriceList بررسی‌شده را نشان نمی‌دهند. این نتیجه وجود مسیرهای دیگر، اختلاف نوع/قالب شماره، یا استفاده از شناسه روش متفاوت را رد نمی‌کند. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۲۱. ساختار SYS3.NumberingMethod و SYS3.NumberingMethodRange — ۱۴۰۵/۰۸/۲۳

ساختار واقعی دو جدول اصلی روش شماره‌گذاری تأیید شد:

- `SYS3.NumberingMethod`: شناسه و عنوان روش، تاریخ اثرگذاری، نوع شماره، تنظیمات تعداد ارقام و قالب، شیوه تخصیص، وضعیت، کنترل یکتایی و رفتار شماره‌های ازدست‌رفته.
- `SYS3.NumberingMethodRange`: شناسه بازه، ارجاع به روش، اولویت، شرط Attribute اختیاری، `StartSerial`، `FinishSerial` nullable و `SerialStep`.

**ارتباط با یافته قبلی:** Range=77 قبلاً به‌عنوان بازه روش اعلامیه قیمت بررسی شده است. ساختار جدول با آن یافته سازگار است؛ اما این schema به‌تنهایی الگوریتم انتخاب سریال، DomainKey یا ترتیب عملیات رزرو و ایجاد PriceList را اثبات نمی‌کند.

**مرحله بررسی بعدی:** شناسایی سایر روش‌های شماره‌گذاری که از نظر تنظیمات، مؤلفه/عنوان یا بازه می‌توانند به PriceList مربوط باشند؛ مقایسه داده‌های واقعی با روش ۷۷. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۲۲. بررسی ماژول‌های وابسته به SYS3.NumberingRangeReservation و جست‌وجوی گسترده اشیای شماره‌گذاری — ۱۴۰۵/۰۸/۲۳

جست‌وجوی متنی در `sys.sql_modules` برای عبارت‌های `NumberingMethodRange`، `ReservedSerial` و `DomainKey` این اشیا را نشان داد:

- `SYS3.CreateNumberingMethod`
- `SYS3.CreateNumberingMethodReservation`
- `SYS3.spAddNumberingMethodReservation`
- `SYS3.spRemoveNumberingMethodReservation`
- `SYS3.spRemoveOldNumberingReservations`

جست‌وجوی گسترده‌تر عبارت `NumberingMethod` نیز در ماژول‌های قابل مشاهده فقط همین پنج Procedure را برگرداند. بنابراین در ماژول‌های T-SQL قابل مشاهده، الگوریتم دیگری برای انتخاب `ReservedSerial` یا ساخت `DomainKey` پیدا نشد.

تعریف‌های بررسی‌شده رفتارهای زیر را نشان می‌دهند:

- `CreateNumberingMethod` در صورت نبود روش متناظر، روش، Attribute و Range را ایجاد می‌کند؛ این Procedure شماره یک سند PriceList را تخصیص نمی‌دهد.
- `CreateNumberingMethodReservation` مقادیر Range، DomainKey و ReservedValue را از فراخواننده می‌گیرد و فقط وقتی هیچ رزروی برای آن Range وجود ندارد، رکورد رزرو ایجاد می‌کند.
- `spAddNumberingMethodReservation` مقادیر Range، DomainKey و ReservedValue را از فراخواننده می‌گیرد و درج تکراری همان ترکیب سه‌گانه را با ایندکس یکتا کنترل می‌کند.
- `spRemoveNumberingMethodReservation` رزرو مشخص را بر اساس Range، DomainKey و ReservedValue حذف می‌کند.
- `spRemoveOldNumberingReservations` رزروهای مثبت قدیمی‌تر از ۵۰ شماره جدید را در هر گروه Range و DomainKey حذف می‌کند؛ اجرای آن در جریان ثبت قیمت مجاز/پیشنهاد نشده است.

**نتیجه:** این Procedureها مصرف‌کننده شماره و دامنه هستند، نه تولیدکننده آن‌ها. برای Range=77، شناسه Range مشخص است؛ اما تعیین معتبر `DomainKey` و الگوریتم تخصیص `ReservedSerial` همچنان به بررسی کد برنامه راهکاران یا مسیر رسمی دیگری نیاز دارد. هیچ Procedure یا دستور تغییردهنده‌ای اجرا نشده است.

### ۱۱.۲۳. بررسی اشیای شماره‌گذاری و ماژول‌های CLR — ۱۴۰۵/۰۸/۲۳

جست‌وجوی اشیای دیتابیس با نام‌های شامل Number، Serial و Reservation، موارد زیر را در حوزه SYS3 نشان داد:

- جدول‌های `SYS3.NumberingMethod`، `SYS3.NumberingMethodEntityAttribute`، `SYS3.NumberingMethodRange`، `SYS3.NumberingRangeReservation` و `SYS3.NumberingReseterAttribute`.
- Sequenceهای داخلی تولید شناسه برای جدول‌های NumberingMethod، NumberingMethodEntityAttribute، NumberingMethodRange و NumberingReseterAttribute.
- پنج Procedure شماره‌گذاری ثبت‌شده در بخش ۱۱.۲۲.

همچنین اشیای `ECM.NumberingMethod`، `ECM.NumberingReservation` و `ECM.AssignedSerial` شناسایی شدند. این‌ها زیرسیستم جداگانه‌ای با روابط FK داخلی خود هستند؛ صرف وجود آن‌ها ارتباطی با روش `SYS3` شماره ۷۷ یا PriceList را اثبات نمی‌کند. تطبیق‌های داده‌ای قبلی برای نمونه‌های PriceList نیز این مسیر ECM را تأیید نکرده‌اند.

فهرست ماژول‌های CLR دیتابیس بررسی شد. موارد برگشتی:

| Assembly | Schema.Object | نوع |
|---|---|---|
| PairAttendanceDataCLR | HCM3.fnPairAttendanceDataCLR | CLR_TABLE_VALUED_FUNCTION |
| PairAttendanceDataCLR | HCM3.fnPairAttendanceDataSortedCLR | CLR_TABLE_VALUED_FUNCTION |
| PolicyHelper | SYS3.fnMinifyValue | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnAddPersianMonth | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnAddPersianYear | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnGetDateFromPersianDate | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnGetPersianDayOfMonth | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnGetPersianDaysInMonth | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnGetPersianMonth | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnGetPersianYear | CLR_SCALAR_FUNCTION |
| SqlPersianDateHelper | SYS3.fnGetPersianYearMonth | CLR_SCALAR_FUNCTION |
| SqlZipHelper | SYS3.fnUnZipValue | CLR_SCALAR_FUNCTION |
| SqlZipHelper | SYS3.fnZipValue | CLR_SCALAR_FUNCTION |

**نتیجه محدود:** در فهرست `sys.assembly_modules` هیچ ماژول CLR با نام یا نقش آشکار مرتبط با تخصیص شماره، Reservation، NumberingMethod یا PriceList مشاهده نشد. ماژول‌های CLR موجود عمدتاً برای حضور و داده‌های منابع انسانی، فشرده‌سازی/بازکردن داده، کوچک‌سازی مقدار و تبدیل تاریخ فارسی هستند. این مشاهده وجود کد شماره‌گذاری در اسمبلی‌های خارجی برنامه، سرویس‌های خارج از SQL Server، ماژول رمزگذاری‌شده یا مسیرهای پویا را رد نمی‌کند.

**اثر بر طراحی:** منطق تولید شماره را نباید از Sequenceهای `SYS3_TableIdGen__...` یا از توابع CLR فهرست‌شده استنتاج کرد. Sequenceهای یادشده برای شناسه داخلی رکوردهای تنظیمات شماره‌گذاری‌اند، نه شماره تجاری PriceList. تا زمان شناسایی فراخواننده رسمی، ثبت واقعی PriceList و رزرو شماره نباید پیاده‌سازی یا اجرا شود. تمام بررسی‌های این بخش فقط خواندنی بوده‌اند.


### ۱۱.۲۴. بررسی خروجی جست‌وجوی ماژول‌های T-SQL و ارزیابی رفتار spGetNextId — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** اجرای جست‌وجوی متنی در تعریف ماژول‌های SQL برای عبارت‌های `PriceList`، `PriceListItem`، `PriceListResultRow`، `PriceListConditionRow`، `spGetNextId` و `NumberingRangeReservation` و دریافت متن تعریف اشیای منطبق.

**نتیجه جست‌وجو:**

- چندین Procedure در حوزه‌های ECM، FIN3، FAM3، FSR3، RPA3، SCM3، HCM3 و سایر بخش‌ها به دلیل استفاده از `SYS3.spGetNextId` در نتیجه ظاهر شدند؛ متن‌های دریافت‌شده آن‌ها برای ساخت Correspondent، اطلاعات دارایی، اسناد مالی، مغایرت‌گیری بانکی، مجوزهای امنیتی، Lookupها یا گزارش‌ها بود و ارتباط مستقیمی با ثبت PriceList نشان ندادند.
- در میان نتایج، `SYS3.CreateNumberingMethod`، `SYS3.CreateNumberingMethodReservation`، `SYS3.spAddNumberingMethodReservation` و Procedureهای حذف رزرو نیز دیده شدند؛ رفتار آن‌ها قبلاً در بخش ۱۱.۲۲ تحلیل شده است.
- هیچ تعریف جدیدی از ماژول T-SQL که مستقیماً درج PriceList یا انتخاب `ReservedSerial` و `DomainKey` برای روش ۷۷ را انجام دهد، در خروجی دریافت‌شده مشاهده نشد.
- وجود عبارت `spGetNextId` در متن یک Procedure به‌تنهایی ارتباط آن Procedure با شماره تجاری PriceList را ثابت نمی‌کند؛ در بسیاری از نتایج، این تابع صرفاً برای شناسه داخلی جدول دیگری استفاده شده است.

**نکته مهم درباره `SYS3.spGetNextId`:**

متن کامل Procedure نشان می‌دهد که در حالت `@IsLegacy=0` ابتدا وجود Sequence متناظر با نام `SYS3_TableIdGen__<TableName>` بررسی می‌شود. اگر Sequence وجود نداشته باشد، Procedure با قفل Application آن را از مقدار `SYS3.TableIdGenBacking.LastId` ایجاد می‌کند و سپس با اجرای `NEXT VALUE FOR` یک مقدار از آن می‌گیرد. پس از آن، اگر `@IncValue=0` باشد، مقدار فعلی Sequence خوانده و به خروجی تبدیل می‌شود؛ اگر مقدار غیرصفر باشد، از `sys.sp_sequence_get_range` برای دریافت بازه استفاده می‌شود.

**اصلاح برداشت قبلی:** عبارت «`@IncValue=0` بدون رزرو شناسه مقدار فعلی را می‌خواند» فقط وقتی Sequence از قبل وجود داشته باشد، بدون پیشروی Sequence معتبر است. اگر Sequence متناظر وجود نداشته باشد، همان فراخوانی می‌تواند Sequence را ایجاد کند و یک مقدار اولیه مصرف کند. بنابراین اجرای `spGetNextId` با `@IncValue=0` نیز بدون بررسی قبلی وجود Sequence، عملیات تضمین‌شده read-only نیست و نباید در پژوهش عملیاتی به‌عنوان کوئری بی‌اثر اجرا شود.

**محدودیت جست‌وجو:** جست‌وجوی متنی `sys.sql_modules.definition` فقط تعریف‌های قابل مشاهده و منطبق با عبارت‌های جست‌وجوشده را پوشش می‌دهد. ماژول‌های رمزگذاری‌شده که تعریفشان NULL است، کد خارج از SQL Server، dynamic SQL با نام‌ها/عبارت‌های متفاوت، اسمبلی‌ها و اجزای برنامه راهکاران را رد نمی‌کند. همچنین خروجی این جست‌وجو به‌تنهایی اثبات نمی‌کند که هیچ مسیر دیگری برای ثبت PriceList وجود ندارد.

**اثر بر طراحی:** تولید شناسه داخلی PriceList و تخصیص شماره تجاری PriceList دو مسئله جدا هستند. برای ادامه باید مسیر فراخوانی از برنامه رسمی راهکاران و ماژول‌های رمزگذاری‌شده/غیرقابل مشاهده بررسی شود. هیچ Procedure یا دستور تغییردهنده‌ای در این مرحله اجرا نشده و هیچ شناسه یا شماره‌ای رزرو نشده است.

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
- [x] استخراج تعریف کامل `SYS3.spRemoveNumberingMethodReservation`؛ رفتار حذف هدفمند رزرو ثبت شد.\n- [x] جست‌وجوی ارجاع متنی پنج Procedure در سایر ماژول‌های SQL؛ هیچ ردیفی در `sys.sql_modules` برنگشت. این نتیجه مسیر فراخوانی از برنامه، Job یا ماژول‌های غیرقابل مشاهده را رد نمی‌کند.\n- [x] جست‌وجوی فرمان Job Stepها در `msdb` برای عبارات `Numbering`، `Reservation` و `PriceList`؛ هیچ ردیفی برنگشت. این نتیجه نبود Job مرتبط با نام‌ها/فرمان‌های دیگر یا فراخوانی غیرمستقیم را ثابت نمی‌کند.\n- [x] جست‌وجوی Triggerهای متصل به جداول PriceList/شماره‌گذاری و تعریف‌های حاوی NumberingMethod یا PriceList؛ هیچ ردیفی برنگشت. این نتیجه محدود به متادیتای قابل مشاهده و معیارهای جست‌وجو است.\n- [x] محدودسازی جست‌وجوی ارجاع مستقیم به جدول رزرو با نمایش نام اشیا؛ فقط چهار Procedure شناخته‌شده در `SYS3` یافت شدند. این نتیجه مسیرهای غیرمستقیم یا خارج از SQL را رد نمی‌کند.
- [x] بررسی ساختار دو جدول `SLS3.SalesNumberReservation` و `ECM.NumberingReservation`؛ هر دو ستون زمانی دارند، اما ارتباطشان با رزروهای Range=77 هنوز اثبات نشده است.
- [x] بررسی FKهای دو جدول؛ زنجیره `ECM.NumberingReservation` ← `ECM.AssignedSerial` و اتصال آن به `ECM.NumberingMethod` مشخص شد؛ برای `SLS3.SalesNumberReservation` FK مرتبطی گزارش نشد.
- [x] بررسی ساختار `ECM.AssignedSerial`؛ ستون‌های Value، NumberingMethodRef، CreationDate، EntityCode/EntityRef و NumberingReservationRef مشخص شدند.
- [x] آزمون تطبیق `ECM.AssignedSerial.Value` با `SLS3.PriceList.Number` در داده‌های موجود؛ نتیجه خالی بود، پس این مسیر فعلاً تأیید نشد.
- [x] آزمون تطبیق `SLS3.SalesNumberReservation.TargetNumber` با بازه شماره‌های PriceList (46506–46615)؛ نتیجه خالی بود.
- [x] بررسی ساختار `SYS3.NumberingMethod` و `SYS3.NumberingMethodRange`؛ ستون‌های تنظیمات روش و بازه شناسایی شدند.
- [ ] بررسی تمام رکوردهای روش شماره‌گذاری و تطبیق عنوان/تنظیمات/بازه‌ها با نمونه‌های PriceList؛ روش ۷۷ مرجع مقایسه است.
- [x] جست‌وجوی گسترده ماژول‌های T-SQL برای NumberingMethod، NumberingMethodRange، ReservedSerial و DomainKey؛ فقط پنج Procedure شناخته‌شده پیدا شدند و الگوریتم تخصیص شماره در آن‌ها مشاهده نشد.
- [x] بررسی خروجی جست‌وجوی ماژول‌ها با عبارت‌های PriceList و spGetNextId؛ نتایج متعدد نامرتبط ناشی از استفاده عمومی از spGetNextId تفکیک شدند و ماژول ثبت مستقیم PriceList/تخصیص شماره جدیدی مشاهده نشد. رفتار شرطی spGetNextId با IncValue=0 نیز اصلاح و محدودیت read-only بودن آن ثبت شد.
- [x] بررسی فهرست اشیای دیتابیس با نام‌های Number، Serial و Reservation؛ زیرسیستم SYS3 و اشیای جداگانه ECM شناسایی شدند و ارتباط ECM با PriceList/Range=77 اثبات نشد.
- [x] بررسی `sys.assembly_modules`؛ هیچ CLR module آشکارا مرتبط با شماره‌گذاری PriceList یا رزرو Range=77 مشاهده نشد؛ محدودیت این نتیجه برای کد خارج از SQL و اسمبلی‌های خارجی ثبت شد.
- [ ] بررسی ماژول‌های رمزگذاری‌شده و کد/لاگ برنامه راهکاران برای یافتن فراخوانی واقعی و مسیر رسمی تخصیص شماره. جست‌وجوی متنی ماژول‌های قابل مشاهده در بخش ۱۱.۲۴ الگوریتم تخصیص را پیدا نکرد و ماژول‌های با تعریف NULL را پوشش نمی‌دهد.
- [x] تطبیق رزروهای Range 77 و DomainKey خالی با `SLS3.PriceList.Number`؛ هر ۱۰۷ رزرو به یک PriceList متمایز متصل شد و هیچ رزرو بدون PriceList باقی نماند.
- [ ] بررسی رفتار رزرو شماره در حالت موفق، خطا، Rollback، timeout و درخواست‌های هم‌زمان؛ بدون اجرای عملیات مخرب روی محیط عملیاتی. آزمون تمام ۱۰۷ رکورد در بخش ۱۱.۱۵ هیچ وارونگی زمانی یا شناسه‌ای نشان نداد، اما ترتیب دستورات و رفتار شکست همچنان نامشخص است. نمونه ۳۰ رکورد اخیر در بخش ۱۱.۱۴ ترتیب همبسته Serial، CreationDate و PriceListID را نشان می‌دهد، اما ترتیب دستورات و رفتار شکست را اثبات نمی‌کند.
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
| ۱۴۰۵/۰۸/۲۳ | ایجاد مستند اولیه از یافته‌های ساختار PriceList، روابط، شناسه‌ها، سیاست قیمت، کاربر ثبت‌کننده، روش شماره‌گذاری ۷۷، بازه و رزروهای مشاهده‌شده و فهرست Procedureهای شماره‌گذاری. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت پارامترها و تحلیل متن چهار Procedure شماره‌گذاری؛ مشخص‌کردن تفاوت دو روش ایجاد Reservation، رفتار rollback در `spAddNumberingMethodReservation`، حذف رزروهای قدیمی و باقی‌ماندن تعریف Procedure حذف رزرو برای استخراج. |\n| ۱۴۰۵/۰۸/۲۳ | تکمیل تعریف `spRemoveNumberingMethodReservation`؛ ثبت رفتار حذف بر اساس Range، DomainKey و ReservedValue و به‌روزرسانی فهرست اقدامات پژوهش. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت نتیجه جست‌وجوی ارجاع متنی پنج Procedure در `sys.sql_modules`؛ هیچ ارجاع دیگری یافت نشد و محدودیت‌های این نتیجه مستند شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت نتیجه جست‌وجوی Job Stepهای SQL Agent برای عبارات Numbering، Reservation و PriceList؛ هیچ تطبیقی یافت نشد و محدودیت‌های نتیجه مستند شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت نتیجه جست‌وجوی Triggerهای مرتبط با PriceList و شماره‌گذاری؛ هیچ ردیفی برنگشت و محدودیت‌های نتیجه مستند شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت FK جدول `SYS3.NumberingRangeReservation` به `SYS3.NumberingMethodRange` از طریق `NumberingMethodRangeRef`؛ محدودیت استنباط از این رابطه نیز ثبت شد. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت PK خوشه‌ای و ایندکس یکتای سه‌ستونی Range + DomainKey + ReservedSerial در جدول رزرو شماره؛ ارتباط آن با کنترل تکرار در Procedure ثبت شد. |\n| ۱۴۰۵/۰۸/۲۳ | مستندسازی metadata سه جدول NumberingMethod، NumberingMethodRange و NumberingRangeReservation؛ مشخص شد شناسه جدول Reservation از نوع Identity است. |\n| ۱۴۰۵/۰۸/۲۳ | ثبت تجمیع رزروها بر اساس Range و DomainKey؛ ارتباط آماری Range 77 با بازه شماره‌های PriceList و محدودیت استنتاج شماره بعدی مستند شد. |
| ۱۴۰۵/۰۸/۲۳ | ثبت جست‌وجوی محدودشده ارجاع مستقیم به جدول رزرو؛ فقط چهار Procedure شناخته‌شده پیدا شدند و محدودیت نتیجه برای مسیرهای غیرمستقیم مستند شد. |
| ۱۴۰۵/۰۸/۲۳ | ثبت تطبیق کامل ۱۰۷ رزرو Range 77 با ۱۰۷ رکورد متمایز PriceList از طریق `ReservedSerial = TRY_CONVERT(BIGINT, Number)`؛ محدودیت استنتاج درباره الگوریتم تخصیص شماره نیز درج شد. |
| ۱۴۰۵/۰۸/۲۳ | ثبت بررسی ۳۰ رزرو/PriceList اخیر؛ همبستگی ترتیب Serial با CreationDate و PriceListID و محدودیت استنتاج ترتیب دستورات اجرایی مستند شد. |
| ۱۴۰۵/۰۸/۲۳ | ثبت آزمون ترتیب تمام ۱۰۷ رکورد؛ هیچ وارونگی در CreationDate، PriceListID یا ReservationID نسبت به Serial مشاهده نشد و محدودیت استنتاج از داده نهایی حفظ شد. |
| ۱۴۰۵/۰۸/۲۳ | ثبت فهرست جدول‌های بالقوه مرتبط با شماره‌گذاری/تاریخچه؛ `SLS3.SalesNumberReservation`، `ECM.NumberingReservation` و تاریخچه‌های عمومی برای بررسی ساختاری بعدی مشخص شدند. |
| ۱۴۰۵/۰۸/۲۳ | ثبت ساختار `SLS3.SalesNumberReservation` و `ECM.NumberingReservation`؛ وجود ستون‌های زمانی ثبت شد، اما ارتباط با رزروهای Range=77 هنوز اثبات نشده است. |
| ۱۴۰۵/۰۸/۲۳ | ثبت FKهای `ECM.NumberingReservation`؛ ارتباط با `ECM.NumberingMethod`، کاربران و جدول فرزند `ECM.AssignedSerial` مشخص شد؛ ارتباط مستقیم با SYS3 Range هنوز اثبات نشده است. |
| ۱۴۰۵/۰۸/۲۳ | ثبت ساختار `ECM.AssignedSerial`؛ وجود Value، CreationDate، EntityCode/EntityRef و ارجاع nullable به NumberingReservation ثبت شد؛ ارتباط با PriceList هنوز نیازمند تطبیق داده است. |
| ۱۴۰۵/۰۸/۲۳ | آزمون تطبیق شماره‌های PriceList با `ECM.AssignedSerial` و `SLS3.SalesNumberReservation` در بازه 46506–46615 نتیجه خالی داد؛ این دو مسیر با معیار آزموده‌شده تأیید نشدند. |
| ۱۴۰۵/۰۸/۲۳ | ثبت جست‌وجوی گسترده اشیای شماره‌گذاری و ماژول‌های T-SQL؛ فقط پنج Procedure شناخته‌شده در SYS3 یافت شدند و الگوریتم تولید ReservedSerial/DomainKey مشخص نشد. فهرست CLR نیز بررسی شد و ماژول آشکارا مرتبط با شماره‌گذاری PriceList پیدا نشد؛ محدودیت نتیجه مستند شد. |
| ۱۴۰۵/۰۸/۲۳ | ثبت ساختار `SYS3.NumberingMethod` و `SYS3.NumberingMethodRange`؛ تنظیمات روش و بازه مشخص شد، اما الگوریتم تخصیص/DomainKey هنوز از schema قابل استنتاج نیست. |


### ۱۱.۲۵. فهرست ماژول‌های SQL رمزگذاری‌شده — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** استخراج اشیای رمزگذاری‌شده از `sys.objects` با فیلتر `OBJECTPROPERTY(object_id, 'IsEncrypted') = 1` برای انواع Procedure، Function، Trigger و View.

**خروجی:**

| Schema | Object | نوع | تاریخ ایجاد/تغییر |
|---|---|---|---|
| HCM3 | fnGenerateDatesTable | SQL_TABLE_VALUED_FUNCTION | 2026-08-17 15:47:41.227 |
| HCM3 | fnGenerateMonthTable | SQL_TABLE_VALUED_FUNCTION | 2026-08-17 15:47:41.240 |

**نتیجه:** در خروجی این بررسی فقط دو تابع رمزگذاری‌شده و هر دو در Schema مربوط به HCM3 مشاهده شدند. هیچ ماژول رمزگذاری‌شده‌ای در Schemaهای SYS3 یا SLS3 در این فهرست گزارش نشد؛ بنابراین این دو تابع، بر اساس نام و حوزه Schema، شاهدی برای وجود منطق رمزگذاری‌شده ثبت PriceList یا تخصیص شماره روش ۷۷ فراهم نمی‌کنند.

**محدودیت:** این نتیجه فقط اشیای SQL رمزگذاری‌شده قابل مشاهده در `sys.objects` و انواع انتخاب‌شده را پوشش می‌دهد. کد برنامه راهکاران، اسمبلی‌ها/اجزای خارجی، ماژول‌های عادی با dynamic SQL، سرویس‌ها و منطق سمت کلاینت را پوشش نمی‌دهد. نبود ماژول رمزگذاری‌شده در SYS3/SLS3 نیز به‌تنهایی نبود مسیر تخصیص شماره در برنامه را اثبات نمی‌کند.

**اثر بر طراحی:** مسیر بعدی پژوهش باید بر شناسایی کد/رفتار برنامه رسمی راهکاران و منشأ فراخوانی عملیات ایجاد PriceList متمرکز شود؛ از روی دو تابع HCM3 نباید الگوریتم شماره‌گذاری استنتاج کرد. هیچ داده‌ای تغییر نکرده و هیچ Procedure اجرا نشده است.


### ۱۱.۲۶. وابستگی‌های ثبت‌شده در sys.sql_expression_dependencies — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی وابستگی‌های ایستای SQL برای اشیای مرجع `PriceList`، `PriceListItem`، `PriceListResultRow`، `PriceListConditionRow`، `NumberingRangeReservation` و `NumberingMethodRange`.

**خروجی:**

| شیء وابسته | نوع | شیء مرجع |
|---|---|---|
| SYS3.CreateNumberingMethod | SQL_STORED_PROCEDURE | SYS3.NumberingMethodRange |
| SYS3.CreateNumberingMethodReservation | SQL_STORED_PROCEDURE | SYS3.NumberingRangeReservation |
| SYS3.spAddNumberingMethodReservation | SQL_STORED_PROCEDURE | SYS3.NumberingRangeReservation |
| SYS3.spRemoveNumberingMethodReservation | SQL_STORED_PROCEDURE | SYS3.NumberingRangeReservation |
| SYS3.spRemoveOldNumberingReservations | SQL_STORED_PROCEDURE | SYS3.NumberingRangeReservation |

در خروجی هیچ وابستگی ایستای ثبت‌شده‌ای به چهار جدول `SLS3.PriceList`، `SLS3.PriceListItem`، `SLS3.PriceListResultRow` و `SLS3.PriceListConditionRow` مشاهده نشد.

**نتیجه:** خروجی با تحلیل قبلی پنج Procedure شماره‌گذاری سازگار است. وابستگی‌های ایستای قابل مشاهده برای دو جدول رزرو/بازه فقط به همان پنج Procedure شناخته‌شده محدود شده‌اند؛ مسیر ثبت PriceList از طریق این فهرست وابستگی‌ها پیدا نشد.

**محدودیت:** `sys.sql_expression_dependencies` تمام وابستگی‌های runtime را نشان نمی‌دهد. نام جدول‌هایی که در dynamic SQL ساخته می‌شوند، کد برنامه خارج از SQL Server، اجزای خارجی و مسیرهایی که وابستگی‌شان قابل ثبت/حل نیست ممکن است در این خروجی دیده نشوند. نبود نتیجه برای جداول PriceList اثبات نمی‌کند که برنامه راهکاران آن‌ها را مستقیماً یا با SQL پویا تغییر نمی‌دهد.

**اثر بر طراحی:** برای تعیین مسیر رسمی ثبت، لازم است رفتار برنامه راهکاران هنگام ایجاد یک PriceList در محیط مجاز مشاهده یا از کد/لاگ برنامه استخراج شود. تا زمان شناسایی آن مسیر، تولید دستی شماره، DomainKey یا رزرو شماره قابل قبول نیست. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۲۷. بررسی Triggerهای چهار جدول PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** جست‌وجوی Triggerهای متصل به `SLS3.PriceList`، `SLS3.PriceListItem`، `SLS3.PriceListResultRow` و `SLS3.PriceListConditionRow` همراه با وضعیت فعال/غیرفعال، نوع INSTEAD OF، وضعیت رمزگذاری و متن تعریف.

**نتیجه:** کوئری هیچ ردیفی برنگرداند؛ بنابراین برای این چهار جدول Trigger متصلِ قابل مشاهده‌ای در `sys.triggers` یافت نشد.

**اثر بر تحلیل:** Triggerهای جدول‌محور، مسیر ثبت یا تولید شماره را برای این چهار جدول توضیح نمی‌دهند. این نتیجه، منطق سمت برنامه، فراخوانی‌های مستقیم/پویا، Triggerهای سطح دیتابیس یا سرور و اجزای خارجی را رد نمی‌کند. هنوز مسیر رسمی ایجاد PriceList و تخصیص Number مشخص نشده است.

**ایمنی:** هیچ داده‌ای تغییر نکرده و هیچ عملیات رزرو یا تولید شناسه اجرا نشده است.


### ۱۱.۲۸. قیود ستون‌ها، Identity و Default جداول PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** خواندن ویژگی ستون‌های چهار جدول قیمت از `sys.columns`، `sys.default_constraints` و `sys.check_constraints`.

**نتایج قطعی از خروجی:**
- هیچ‌یک از ستون‌های گزارش‌شده در چهار جدول `PriceList`، `PriceListItem`، `PriceListResultRow` و `PriceListConditionRow` خاصیت Identity ندارند.
- برای هیچ‌یک از ستون‌های گزارش‌شده Default Constraint مشاهده نشد.
- هیچ Check Constraint متصل به این چهار جدول در خروجی مشاهده نشد.
- ستون‌های کلیدی شناسه در هر چهار جدول اجباری و غیر Identity هستند؛ بنابراین تولید شناسه باید از مسیر رسمی سامانه انجام شود و نباید به مقدار پیش‌فرض یا Identity اتکا کرد.
- `PriceList.Number` و ستون‌های اصلی اجباری PriceList نیز Default ندارند؛ شماره و سایر مقادیر لازم باید از منبع معتبر برنامه استخراج شوند.
- `PriceList.Version`، `PriceListItem.Version`، `PriceListResultRow.Version` و `PriceListConditionRow.Version` اجباری گزارش شده‌اند، اما در این خروجی Default ندارند. نوع واقعی آن‌ها و رفتار rowversion باید از metadata نوع ستون تأیید شود؛ نباید برای این ستون‌ها مقدار دستی تولید کرد.
- در `PriceListItem`، `ProductRef` و `ProductGroupRef` nullable هستند، در حالی که `ProductUnitRef`، `CurrencyRef`، `Fee` و `Definitiveness` اجباری‌اند.
- در `PriceListResultRow`، تمام ابعاد کسب‌وکاری گزارش‌شده nullable هستند؛ فقط شناسه، PriceListRef و Version اجباری‌اند.
- در `PriceListConditionRow`، ارجاع‌های Policy، Condition، ConditionRow، PriceList و PriceListItem اجباری‌اند؛ `PriceListResultRowRef` و `EndDate` nullable هستند.

**محدودیت:** نبود Default و Check Constraint در این خروجی به معنی نبود تمام قواعد اعتبارسنجی نیست؛ Foreign Key، Unique/Nonclustered Index، قواعد برنامه و منطق سمت سرور همچنان باید جداگانه بررسی شوند. خروجی حاضر نوع داده و دقت عددی ستون‌ها را نیز نشان نمی‌دهد.

**اثر بر طراحی:** ثبت نباید بر Default یا Identity فرضی متکی باشد. همه مقادیر اجباری باید از رکوردهای معتبر و مسیر رسمی راهکاران استخراج شوند؛ ستون‌های Version نباید دستی مقداردهی شوند. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۲۹. نوع داده و Foreign Key جداول PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** استخراج نوع، طول، دقت، مقیاس و Foreign Keyهای چهار جدول از کاتالوگ سیستم SQL Server.

**نتایج مهم:**
- شناسه‌های اصلی چهار جدول از نوع `bigint` هستند و Identity نیستند.
- `PriceList.Number` از نوع `nvarchar(100)` و `Title` از نوع `nvarchar(512)` است.
- تاریخ‌های PriceList و EndDate در ConditionRow از نوع `datetime` با دقت گزارش‌شده scale=3 هستند.
- `PriceList.Version` و Version سه جدول وابسته از نوع `timestamp` هستند؛ این نوع در SQL Server معادل rowversion است و نباید مقدار آن را در INSERT/UPDATE دستی تعیین کرد.
- `PriceListItem.Fee`، `Quantity`، `ReductionTolerance`، `AdditionTolerance` و `Weight` از نوع `decimal(28,6)` هستند.
- `PriceListItem.ProductUnitRef` به `GNR3.Unit.UnitID` و `CurrencyRef` به `GNR3.Currency.CurrencyID` ارجاع می‌دهند. `ProductRef` به `SLS3.Product.ProductID` و `ProductGroupRef` به `GNR3.EntityGroup.EntityGroupID` وصل است.
- `PriceList.PriceListParametersRef` به `SLS3.PriceListParameters.PriceListParametersID` و `CurrencyRef` به `GNR3.Currency.CurrencyID` متصل است؛ Creator و LastModifier نیز به `SYS3.User.UserID` ارجاع دارند.
- `PriceListItem.PriceListRef` به PriceList متصل است؛ فیلدهای Package، Batch و PartTrackingFactorها به جداول LGS3 مربوط متصل‌اند.
- `PriceListResultRow.PriceListRef` به PriceList و PaymentAgreementRef به `SLS3.PaymentAgreement.PaymentAgreementID` متصل است. ابعاد دیگری مانند Customer، SalesArea، SalesType، SalesChannel، SalesOrganization، Division، SalesOffice، SalesTerritory، Broker، ProductPack، Plant و Store نیز FKهای مشخص دارند.
- `PriceListConditionRow` به PriceList، PriceListItem، PriceListResultRow، BusinessPolicy و BusinessPolicyCondition متصل است. `BusinessPolicyConditionRowRef` در این خروجی FK گزارش‌شده‌ای ندارد.
- `TermOfPaymentRef` و `RouteRef` در PriceListResultRow نیز در این خروجی FK ثبت‌شده‌ای ندارند؛ نبود FK به‌تنهایی معنای آن‌ها یا معتبر بودن مقادیر را تعیین نمی‌کند.

**محدودیت:** این خروجی فقط Foreign Keyهای ثبت‌شده در metadata را نشان می‌دهد؛ روابط منطقی بدون FK و قواعد برنامه ممکن است وجود داشته باشند. همچنین نوع و طول داده به‌تنهایی مقادیر مجاز Enumها یا قواعد اعتبارسنجی را مشخص نمی‌کند.

**اثر بر طراحی:** پیاده‌سازی باید دقت `decimal(28,6)` را حفظ کند، Version/rowversion را از SQL Server دریافت کند و تمام ارجاع‌ها را با رکوردهای واقعی و معتبر تطبیق دهد. شناسه‌ها و ارجاع‌های بدون FK نباید با حدس یا مقدار ثابت ساخته شوند. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۳۰. کلیدهای اصلی و ایندکس‌های جداول PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** استخراج ایندکس‌ها، کلیدهای اصلی، یکتایی، ستون‌های کلیدی و Included Columnها از کاتالوگ SQL Server برای چهار جدول قیمت.

**نتایج:**
- هر چهار جدول فقط یک ایندکس یکتای گزارش‌شده دارند که همان Primary Key خوشه‌ای روی شناسه اصلی جدول است:
  - `PriceList(PriceListID)`
  - `PriceListItem(PriceListItemID)`
  - `PriceListResultRow(PriceListResultRowID)`
  - `PriceListConditionRow(PriceListConditionRowID)`
- در خروجی هیچ Unique Index یا Unique Constraint دیگری برای این چهار جدول گزارش نشد؛ بنابراین metadata بررسی‌شده، یکتایی `PriceList.Number`، ترکیب محصول/فهرست یا ترکیب Conditionها را با ایندکس یکتا تضمین نمی‌کند.
- ایندکس‌های غیریکتا عمدتاً برای FKها و جست‌وجوی ارتباطی ایجاد شده‌اند؛ از جمله PriceListRef در سه جدول وابسته، PaymentAgreementRef در ResultRow، و BusinessPolicy/Condition/Item/ResultRow refs در ConditionRow.
- ایندکس `IX_SLS3_PriceListConditionRow_PriceListRef_EndDate` کلیدهای `PriceListRef, EndDate` دارد و ستون‌های `PriceListConditionRowID`، `PriceListItemRef` و `PriceListResultRowRef` را به‌صورت INCLUDE نگه می‌دارد.
- در خروجی، ایندکس با نام `IX_SLS3_PriceList_FeePolicyRef` ستون `FeeMapperPolicyRef` را نشان می‌دهد، نه `FeePolicyRef`. این عدم تطابق نام و ستون باید در صورت نیاز بهینه‌سازی/بررسی ایندکس‌ها با metadata دقیق‌تر تأیید شود و نباید بر اساس نام ایندکس فرض گرفته شود.
- ایندکس‌های گزارش‌شده Filtered نیستند.

**محدودیت:** نبود Unique Index فقط نبود تضمین یکتایی در ایندکس‌های این چهار جدول را نشان می‌دهد؛ ممکن است برنامه راهکاران قبل از ثبت، یکتایی را در منطق خود کنترل کند. این خروجی رفتار تراکنش، ترتیب ثبت یا الگوریتم شماره‌گذاری را مشخص نمی‌کند.

**اثر بر طراحی:** منطق برنامه نباید یکتایی Number یا ردیف‌ها را به قید یکتای دیتابیس نسبت دهد. کنترل تکرار و هم‌زمانی باید از مسیر رسمی برنامه/تراکنش و قواعد واقعی راهکاران استخراج شود. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۳۱. مشخصات کامل Foreign Keyهای جداول PriceList — ۱۴۰۵/۰۸/۲۳

**روش بررسی:** استخراج روابط FK، ستون‌های دو سمت رابطه، رفتار ON DELETE/UPDATE و وضعیت فعال/Trusted بودن قیود برای چهار جدول قیمت.

**نتایج:**
- تمام Foreign Keyهای گزارش‌شده فعال و Trusted هستند؛ هیچ‌کدام Disabled یا Not Trusted نیستند.
- تمام روابط دارای `NO_ACTION` برای حذف و به‌روزرسانی هستند؛ بنابراین SQL Server حذف یا تغییر کلید مرجع را در صورت وجود ردیف وابسته خودکار Cascade نمی‌کند.
- روابط اصلی ساختار:
  - PriceList به PriceListParameters، Currency، Creator/LastModifier و در صورت مقداردهی به BusinessPolicyها متصل است.
  - PriceListItem به PriceList، Product، ProductGroup، Unit، Currency و در صورت استفاده به Package، Batch و PartTrackingFactorها متصل است.
  - PriceListResultRow به PriceList و ابعاد اختیاری از جمله PaymentAgreement، Customer، SalesArea، SalesType، SalesChannel، SalesOrganization، Division، SalesOffice، SalesTerritory، Broker، ProductPack، Plant و Store متصل است.
  - PriceListConditionRow به BusinessPolicy، BusinessPolicyCondition، PriceList، PriceListItem و در صورت مقداردهی به PriceListResultRow متصل است.
- `BusinessPolicyConditionRowRef` در خروجی قبلی FK ندارد؛ این رابطه باید از مدل/داده‌های واقعی ConditionRow و Policy بررسی شود، نه با حدس.
- `TermOfPaymentRef` و `RouteRef` در PriceListResultRow نیز FK گزارش‌شده ندارند؛ نباید برای آن‌ها شناسه یا معنای مرجع را از روی نام فرض کرد.
- نام FK مربوط به FeePolicy دارای املای غیرمعمول `FK_SLS3_PriceList_FeePol3icyRef` است؛ ستون واقعی آن FeePolicyRef است و این نام صرفاً metadata است.

**هدف پژوهش و چرایی این بررسی:** هدف، پیاده‌سازی امن گزینه «ثبت در راهکاران» در پروژه Pricing است؛ به‌طوری‌که یک یا چند محصول تأییدشده همراه قیمت و شرایط پرداخت در ساختار PriceList ثبت شوند. بررسی FKها مشخص می‌کند هر ردیف ثبت‌شونده باید به کدام رکورد معتبر موجود متصل باشد و از ساخت ارجاع‌های نامعتبر یا حذف/تغییر ناخواسته داده‌های مرجع جلوگیری می‌کند.

**گام‌های هنوز حل‌نشده:** این بررسی ساختار روابط را روشن می‌کند، اما هنوز مسیر رسمی ایجاد PriceList، تولید Number در روش شماره‌گذاری ۷۷، تولید شناسه‌های اصلی، ترتیب درج چهار جدول، مقادیر لازم برای ResultRow/ConditionRow و قواعد اعتبارسنجی برنامه راهکاران را اثبات نمی‌کند. برای تکمیل این موارد باید نمونه‌های واقعی ثبت‌شده و تفاوت بین آن‌ها، یا رفتار برنامه رسمی هنگام ایجاد یک PriceList، بررسی شود.

**ایمنی:** تمام بررسی‌ها فقط خواندنی بوده‌اند؛ هیچ شماره یا شناسه‌ای رزرو نشده و هیچ داده‌ای تغییر نکرده است.


### ۱۱.۳۲. بررسی نمونه‌های PriceList شماره ۴۶۵۹۶ تا ۴۶۶۰۶ — نتیجه مقدماتی

**روش بررسی:** اتصال PriceList به PriceListItem، PriceListResultRow و PriceListConditionRow برای شناسه‌های 48076 تا 48095 با محدودیت `TOP (50)`.

**مشاهدات قابل اتکا از ردیف‌های دریافت‌شده:**
- نمونه‌های مشاهده‌شده از `PriceListParametersRef=2`، `PriceListType=1`، `SalesDocumentType=2`، `CurrencyRef=1` و `State=2` استفاده می‌کنند؛ این‌ها فقط مقادیر نمونه‌اند و نباید به‌عنوان ثابت برنامه در نظر گرفته شوند.
- در نمونه‌های مشاهده‌شده، هر PriceListItem دارای یک محصول، RowNumber=1، ProductUnitRef=1، ItemCurrencyRef=1 و Definitiveness=1 است؛ Fee با دقت شش رقم اعشار ذخیره شده است.
- نمونه‌های دارای چند PaymentAgreement در ResultRow چند ردیف نتیجه دارند؛ نمونه 48085 چهار توافق پرداخت متمایز را نشان می‌دهد.
- ردیف‌های ConditionRow به BusinessPolicyRef=185 و BusinessPolicyConditionRef=163 ارجاع می‌دهند. در داده‌های مشاهده‌شده، BusinessPolicyConditionRowRefها متفاوت‌اند و PriceListResultRowRef به شناسه ردیف نتیجه اشاره می‌کند.
- در نمونه 48086، مقادیر ConditionRow نشان می‌دهند که یک ردیف PriceListResultRow می‌تواند با چند ConditionRow مرتبط باشد؛ بخشی از خروجی به دلیل محدودیت TOP (50) و اتصال چندبه‌چند حاصل از join ممکن است خارج از نتیجه مانده باشد.

**هشدار تحلیلی مهم:** کوئری قبلی چهار جدول فرزند را فقط با `PriceListRef` به هم متصل کرده بود. بنابراین وقتی یک PriceList چند ResultRow و چند ConditionRow دارد، خروجی حاصل cross-product می‌شود و یک ردیف نتیجه در چند سطر تکرار می‌شود. این تکرارها به معنی وجود ردیف‌های تکراری در دیتابیس نیستند. برای تحلیل رابطه دقیق باید ConditionRow را با `PriceListResultRowRef = PriceListResultRowID` به ResultRow متصل کرد و شمارش‌ها را جداگانه انجام داد.

**محدودیت:** به علت `TOP (50)` و cross-product، خروجی فعلی برای نتیجه‌گیری درباره تعداد کامل ردیف‌ها یا همه روابط نمونه‌های 48076 تا 48095 کافی نیست. هیچ مقدار ثابت یا الگوریتم ثبت از این نمونه‌ها استنتاج نمی‌شود.

**گام بعدی:** اجرای کوئری تفکیک‌شده و بدون cross-product برای شمارش Item/ResultRow/ConditionRow و بررسی تطبیق دقیق ConditionRow با ResultRow. هیچ داده‌ای تغییر نکرده است.


### ۱۱.۳۳. تطبیق دقیق PriceListItem، ResultRow و ConditionRow در نمونه‌های ۴۸۰۷۶ تا ۴۸۰۹۵

خروجی تفکیک‌شده نشان داد اتصال مستقیم `PriceListConditionRow.PriceListResultRowRef` به `PriceListResultRow.PriceListResultRowID` برقرار است و در تمام ردیف‌های گزارش‌شده وضعیت «اتصال مستقیم» ثبت شده است. بنابراین ConditionRow فقط به سربرگ PriceList وابسته نیست؛ به‌صورت دقیق به یک ResultRow و نیز یک PriceListItem متصل می‌شود.

**الگوهای مشاهده‌شده:**
- فهرست‌های تک‌محصولی با یک توافق پرداخت: یک Item، یک ResultRow و یک ConditionRow؛ مانند 48077 و 48078.
- فهرست تک‌محصولی با دو توافق پرداخت: یک Item، دو ResultRow و دو ConditionRow؛ مانند 48076، 48079 و 48082.
- فهرست تک‌محصولی با چهار توافق پرداخت: یک Item، چهار ResultRow و چهار ConditionRow؛ نمونه 48085.
- فهرست چندمحصولی با دو توافق پرداخت: نمونه 48086 دارای 11 Item، دو ResultRow و 22 ConditionRow است. برای هر ResultRow یازده ConditionRow وجود دارد و هر ConditionRow به یکی از یازده Item متصل است.
- نمونه 48090 دارای 10 Item، دو ResultRow و 20 ConditionRow است؛ برای هر توافق پرداخت، ده شرط متناظر با ده Item ثبت شده است.
- نمونه 48091 دارای 10 Item، یک ResultRow و 10 ConditionRow است.
- نمونه 48092 دارای دو Item، دو ResultRow و چهار ConditionRow است؛ هر ResultRow به هر دو Item از طریق دو ConditionRow متصل شده است.
- نمونه‌های 48093 و 48094 هرکدام دو Item، یک ResultRow و دو ConditionRow دارند.
- نمونه 48095 دارای دو Item، یک ResultRow و دو ConditionRow است.

**نتیجه ساختاری:** در این نمونه‌ها تعداد ConditionRow با حاصل‌ضرب تعداد Item و تعداد ResultRow برابر است و هر زوج Item/ResultRow یک ConditionRow متناظر دارد. این یک الگوی تجربی قوی برای این مجموعه است، نه تضمین قاعده سراسری نرم‌افزار؛ در پیاده‌سازی باید روابط واقعی و الزامات سیاست تجاری خوانده شوند، نه اینکه تعداد یا ترتیب ردیف‌ها hardcode شود.

**اهمیت برای ثبت:** ایجاد صرفاً یک PriceListItem و چند ResultRow کافی نیست. برای هر زوج معتبر Item/ResultRow باید ConditionRow متناظر با BusinessPolicyConditionRowRef صحیح ساخته شود. تعیین اینکه ConditionRowRef از کجا و با چه ورودی/خروجی ساخته یا انتخاب می‌شود، همچنان نیازمند بررسی رکوردهای واقعی BusinessPolicyConditionRow و XML ورودی/خروجی آن‌هاست.

هیچ داده‌ای تغییر نکرده است؛ این بخش صرفاً مستندسازی خروجی خواندنی کاربر است.


### ۱۱.۳۴. شناسایی جداول مرجع BusinessPolicyConditionRow و ساختار ورودی/خروجی

**منبع:** خروجی متادیتای `sys.tables/sys.columns` که کاربر در 2026-11-15 ارسال کرد.

**یافته‌های قطعی از متادیتا:**
- جداول عملیاتی مرتبط در schema `GNR3` عبارت‌اند از:
  - `GNR3.BusinessPolicy`
  - `GNR3.BusinessPolicyCondition`
  - `GNR3.BusinessPolicyConditionRow`
  - `GNR3.BusinessPolicyInputValue`
  - `GNR3.BusinessPolicyOutputValue`
  - `GNR3.BusinessPolicyParameter`
  - `GNR3.BusinessPolicyParameterAssociation`
- `GNR3.BusinessPolicyConditionRow` ستون‌های `BusinessPolicyConditionRowID`، `BusinessPolicyConditionRef`، `Index`، `Title`، تاریخ‌های شروع/پایان، `CreationDate`، `ForceToUnload`، `BusinessPolicyInputRef` و `BusinessPolicyOutputRef` را دارد.
- در جدول عملیاتی `GNR3.BusinessPolicyConditionRow` ستون XML به نام `ParameterValues` وجود ندارد؛ ورودی و خروجی از طریق دو Ref جداگانه نگهداری می‌شوند.
- `GNR3.BusinessPolicyInputValue.Value` و `GNR3.BusinessPolicyOutputValue.Value` از نوع nvarchar(max) هستند و می‌توانند محل ذخیره محتوای متنی/XML باشند؛ محتوای واقعی هنوز بررسی نشده است.
- جدول‌های `dbo.BusinessPolicyCondition_14010722_1130` و `dbo.BusinessPolicyConditionRow_14010722_1130` در خروجی وجود دارند و به‌نظر جدول‌های تاریخ‌دار/نسخه‌ای هستند؛ نباید آن‌ها را بدون بررسی به‌جای جداول عملیاتی GNR3 استفاده کرد. جدول Row تاریخ‌دار ستون `ParameterValues` از نوع XML دارد، اما این ستون در جدول عملیاتی فعلی دیده نشد.
- جدول `GNR3.BusinessPolicyConditionRow` به `BusinessPolicyInputRef` و `BusinessPolicyOutputRef` اشاره دارد؛ جدول‌های مقدار ورودی و خروجی نیز ساختار مستقل دارند. FK و محتوای دقیق این Refها هنوز باید بررسی شود.

**اقدام بعدی:** بررسی رکوردهای واقعی Policy=185 و Condition=163 در جداول عملیاتی GNR3، به‌همراه اتصال Row به InputValue/OutputValue و استخراج امن XML/متن Value. سپس باید تطبیق ردیف‌های ConditionRowRef نمونه‌های PriceList با ردیف‌های فعال و معتبر انجام شود.

هیچ داده‌ای تغییر نکرده است. تمام بررسی‌ها read-only بوده‌اند.

### ۱۱.۳۵. بررسی XML ورودی/خروجی ۵۰ ردیف اول سیاست ۱۸۵ و شرط ۱۶۳

**منبع:** خروجی کاربر از کوئری اتصال جداول عملیاتی GNR3 با فیلتر PolicyID=185 و ConditionID=163 و محدودیت TOP (50)، مورخ ۱۴۰۵/۰۸/۲۴.

**مشاهدات مستقیم از خروجی:**
- تمام ۵۰ ردیف برگردانده‌شده دارای BusinessPolicyID=185، عنوان «اعلامیه قیمت فروش 2»، BusinessPolicyConditionID=163، ConditionModel=1 و ConditionStatus=0 هستند.
- شناسه‌های ردیف شرط در این خروجی 1290060 تا 1290109 هستند. همه ردیف‌ها RowStartDate=2022-10-15 00:01:00 دارند و تاریخ پایان آن‌ها در بازه 2022-11-08 تا 2022-11-30 ثبت شده است؛ بنابراین این ۵۰ ردیف نمونه‌های قدیمی و منقضی‌اند و نباید برای ثبت جاری انتخاب شوند.
- Index و ConditionRowTitle در تمام ردیف‌های نمایش‌داده‌شده NULL هستند.
- ForceToUnload=1 در تمام این ردیف‌ها دیده می‌شود.
- هر ردیف به یک BusinessPolicyInputValue و یک BusinessPolicyOutputValue ارجاع می‌دهد. محتوای Value در خروجی واقعاً XML است؛ بنابراین داده ورودی/خروجی از ستون‌های Value قابل خواندن است و ستون عملیاتی ParameterValues برای این مسیر لازم نیست.
- XML ورودی هر ردیف شامل ۱۲ عنصر متوالی Input است. ترتیب مقادیر در نمونه‌های این خروجی چنین است: جایگاه ۱ مقدار Int64 برابر 1؛ جایگاه ۲ شناسه Int64 محصول؛ جایگاه ۳ مقدار null؛ جایگاه ۴ مقدار Int64 واحد (در نمونه‌ها 1 یا 9)؛ جایگاه ۵ مقدار Int64 توافق پرداخت (در این خروجی 23 یا 24)؛ جایگاه‌های ۶ تا ۱۲ همگی null.
- این ترتیب با نگاشت مفهومی ۱۲ پارامتر ثبت‌شده در بخش ۹.۱ هم‌راستاست، اما این خروجی به‌تنهایی Association یا تعریف رسمی نام و نوع هر پارامتر را اثبات نمی‌کند؛ تعریف رسمی باید از BusinessPolicyParameter و BusinessPolicyParameterAssociation و نمونه‌های جاری تأیید شود.
- XML خروجی هر ردیف یک Output و یک ValueWithDescription از نوع Decimal دارد. نمونه‌های خروجی مقادیر متفاوتی از جمله 1، 2199488، 16057، 41500 و مقادیر دیگر دارند. در بسیاری از زوج‌های ردیف با توافق پرداخت 23 و 24، OutputValueRef و مقدار خروجی یکسان است؛ این مشاهده برای تمام سیاست یا تمام ردیف‌ها تعمیم‌پذیر نیست.
- BusinessPolicyConditionRowID و BusinessPolicyInputRef در ردیف‌ها به‌صورت متوالی/هم‌جهت دیده می‌شوند، ولی این مشاهده الگوریتم تولید شناسه یا رابطه معنایی فراتر از Ref صریح را اثبات نمی‌کند.

**نتیجه و محدودیت:**
- ساختار serialization ورودی و خروجی برای ردیف‌های بررسی‌شده روشن شد: XML در BusinessPolicyInputValue.Value و BusinessPolicyOutputValue.Value ذخیره می‌شود و ورودی دارای ۱۲ جایگاه است.
- این TOP (50) خروجی فقط ردیف‌های قدیمی منقضی را پوشش می‌دهد؛ هیچ ردیف جاری از بازه شناسه‌های جدیدتر در آن دیده نشده است. از این رو هیچ‌یک از InputRef/OutputRef یا مقادیر این ردیف‌ها نباید برای ثبت جدید استفاده شود.
- چون Index و عنوان ردیف‌ها NULL هستند و تعاریف پارامترها در این خروجی حضور ندارند، تعیین نام/نوع رسمی هر جایگاه فقط با XML نمونه قطعی نمی‌شود.
- هنوز باید ردیف‌های جاری و معتبر Condition=163، Associationهای پارامتر، و تطبیق PriceListConditionRow.BusinessPolicyConditionRowRef با ردیف واقعی سیاست بررسی شود. کوئری بعدی باید ردیف‌های جاری/بدون تاریخ پایان یا دارای بازه اعتبار منطبق با زمان جاری را استخراج کند و هم‌زمان تعریف پارامترها را از جداول Association نشان دهد.

**ایمنی:** این بررسی فقط خواندنی بوده است؛ هیچ داده، شناسه یا شماره‌ای ایجاد، رزرو یا تغییر نشده است.

### ۱۱.۳۶. ساختار واقعی جداول تعریف پارامتر و Association سیاست

**منبع:** خروجی کاربر از sys.tables و sys.columns برای دو جدول GNR3.BusinessPolicyParameter و GNR3.BusinessPolicyParameterAssociation.

**ساختار تأییدشده GNR3.BusinessPolicyParameter:**
- BusinessPolicyParameterID از نوع bigint و غیر nullable است.
- مشخصات معنایی پارامتر شامل Name nvarchar(100)، BaseConcept nvarchar(256)، Concept nvarchar(256) و ConceptProperty nvarchar(40) است؛ طول‌ها بر اساس max_length بایتی SQL Server برای nvarchar گزارش شده‌اند.
- EntityGroupingRef از نوع bigint و nullable است.
- AssignmentMethod از نوع int و IsDefaultParameter از نوع bit و هر دو اجباری‌اند.
- اطلاعات تکمیلی/محاسباتی شامل DrillThroughProperty، DrillThroughFieldRef، DrillThroughPrimitiveIndex، DrillThroughPropertyDisplay، IsInternal، IsComputational، ComputationType، GroupingLevel، DrillThroughProviderProperty و ProviderFullName است.
- Version از نوع timestamp/rowversion است و نباید هنگام درج دستی مقداردهی شود.

**ساختار تأییدشده GNR3.BusinessPolicyParameterAssociation:**
- BusinessPolicyParameterAssociationID، BusinessPolicyRef و BusinessPolicyParameterRef از نوع bigint و اجباری‌اند.
- Priority از نوع int و IsDefault از نوع bit و اجباری‌اند.
- Version از نوع timestamp/rowversion است.
- این جدول ارتباط پارامتر با سیاست و ترتیب آن را در Priority نگه می‌دارد؛ بنابراین برای اثبات نگاشت جایگاه‌های XML باید Associationهای خود سیاست ۱۸۵ با اتصال به جدول پارامتر خوانده شوند، نه اینکه نام پارامترها از ترتیب XML حدس زده شود.

**محدودیت داده دریافتی:**
- کاربر فقط نتیجه مجموعه دوم (ساختار ستون‌ها) را ارسال کرده است؛ نتیجه مجموعه اول کوئری قبلی، یعنی ردیف‌های جاری و معتبر شرط ۱۶۳ به همراه XML ورودی/خروجی، هنوز دریافت نشده است.
- هیچ مقدار پارامتر یا Association برای سیاست ۱۸۵ از این خروجی قابل تعیین نیست؛ فقط ساختار جدول‌ها تأیید شده است.

**گام بعدی:** دریافت خروجی مجموعه اول کوئری قبلی؛ سپس Associationهای سیاست ۱۸۵ از طریق BusinessPolicyParameterAssociation به BusinessPolicyParameter متصل و با ترتیب Priority و XML ردیف‌های جاری تطبیق داده می‌شوند.

**ایمنی:** این مرحله صرفاً بررسی ساختار metadata بوده و هیچ داده‌ای در پایگاه داده تغییر نکرده است.


### ۱۱.۳۷. بررسی پیاده‌سازی SYS3.spGetNextId و جست‌وجوی تنظیمات شماره‌گذاری — ۲۰۲۶/۱۱/۱۵

**منبع:** متن کامل Stored Procedure از خروجی کاربر و نتایج دو کوئری Read-only که کاربر در ۲۰۲۶/۱۱/۱۵ اجرا کرد.

#### تعریف و رفتار تأییدشده SYS3.spGetNextId

امضای Procedure:

```sql
CREATE PROCEDURE SYS3.spGetNextId
    @TableName VARCHAR(100),
    @Id BIGINT OUTPUT,
    @IncValue INT = 1,
    @IsLegacy BIT = 0
```

رفتار شاخه غیر Legacy (`@IsLegacy=0`):

1. نام Sequence با پیشوند `SYS3_TableIdGen__` و نام جدول ساخته می‌شود؛ نام Sequence در دستورهای پویا با `QUOTENAME` محصور می‌شود.
2. اگر Sequence موجود نباشد، Procedure با `sp_getapplock` و منبع قفل `TableIdGen_CreateSequence` از ایجاد هم‌زمان جلوگیری می‌کند.
3. مقدار اولیه از `SYS3.TableIdGenBacking.LastId` برای همان `TableName` خوانده می‌شود؛ در نبود مقدار، صفر در نظر گرفته می‌شود. Sequence با شروع از LastId و گام ۱ ساخته می‌شود و سپس یک مقدار با `NEXT VALUE FOR` مصرف می‌شود.
4. اگر `@IncValue=0` باشد، `current_value` از `sys.sequences` خوانده می‌شود و بازه جدید رزرو نمی‌شود؛ اگر مقدار غیرصفر باشد، `sys.sp_sequence_get_range` به اندازه `@IncValue` اجرا می‌شود و مقدار انتهایی بازه به‌عنوان پایه خروجی قرار می‌گیرد.
5. پس از دریافت مقدار پایه، شناسه با مقدار `SYS3.SetupValues` که کلید آن `ReplicationSiteId` است، به قالب ترکیبی تبدیل می‌شود:
   - `IdHigh = Id / 100000000`
   - `IdLow = Id % 100000000`
   - `Id = IdHigh * 100000000000 + ReplicationSiteId * 100000000 + IdLow`
6. اگر `@IsLegacy=1` باشد، Procedure به‌جای Sequence جدید، `GNR.uspGetNextID` را فراخوانی می‌کند.

**نتیجه اجرایی برای طراحی:** تولید شناسه باید از خود `SYS3.spGetNextId` انجام شود و شناسه خروجی آن بدون تبدیل یا بازسازی دستی مصرف شود. برای جدول‌های بررسی‌شده، نام ورودی مطابق نام جدول مانند `SLS3.PriceListItem` است. استفاده از `MAX(ID)+1` یا خواندن مستقیم Sequence به‌عنوان جایگزین Procedure مجاز نیست.

**محدودیت و اثر جانبی:** مقداردهی اولیه Sequence در صورت نبود Sequence، حتی در مسیر `@IncValue=0`، می‌تواند Sequence ایجاد کند و یک مقدار اولیه مصرف کند؛ بنابراین `@IncValue=0` را نباید در همه شرایط read-only فرض کرد. همچنین رزرو Sequence با rollback تراکنش SQL Server بازگردانده نمی‌شود. در نتیجه هیچ فراخوانی رزرو شناسه در مرحله پژوهش یا تست بی‌اثر اجرا نشده است.

#### جست‌وجوی منطق شماره‌گذاری PriceList در ماژول‌های SQL

کوئری جست‌وجوی `sys.sql_modules` با شرط وجود `PriceList` و یکی از واژه‌های `Numbering`، `NumberMethod` یا `77` اجرا شد. خروجی کاربر فقط سرستون‌ها (`SchemaName`، `ObjectName`، `ObjectType`، `ObjectDefinition`) را داشت و هیچ ردیفی برنگرداند.

**برداشت محدود:** با همین شروط متنی، هیچ ماژول SQL منطبق پیدا نشد. این نتیجه اثبات نمی‌کند که منطق شماره‌گذاری وجود ندارد؛ ممکن است در ماژول‌هایی با متن/نام متفاوت، کد برنامه یا لایه دیگری پیاده‌سازی شده باشد.

#### جست‌وجوی SYS3.SetupValues

کوئری جست‌وجوی کلیدهای شامل `Number`، `Sequence` یا `PriceList` از `SYS3.SetupValues` اجرا شد. خروجی کاربر فقط دو سرستون `Key` و `Value` را نشان داد و هیچ ردیفی گزارش نشد.

**برداشت محدود:** در نتیجه ارسالی، هیچ کلید تنظیماتی منطبق با این سه الگوی جست‌وجو مشاهده نشد. این نتیجه وجود تنظیمات شماره‌گذاری در سایر جداول یا لایه‌های برنامه را رد نمی‌کند.

**وضعیت شماره‌گذاری PriceList:** روش ۷۷ و نمونه بازه/رزروهای آن در بخش ۱۱ مستند شده‌اند، اما الگوریتم عملیاتی تخصیص Number به یک PriceList جدید هنوز از خروجی‌های فعلی اثبات نشده است. تا زمان شناسایی مسیر رسمی اجرا یا API/Procedure مسئول، ساخت دستی Number یا درج آزمایشی انجام نشود.

**ایمنی:** هر دو کوئری جست‌وجو فقط خواندنی بودند. هیچ Sequence یا شماره‌ای در این مرحله رزرو یا تغییر داده نشد و هیچ رکورد راهکاران درج/به‌روزرسانی/حذف نشد.


### ۱۱.۳۸. نمونه ردیف‌های جاری سیاست قیمت ۱۸۵ / شرط ۱۶۳ و XML ورودی/خروجی — ۲۰۲۶/۱۱/۱۵

**منبع:** بخش ارسالی کاربر از خروجی کوئری ردیف‌های فعال Policy=185 و Condition=163؛ داده در چند پیام ارسال شده و پیام آخر در میانه یک ردیف/خروجی پایان یافته است. بنابراین این بخش فقط یافته‌های قابل مشاهده از قسمت دریافت‌شده را ثبت می‌کند و به‌معنای دریافت کل نتیجه نیست.

**مشاهدات مستقیم:**
- ردیف‌های جدید با شناسه‌های BusinessPolicyConditionRowID از 2361587 تا حداقل 2361502 در داده ارسالی دیده می‌شوند. برای ردیف‌های نمایش‌داده‌شده BusinessPolicyID=185، BusinessPolicyConditionID=163، ConditionModel=1، ConditionStatus=0، PolicyStatus=1 و ForceToUnload=0 است.
- ردیف‌های تازه‌تر 2361582 تا 2361587 دارای StartDate=2026-11-15 07:59:00 و ردیف‌های بعدیِ مشاهده‌شده عمدتاً دارای تاریخ شروع در 2026-11-14 هستند. در تمام ردیف‌های قابل مشاهده EndDate=NULL است؛ اعتبار نهایی هر ردیف باید در زمان مصرف دوباره با زمان سرور و ورودی دقیق تطبیق داده شود.
- XML ورودی هر ردیف شامل ۱۲ عنصر Input است. در نمونه‌های قابل مشاهده: جایگاه اول ارز با مقدار 1؛ جایگاه دوم شناسه محصول؛ جایگاه سوم null؛ جایگاه چهارم واحد؛ جایگاه پنجم توافق پرداخت؛ و جایگاه‌های ششم تا دوازدهم null هستند. نمونه واحدها شامل 1، 5، 17 و 12 و نمونه توافق‌ها شامل 20، 21، 23 و 30 است.
- XML خروجی هر ردیف شامل یک مقدار Decimal است. در نمونه‌های مرتبط با محصول 25085 و 25628، خروجی‌ها با قیمت‌های نمونه قبلاً مشاهده‌شده در PriceListها هم‌خوان‌اند: محصول 25085 با توافق 20/21 برابر 4,180,000 و با توافق 23 برابر 4,475,000؛ محصول 25628 با توافق 20/21 برابر 4,730,000 و با توافق 23 برابر 5,060,000.
- شناسه‌های InputRef و OutputRef مستقل از شناسه ConditionRow هستند. در خروجی نمونه، یک OutputRef ممکن است در چند ردیف تکرار شود؛ بنابراین یکتایی OutputRef یا رابطه یک‌به‌یک آن با ConditionRow نباید فرض شود.
- در بعضی نمونه‌ها مقدار خروجی برای دو یا چند توافق پرداخت یکسان است؛ این رفتار در داده مشاهده‌شده وجود دارد، اما قاعده عمومی سیاست محسوب نمی‌شود.
- ردیف 2361587 برای محصول 25085، واحد 1 و توافق 23 مقدار 4,475,000 دارد؛ ردیف 2361586 برای محصول 25628، واحد 1 و توافق 23 مقدار 5,060,000 دارد. ردیف‌های 2361585 و 2361584 برای محصول 25085، واحد 1 و توافق‌های 21 و 20 مقدار 4,180,000 دارند؛ ردیف‌های 2361583 و 2361582 برای محصول 25628، واحد 1 و توافق‌های 21 و 20 مقدار 4,730,000 دارند. این‌ها نمونه‌های قابل تطبیق با داده‌های قبلی‌اند، نه شناسه‌هایی برای استفاده ثابت در ثبت‌های آینده.

**نتیجه محدود:**
- نمونه‌های جاری‌تر XML ورودی/خروجی برای شرط ۱۶۳ دریافت شده و قالب ۱۲ جایگاهی ورودی و یک خروجی Decimal در این نمونه‌ها تأیید می‌شود.
- انتخاب BusinessPolicyConditionRowRef باید بر اساس تطبیق دقیق ارز، محصول، واحد و توافق پرداخت با ردیف معتبر سیاست انجام شود؛ انتخاب صرفاً بر اساس جدیدترین ID یا OutputRef نادرست است.
- وجود EndDate=NULL در خروجی به‌تنهایی کافی نیست؛ باید StartDate <= زمان جاری و شرط پایان اعتبار نیز در query زمان اجرا کنترل شود.
- داده‌های دریافتی در انتهای ردیف محصول 27697 و سپس در بخش بعدی خروجی ادامه دارند؛ نتیجه کامل هنوز دریافت نشده است. هیچ نتیجه‌ای درباره پوشش همه محصولات/توافق‌ها یا تعداد کل ردیف‌های فعال نمی‌توان گرفت.
- Associationهای پارامترها هنوز از خروجی واقعی جدول‌های BusinessPolicyParameterAssociation و BusinessPolicyParameter برای Policy=185 تأیید نشده‌اند؛ بنابراین نام رسمی و ترتیب معنایی جایگاه‌های XML همچنان باید با Associationها تطبیق داده شود.

**گام بعدی:** ادامه بخش‌های باقی‌مانده خروجی را دریافت کنیم. پس از اعلام کاربر مبنی بر پایان ارسال، ردیف‌ها را یک‌جا با Associationهای پارامتر و نمونه‌های PriceList تطبیق می‌دهیم. فعلاً کوئری جدیدی لازم نیست.

**ایمنی:** این بررسی صرفاً بر داده‌های ارسالی و خواندنی متکی است؛ هیچ شناسه‌ای رزرو و هیچ رکوردی در راهکاران درج، به‌روزرسانی یا حذف نشده است.


### ۱۱.۳۹. تکمیل دریافت نمونه‌های جاری سیاست قیمت ۱۸۵ / شرط ۱۶۳ — بخش پایانی ارسالی کاربر

کاربر اعلام کرد این پیام بخش پایانی مجموعه خروجی ردیف‌های سیاست ۱۸۵ و شرط ۱۶۳ است. این بخش در ادامه یافته‌های ۱۱.۳۸ ثبت می‌شود.

**نمونه‌های اضافه‌شده در بخش پایانی:**
- محصول 25074، واحد 1: توافق 20 با مبلغ 4,460,000 و توافق 21 با مبلغ 4,460,000؛ توافق 23 با مبلغ 4,460,000 نیز در ردیف 2361503 گزارش شده و توافق 30 با مبلغ 4,915,000 در ردیف 2361502.
- محصول 25299، واحد 1: توافق 20 و 21 هر دو 175,000؛ توافق 23 برابر 185,000؛ توافق 30 برابر 195,000.
- محصول 31532، واحد 1: توافق 20 برابر 6,500,000 و توافق 21 برابر 6,600,000.
- محصول 29752، واحد 1: توافق 20 و 21 هر دو 170,000.
- محصول 32236، واحد 1: توافق 20 و 21 هر دو 110,000.
- محصول 32239، واحد 1: توافق 20 و 21 هر دو 210,000.
- محصول 32238، واحد 1: توافق 20 و 21 هر دو 110,000.
- محصول 30683، واحد 1: توافق 20 برابر 19,800,000؛ توافق 21 برابر 19,800,000؛ توافق 23 برابر 20,790,000.
- محصول 51851، واحد 228 و توافق 21: مبلغ 6,999,000.

**یافته تکمیلی مهم:**
- واحد محصول در همه ردیف‌ها 1 نیست؛ نمونه محصول 51851 با Unit=228 نشان می‌دهد واحد باید از ورودی واقعی همان ردیف سیاست استخراج و با UnitRef مقصد تطبیق داده شود. هرگونه فرض ثابت درباره UnitRef یا تبدیل ProductPriceMonitor.UnitID به ProductUnitRef بدون نگاشت معتبر ممنوع است.
- توافق‌های پرداخت مشاهده‌شده در این بخش شامل 20، 21، 23 و 30 هستند. توافق پرداخت 30 نیز باید مانند سایر توافق‌ها با مرجع معتبر PaymentAgreement در مقصد بررسی شود؛ از روی عدد به‌تنهایی نمی‌توان فعال‌بودن یا مناسب‌بودن آن برای PriceList را نتیجه گرفت.
- مبالغ یکسان برای دو توافق پرداخت در چند محصول مشاهده شده‌اند، اما این صرفاً داده مشاهده‌شده است و نباید به قاعده محاسباتی عمومی تبدیل شود.
- ردیف‌های نمونه همچنان نشان می‌دهند خروجی Decimal در XML با مبلغ Fee سیاست متناظر است؛ برای ثبت نهایی باید مقدار از خروجی معتبر و منطبق با چهار مؤلفه ارز، محصول، واحد و توافق پرداخت استخراج شود.

**وضعیت دریافت داده:**
- کاربر این پیام را «قسمت آخر» اعلام کرده است؛ بنابراین دریافت مجموعه ارسالی فعلی کامل تلقی می‌شود.
- داده‌ها نمونه‌ای از ردیف‌های فعال/بدون EndDate در خروجی ارائه‌شده هستند؛ برای استفاده عملیاتی، اعتبار زمانی و تطبیق دقیق ورودی‌ها باید در زمان اجرای ثبت دوباره کنترل شود.
- این مجموعه به‌تنهایی ساختار رسمی تولید شماره PriceList، نگاشت UnitID به ProductUnitRef، نگاشت شناسه محصول Pricing به ProductRef راهکاران، و مسیر رسمی ایجاد PriceList را اثبات نمی‌کند.

**گام بعدی تحقیق:**
- تطبیق ردیف‌های سیاست با `BusinessPolicyParameterAssociation` برای تثبیت رسمی معنای ۱۲ جایگاه XML.
- اثبات نگاشت ProductCode و UnitID دیتابیس Pricing به ProductRef و ProductUnitRef راهکاران.
- کشف مسیر رسمی برنامه/سرویس برای تولید Number روش شماره‌گذاری 77 و ایجاد PriceList به‌همراه آیتم‌ها، ResultRowها و ConditionRowها.
- تا پیش از اثبات موارد فوق، فقط کوئری‌های SELECT تشخیصی مجازند؛ درج آزمایشی، رزرو Sequence یا هر تغییر داده‌ای انجام نشود.

**ایمنی:** هیچ شناسه‌ای رزرو نشده و هیچ داده‌ای در جداول راهکاران یا Pricing درج، به‌روزرسانی یا حذف نشده است.
