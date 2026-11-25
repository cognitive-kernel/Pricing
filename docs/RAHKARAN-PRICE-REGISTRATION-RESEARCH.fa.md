# مستند تحقیق و آزمون ثبت پیش‌نویس اعلامیه قیمت در راهکاران

> مخزن مصرف‌کننده: `cognitive-kernel/Pricing`  
> مسیر: `docs/RAHKARAN-PRICE-REGISTRATION-RESEARCH.fa.md`  
> تاریخ بازنگری: **۱۴۰۵/۰۹/۰۴ (۲۰۲۶/۱۱/۲۵)**؛ افزودن نتایج SQL تست، لاگ IIS و تحلیل ایستای دو اسمبلی `General.Numbering.API` و `Framework.API`؛ بخش‌های ۱ تا ۲۱ بدون حذف حفظ شده‌اند؛ کشف ارث‌بری کنترلر، اسکن Gateway و تحلیل نهایی API پورتال مشتریان تا بخش ۲۸ افزوده شده است.  
> وضعیت: **ثبت پیش‌نویس دوکالا/دوتوافق با COMMIT در دیتابیس تست تا PriceListID=48202 تأیید شده؛ آزمون شماره خودکار 46665 با ROLLBACK پایان یافته؛ `RahkaranEntityController<T0,T1,T2>.Save/BatchSave` در `Framework.API` شناسایی شده‌اند، تحلیل ایستای API پورتال مشتریان آن را از نامزدهای ثبت اعلامیه کنار گذاشت؛ اما اتصال قابل‌استفاده و پشتیبانی‌شده به `PriceList` و شماره‌گذاری رسمی در محیط عملیاتی هنوز تأیید نشده است.**  
> دامنه قطعی: **فقط ایجاد پیش‌نویس اعلامیه قیمت (`State=1`)؛ بدون تأیید، فعال‌سازی یا ایجاد/خاتمه قوانین قیمت.**

> **راهنمای نسخه:** بخش‌های ۱ تا ۲۱ تاریخچه پژوهش تا ۱۴۰۵/۰۸/۳۰ را حفظ می‌کنند؛ **بخش‌های ۲۲ تا ۲۸ یافته‌های بعدیِ ۱۴۰۵/۰۹/۰۴ و وضعیت فعلی** هستند. در تعارض بین ارزیابی‌های تاریخی و یافته‌های جدیدترِ تأییدشده، بخش‌های ۲۲ تا ۲۸ مقدم‌اند. هیچ آبجکتی در دیتابیس عملیاتی نباید ایجاد، اصلاح یا حذف شود.

> **هشدار استقرار (۱۴۰۵/۰۹/۰۴):** وجود کنترلر عمومی `Save/BatchSave` در `Framework.API`، وجود قرارداد `IPriceListService.SavePriceLists` و مسیر داخلی `BusinessBase<T>.DoNumberAssignment` **به معنی احراز endpoint رسمی ثبت PriceList، تضمین شماره‌دهی، مجوز استفاده بیرونی یا ایمنی هم‌زمانی نیست**. تنها یک دیتابیس تست بازیابی‌شده از بک‌آپ و وب راهکاران عملیاتی در دسترس بوده‌اند؛ روی وب/دیتابیس عملیاتی هیچ آزمون نوشتنی اجرا نشود. SQL شماره‌دهی مبتنی بر `MAX(Number)+1` صرفاً برای تست بک‌آپ است و در محیط اصلی ممنوع است.

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

---

## ۱۶. به‌روزرسانی قطعی آزمون ثبت پیش‌نویس — ۱۴۰۵/۰۸/۲۶ (۲۰۲۶/۱۱/۱۷)

> **این بخش، نتیجه تازه‌تر و معتبرتر آزمون‌های بخش‌های ۸، ۹، ۱۲، ۱۴ و ۱۵ این سند است.** بخش‌های قدیمی برای تاریخچه تحقیق حفظ می‌شوند، اما نباید نتیجه آن‌ها را آخرین وضعیت تلقی کرد. تا تاریخ این به‌روزرسانی، **COMMIT پیش‌نویس خالص یک‌کالا/یک‌توافق روی دیتابیس تست موفق شده است.** هنوز هیچ اثباتی درباره شماره‌گذاری هم‌زمان با رابط کاربری راهکاران، اعتبار همه قواعد تجاری، یا ایمنی ثبت در دیتابیس اصلی وجود ندارد.

### ۱۶.۱. محدودیت قطعی معماری

روی دیتابیس اصلی راهکاران **نباید هیچ آبجکت جدیدی ساخته یا آبجکت موجودی اصلاح/حذف شود**؛ این ممنوعیت شامل Stored Procedure، View، Table، Function، Trigger و تغییر Schema است. عملیات یکپارچه‌سازی باید از برنامه Pricing، با **SQL Batch پارامتری، اتصال و تراکنش واحد** اجرا شود. استفاده از Stored Procedureهای **ازپیش‌موجود** `SYS3.spGetNextId` و `SYS3.spAddNumberingMethodReservation` به معنی ساخت Procedure جدید نیست. اما این دو روال اثر نوشتنی دارند و استفاده عملیاتی از آن‌ها مستلزم مجوز است. کارهای توسعه‌ای فعلی فقط روی **سرور `IT-Soleymani` با دیتابیس بازگردانی‌شده `Rahkaran_sg3`** مجاز است.

### ۱۶.۲. اصلاح مشخصات فیزیکی ستون‌ها

در بخش ۴.۱، `Number` و `Title` به‌ترتیب `nvarchar(100)` و `nvarchar(512)` نوشته شده‌اند. داده متادیتای بررسی‌شده، `sys.columns.max_length` را به‌ترتیب **۱۰۰ بایت** و **۵۱۲ بایت** نشان می‌دهد؛ چون `nvarchar` دو بایت به‌ازای هر واحد UTF-16 می‌گیرد، تعریف واقعی به‌ترتیب **`nvarchar(50)` و `nvarchar(256)`** است. در کوئری جدید از این طول‌های درست استفاده شده است. تبدیل طول بایت به کاراکتر نباید با تعریف `nvarchar` اشتباه شود.

### ۱۶.۳. شواهد شناسه و شماره در دیتابیس تست

Sequenceهای شناسایی‌شده در Schema `dbo`:

| Sequence | مقدار مشاهده‌شده پیش از آزمون‌های اخیر |
|---|---:|
| `SYS3_TableIdGen__SLS3.PriceList` | 48145 |
| `SYS3_TableIdGen__SLS3.PriceListItem` | 2294829 |
| `SYS3_TableIdGen__SLS3.PriceListResultRow` | 79364 |
| `SYS3_TableIdGen__SLS3.PriceListConditionRow` | 2315575 |

همه `increment=1` و `is_cached=1` گزارش شدند. شناسه در مرحله اجرای بعدی با `SYS3.spGetNextId` تولید شد، نه با `MAX(ID)+1`. مصرف Sequence پس از Rollback بازنمی‌گردد. از ۲۰ PriceList آخر در بررسی اولیه، همه رزرو متناظر Range=77 و DomainKey خالی داشتند. شماره `46661` در ابتدا آزاد بود، اما **اکنون در PriceListID=`48147` ثبت و رزرو شده است و نباید دوباره استفاده شود**.

### ۱۶.۴. مقادیر مرجع تأییدشده در تست

- سرور تست: `IT-Soleymani`؛ دیتابیس: `Rahkaran_sg3`.
- کالا: کد `1011160029`، `ProductID=31567`، نام `جاقفلی آکاردئونی بلند`.
- واحد کالا: `ProductUnitRef=12` که برای همین کالا در ۳۲۴ ردیف قبلی `PriceListItem` مشاهده شده است؛ واحد `4` در سه ردیف دیده شد. **این مشاهده به‌تنهایی تأیید رسمی زوج کالا/واحد در همه شرایط نیست.**
- توافق پرداخت `20`: عنوان `نقد کارتکش`، `State=1`، فعال در نمونه بررسی‌شده.
- قیمت نمونه: `420001.000000`؛ واحد ارزِ واقعی/مقیاس پولی باید در زمان اتصال Pricing کنترل شود.
- سربرگ: `PriceListParametersRef=2`، `PriceListType=1`، `SalesDocumentType=2`، `CurrencyRef=1`، `State=1`، `Creator=LastModifier=231`، فیلدهای `EndDate` و `FeeMapperPolicyRef` و `FeePolicyRef` و `ConfirmationDate` برابر NULL.
- `PriceListResultRow` برای هر توافق انتخاب‌شده **یک ردیف مجزا** دارد. نمونه مرجع `48145` دو ردیف `PaymentAgreementRef=20` و `21` داشت.
- `PriceListConditionRow` برای پیش‌نویس خالص باید **صفر ردیف** باشد؛ درج سیاست قیمت و تأیید خارج از دامنه است.

### ۱۶.۵. نتایج واقعی اجرای SQL

| آزمون | نتیجه ثبت‌شده | ماندگاری |
|---|---|---|
| کنترل محیط | `IT-Soleymani` / `Rahkaran_sg3` / `Test environment verified` | فقط خواندنی |
| اعتبارسنجی ورودی | `IsValid=1`، شماره پیشنهادی `46661`، کالا `31567`، واحد `12`، توافق `20` | فقط خواندنی |
| INSERT تراکنشی آزمایشی | `PriceListID=48146`، `ItemID=2294830`، `ResultRowID=79365`، شماره `46661`، `State=1`، سپس `TEST PASSED - ROLLBACK COMPLETED` | بازگردانی |
| بررسی بعد از Rollback | `PriceList`، `PriceListItem`، `PriceListResultRow`، `NumberReservation` و `PriceListConditionRow` همگی `RemainingRows=0` | حذف اثر تراکنشی تأیید شد |
| **INSERT واقعی با COMMIT** | **`PriceListID=48147`، `Number=46661`، `ItemID=2294831`، `ResultRowID=79366`، `ChangesCommitted=1`** | **ماندگار در دیتابیس تست** |
| کنترل بعد از COMMIT | `PriceListID=48147`، شماره `46661`، `State=1`، `ItemCount=1`، `PaymentCount=1` | تأیید شده |

آزمون COMMIT مربوط به عنوان `Pricing - First Committed Draft Test` است. این اولین **ثبت ماندگار پیش‌نویس خالص، با یک کالا و یک توافق پرداخت** است که در این گفتگو خروجی آن تأیید شده است. این نتیجه را نباید به آزمون قدیمی `48143` که قوانین قیمت را تغییر داده بود تعمیم داد.

### ۱۶.۶. شیوه اجرای SQL و معنای تک‌تک مراحل

فایل مستقل `rahkaran_draft_insert.sql`، که باید به همراه این سند در مخزن Pricing نگهداری شود، حاوی اسکریپت **کامنت‌گذاری‌شده** است. این اسکریپت **هیچ `CREATE/ALTER/DROP` ندارد**؛ فقط یک Batch برای اجرای مستقیم از برنامه یا SSMS است. مراحل آن:

1. **ورودی‌ها:** عنوان، شماره تجاری، کد کالا، واحد، مبلغ، شناسه توافق پرداخت، تاریخ، شناسه کاربر و پرچم `@CommitChanges` تنظیم می‌شوند. `@CommitChanges=0` یعنی Rollback پیش‌فرض. نمونه `46662` **صرفاً شماره پیشنهادی است و آزادبودن آن تضمین نشده است**.
2. **محدودیت محیط:** نام سرور و دیتابیس قبل از هر تغییر بررسی می‌شود. تا پیش از بازطراحی و مجوز جداگانه، این محدودیت نباید برای اجرای تولید حذف شود.
3. **اعتبارسنجی:** فیلدهای اجباری، مثبت بودن قیمت، وجود محصول، مشاهده زوج محصول/واحد در PriceListهای قبلی و فعال بودن توافق پرداخت بررسی می‌شود. زوج محصول/واحد و ارز باید برای محیط تولید بر اساس مرجع معتبر/قواعد ERP سخت‌گیرانه‌تر کنترل شوند.
4. **تراکنش و قفل:** `BEGIN TRANSACTION` و `sp_getapplock` فقط درخواست‌های **همین مسیر تست** را هماهنگ می‌کنند و جایگزین هماهنگی با شماره‌گذاری راهکاران نیستند.
5. **بررسی شماره:** وجود شماره در `SLS3.PriceList` و `SYS3.NumberingRangeReservation` کنترل می‌شود. نبود آن در لحظه بررسی تضمین نمی‌کند نرم‌افزار ERP هم‌زمان همان شماره را انتخاب نکند.
6. **شناسه‌های داخلی:** سه بار `SYS3.spGetNextId` فراخوانی می‌شود؛ مصرف Sequence حتی بعد از Rollback ممکن است باقی بماند.
7. **رزرو شماره:** `SYS3.spAddNumberingMethodReservation` با Range=77، DomainKey خالی و شماره پیشنهادی اجرا می‌شود. این روال **الگوریتم یافتن شماره بعدی نیست** و در برخی خطاهای تکراری ممکن است بی‌صدا عمل کند.
8. **INSERT سربرگ:** `SLS3.PriceList` با `State=1` و بدون فیلدهای تأیید یا سیاست قیمت درج می‌شود.
9. **INSERT کالا:** یک ردیف در `SLS3.PriceListItem` با `RowNumber=1`، `Definitiveness=1`، `Quantity=NULL` و دو Tolerance برابر صفر درج می‌شود؛ `Version` از نوع rowversion است و مقداردهی نمی‌شود.
10. **INSERT توافق:** یک ردیف در `SLS3.PriceListResultRow` درج می‌شود. این جدول **با** `SLS3.PriceListConditionRow` فرق دارد؛ جدول دوم نباید تغییر کند.
11. **کنترل پس از درج:** وضعیت سربرگ، تعداد اقلام/توافق‌ها و نبود `PriceListConditionRow` بررسی می‌شود؛ در هر خطا Rollback انجام می‌شود.
12. **پایان:** اگر `@CommitChanges=0` باشد Rollback و اگر ۱ باشد **فقط روی دیتابیس تست** Commit می‌شود؛ پیام خروجی نتیجه را مشخص می‌کند.

### ۱۶.۷. محدوده اثبات و کارهای باقی‌مانده

- [x] INSERT با یک کالا و یک توافق، همراه با کنترل Rollback، آزموده شد.
- [x] صفر بودن پنج جدول/رزرو آزمایشی پس از Rollback تأیید شد.
- [x] INSERT پیش‌نویس یک کالا و یک توافق با `COMMIT` انجام شد؛ `48147/46661` ماندگار است.
- [x] محدودیت **عدم ایجاد/تغییر هرگونه آبجکت روی دیتابیس اصلی راهکاران** قطعی شد.
- [ ] تخصیص شماره تجاری هم‌زمان با UI راهکاران هنوز تأیید نشده است؛ استفاده تولیدی **ممنوع تا تعیین راهکار معتبر**.
- [ ] آزمون درج **چند کالا و چند توافق** انجام نشده؛ اسکریپت همراه فعلاً فقط یک کالا و یک توافق را درج می‌کند.
- [ ] اعتبار رسمی زوج محصول/واحد از `SLS3.ProductUnit` و قواعد آن باید بررسی شود.
- [ ] تطبیق واحد پول، اعتبار `PriceListParametersRef` و شناسه کاربر در محیط مقصد باید کنترل شود.
- [ ] عدم تکرار درخواست با کلید یکتا، audit و مدیریت ابهام پس از قطع ارتباط باید **در خود Pricing** نگهداری شود؛ هیچ جدول کمکی در راهکاران ساخته نمی‌شود.
- [ ] امکان مشاهده و ویرایش رکورد جدید در UI راهکاران هنوز آزمون نشده است؛ دیتابیس بازیابی‌شده فعلاً به UI متصل نیست.
- [ ] درج روی دیتابیس اصلی فقط با مجوز، مسیر تخصیص شماره معتبر و احراز سازگاری عملیاتی مجاز خواهد بود.

### ۱۶.۸. کوئری بررسی ثبت نهایی

```sql
-- این کوئری فقط خواندنی است؛ رکورد ماندگار آزمون را بررسی می‌کند.
USE [Rahkaran_sg3];
GO
SELECT pl.PriceListID, pl.Number, pl.Title, pl.State,
       COUNT(DISTINCT i.PriceListItemID) AS ItemCount,
       COUNT(DISTINCT r.PriceListResultRowID) AS PaymentCount,
       COUNT(DISTINCT c.PriceListConditionRowID) AS ConditionCount
FROM SLS3.PriceList AS pl
LEFT JOIN SLS3.PriceListItem AS i ON i.PriceListRef=pl.PriceListID
LEFT JOIN SLS3.PriceListResultRow AS r ON r.PriceListRef=pl.PriceListID
LEFT JOIN SLS3.PriceListConditionRow AS c ON c.PriceListRef=pl.PriceListID
WHERE pl.PriceListID=48147 AND pl.Number=N'46661'
GROUP BY pl.PriceListID, pl.Number, pl.Title, pl.State;
```

**مرز ایمنی:** این پژوهش در محیط تست موفقیت ساختار INSERT را اثبات می‌کند، نه صحت الگوریتم شماره‌گذاری رسمی راهکاران یا مجوز اجرا روی دیتابیس اصلی.

---

## ۱۷. اسکریپت مستقل INSERT پیش‌نویس (با توضیحات درون کد)

> **به‌روزرسانی ۱۴۰۵/۰۸/۲۷:** شماره نمونه `46662` که در کد تاریخی زیر دیده می‌شود، طبق آزمون بعدی **اکنون در دیتابیس تست ثبت و رزرو شده است** (`PriceListID=48197`). این نمونه نباید بدون تعیین شماره جدید معتبر در **محیط تست** دوباره اجرا شود؛ حتی جایگزینی شماره، روش رسمی شماره‌دهی را اثبات نمی‌کند.

این اسکریپت همان نسخه آزمایشی مستقل برای یک کالا و یک توافق پرداخت است. مقدار `@CommitChanges=0` به‌صورت پیش‌فرض تمام درج‌ها را بازمی‌گرداند. مقدار `1` فقط در سرور تست، تغییرات را ثبت می‌کند. شماره نمونه `46662` صرفاً پیشنهادی است و باید آزادبودن آن بررسی شود. شماره `46661` قبلاً در تست ثبت شده و نباید دوباره استفاده شود. این کد برای محیط عملیاتی بدون تکمیل تخصیص شماره و جلوگیری از درخواست تکراری مجاز نیست.

```sql
/*
  Rahkaran price-list draft INSERT (test-only)
  Verified reference: 2026-11-17, server IT-Soleymani, DB Rahkaran_sg3
  Scope: creates State=1 header, one item and one payment agreement.
  No database objects are created/changed. No price policies are changed.
  Important: this is NOT a production numbering allocator.
*/
USE [Rahkaran_sg3];
GO
SET NOCOUNT ON;
SET XACT_ABORT ON;

/* 0) Input data. 46661 has already been committed and MUST NOT be reused.
      For a new TEST execution, supply an unused number reserved for testing.
      Production integration must use a formally coordinated number allocator. */
DECLARE
    @ExpectedServer sysname = N'IT-Soleymani',
    @PriceListNumber nvarchar(50) = N'46662', -- SAMPLE ONLY: not guaranteed free
    @PriceListTitle nvarchar(256) = N'Pricing - Test Draft 2',
    @ProductCode nvarchar(100) = N'1011160029',
    @ProductUnitRef bigint = 12,
    @PaymentAgreementRef bigint = 20,
    @Fee decimal(28,6) = 420001,
    @StartDate datetime = '2026-11-17T09:00:00',
    @ActorUserID bigint = 231,
    @CommitChanges bit = 0, -- 0 = test and ROLLBACK, 1 = persist on test DB
    @ProductID bigint,
    @PriceListID bigint,
    @PriceListItemID bigint,
    @ResultRowID bigint,
    @Serial bigint,
    @Now datetime,
    @LockResult int,
    @ReservationExists bit = 0;

BEGIN TRY
    /* 1) Hard environment guard, checked before DML. */
    IF CONVERT(nvarchar(128), SERVERPROPERTY('ServerName')) <> @ExpectedServer
       OR DB_NAME() <> N'Rahkaran_sg3'
        THROW 51001, 'Only IT-Soleymani / Rahkaran_sg3 test DB is allowed.', 1;

    /* 2) Validate compulsory inputs and basic business references. */
    SET @Serial = TRY_CONVERT(bigint, @PriceListNumber);
    IF @Serial IS NULL OR @Serial <= 0 OR @PriceListNumber <> CONVERT(nvarchar(50), @Serial)
        THROW 51002, 'PriceListNumber must be a canonical positive integer.', 1;
    IF @PriceListTitle IS NULL OR LTRIM(RTRIM(@PriceListTitle)) = N''
        THROW 51003, 'Title must not be empty.', 1;
    IF @ProductCode IS NULL OR LTRIM(RTRIM(@ProductCode)) = N''
        THROW 51004, 'ProductCode must not be empty.', 1;
    IF @Fee IS NULL OR @Fee <= 0
        THROW 51005, 'Fee must be greater than zero.', 1;
    IF @StartDate IS NULL OR @ActorUserID IS NULL OR @ProductUnitRef IS NULL
       OR @PaymentAgreementRef IS NULL
        THROW 51006, 'A compulsory input is NULL.', 1;
    IF @CommitChanges IS NULL OR @CommitChanges NOT IN (0, 1)
        THROW 51007, 'CommitChanges must be 0 or 1.', 1;

    SELECT @ProductID = ProductID
    FROM SLS3.Product
    WHERE Number = @ProductCode;
    IF @ProductID IS NULL
        THROW 51008, 'ProductCode not found.', 1;

    /* Evidence-based unit guard: UnitRef previously used for this ProductRef.
       Formal ERP product-unit eligibility remains to be validated separately. */
    IF NOT EXISTS
    (
        SELECT 1 FROM SLS3.PriceListItem
        WHERE ProductRef = @ProductID AND ProductUnitRef = @ProductUnitRef
    ) THROW 51009, 'Product/Unit pair not observed in PriceListItem.', 1;

    /* Payment agreement must exist and be active (State=1 observed). */
    IF NOT EXISTS
    (
        SELECT 1 FROM SLS3.PaymentAgreement
        WHERE PaymentAgreementID = @PaymentAgreementRef AND State = 1
    ) THROW 51010, 'Payment agreement not active.', 1;

    /* 3) All writes and existing procedure calls run in one transaction. */
    BEGIN TRANSACTION;

    /* Lock coordinates OUR integration attempts only; it does not coordinate
       Rahkaran's UI and does not make numbering production-safe. */
    EXEC @LockResult = sys.sp_getapplock
        @Resource = N'Pricing_Test_CreatePriceList',
        @LockMode = N'Exclusive',
        @LockOwner = N'Transaction',
        @LockTimeout = 10000;
    IF @LockResult < 0 THROW 51011, 'Cannot acquire test lock.', 1;

    /* 4) Confirm both the business number and the serial reservation are free. */
    IF EXISTS
    (
        SELECT 1 FROM SLS3.PriceList WITH (UPDLOCK, HOLDLOCK)
        WHERE Number = @PriceListNumber
    ) THROW 51012, 'Price list number already exists.', 1;

    IF EXISTS
    (
        SELECT 1 FROM SYS3.NumberingRangeReservation WITH (UPDLOCK, HOLDLOCK)
        WHERE NumberingMethodRangeRef = 77
          AND DomainKey = N''
          AND ReservedSerial = @Serial
    ) THROW 51013, 'Number already reserved.', 1;

    /* 5) Generate IDs with the existing Rahkaran stored procedure.
       These IDs are NOT derived from MAX(ID)+1; sequences do not rollback. */
    EXEC SYS3.spGetNextId @TableName='SLS3.PriceList',
        @Id=@PriceListID OUTPUT;
    EXEC SYS3.spGetNextId @TableName='SLS3.PriceListItem',
        @Id=@PriceListItemID OUTPUT;
    EXEC SYS3.spGetNextId @TableName='SLS3.PriceListResultRow',
        @Id=@ResultRowID OUTPUT;

    IF @PriceListID IS NULL OR @PriceListItemID IS NULL OR @ResultRowID IS NULL
        THROW 51014, 'Rahkaran ID generation failed.', 1;

    /* 6) Reserve the EXTERNALLY selected test serial using Rahkaran's existing
       stored procedure. The procedure does NOT allocate a new serial; it may
       ignore duplicate-key 2601, so we checked availability beforehand.
       Integration safety is limited to isolated test environment. */
    EXEC SYS3.spAddNumberingMethodReservation
        @NumberingMethodRangeID = 77,
        @DomainKey = N'',
        @ReservedValue = @Serial;

    IF NOT EXISTS
    (
        SELECT 1 FROM SYS3.NumberingRangeReservation
        WHERE NumberingMethodRangeRef=77
          AND DomainKey=N'' AND ReservedSerial=@Serial
    ) THROW 51015, 'Number reservation did not persist in transaction.', 1;

    /* 7) Create price-list header; State=1 = draft in observed samples.
       No approval, pricing policy, confirmation date, or end date. */
    SET @Now = GETDATE();
    INSERT INTO SLS3.PriceList
    (
      PriceListID, PriceListParametersRef, Number, Title, StartDate,
      EndDate, PriceListType, SalesDocumentType, CurrencyRef, State,
      CreationDate, LastModificationDate, Creator, LastModifier,
      FeeMapperPolicyRef, FeePolicyRef, ConfirmationDate
    )
    VALUES
    (
      @PriceListID, 2, @PriceListNumber, @PriceListTitle, @StartDate,
      NULL, 1, 2, 1, 1,
      @Now, @Now, @ActorUserID, @ActorUserID,
      NULL, NULL, NULL
    );
    IF @@ROWCOUNT <> 1 THROW 51016, 'PriceList insert count incorrect.', 1;

    /* 8) Create one product-price line; Version is rowversion, so omit it. */
    INSERT INTO SLS3.PriceListItem
    (
      PriceListItemID, PriceListRef, RowNumber, ProductRef,
      ProductUnitRef, CurrencyRef, Fee, Definitiveness,
      Quantity, ReductionTolerance, AdditionTolerance
    )
    VALUES
    (
      @PriceListItemID, @PriceListID, 1, @ProductID,
      @ProductUnitRef, 1, @Fee, 1,
      NULL, 0, 0
    );
    IF @@ROWCOUNT <> 1 THROW 51017, 'PriceListItem insert count incorrect.', 1;

    /* 9) Create one selected payment agreement condition for the draft.
       This is NOT a PriceListConditionRow / BusinessPolicy rule. */
    INSERT INTO SLS3.PriceListResultRow
    (
      PriceListResultRowID, PriceListRef, PaymentAgreementRef
    )
    VALUES
    (
      @ResultRowID, @PriceListID, @PaymentAgreementRef
    );
    IF @@ROWCOUNT <> 1 THROW 51018, 'PriceListResultRow insert count incorrect.', 1;

    /* 10) Validate all three inserted records BEFORE committing. */
    IF NOT EXISTS
    (
        SELECT 1 FROM SLS3.PriceList
        WHERE PriceListID=@PriceListID AND State=1
          AND Number=@PriceListNumber
    ) THROW 51019, 'Draft header verification failed.', 1;

    IF (SELECT COUNT_BIG(*) FROM SLS3.PriceListItem
        WHERE PriceListRef=@PriceListID) <> 1
        THROW 51020, 'Draft item count mismatch.', 1;

    IF (SELECT COUNT_BIG(*) FROM SLS3.PriceListResultRow
        WHERE PriceListRef=@PriceListID) <> 1
        THROW 51021, 'Draft agreement count mismatch.', 1;

    IF EXISTS
    (
        SELECT 1 FROM SLS3.PriceListConditionRow
        WHERE PriceListRef=@PriceListID
    ) THROW 51022, 'Unexpected price policy conditions.', 1;

    /* 11) Return the draft preview while transaction is still open. */
    SELECT
        pl.PriceListID, pl.Number, pl.Title, pl.State,
        i.PriceListItemID, p.Number AS ProductCode,
        p.Name AS ProductName, i.ProductUnitRef, i.Fee,
        r.PriceListResultRowID, r.PaymentAgreementRef
    FROM SLS3.PriceList AS pl
    JOIN SLS3.PriceListItem AS i ON i.PriceListRef=pl.PriceListID
    JOIN SLS3.Product AS p ON p.ProductID=i.ProductRef
    JOIN SLS3.PriceListResultRow AS r ON r.PriceListRef=pl.PriceListID
    WHERE pl.PriceListID=@PriceListID;

    /* 12) By default rollback; on explicit test-only opt-in commit. */
    IF @CommitChanges = 1
    BEGIN
        COMMIT TRANSACTION;
        SELECT N'COMMITTED' AS Result,
            @PriceListID AS PriceListID,
            @PriceListNumber AS PriceListNumber,
            CAST(1 AS bit) AS ChangesCommitted;
    END
    ELSE
    BEGIN
        ROLLBACK TRANSACTION;
        SELECT N'ROLLED_BACK' AS Result,
            @PriceListID AS GeneratedPriceListID,
            @PriceListNumber AS TestedNumber,
            CAST(0 AS bit) AS ChangesCommitted;
    END;
END TRY
BEGIN CATCH
    /* 13) If any step fails, never leave a partially written draft. */
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    SELECT ERROR_NUMBER() AS ErrorNumber,
           ERROR_MESSAGE() AS ErrorMessage,
           ERROR_LINE() AS ErrorLine;
    THROW;
END CATCH;
GO
```

---

## ۱۸. پژوهش جدید شماره‌گذاری در IIS و اسمبلی‌های راهکاران — ۱۴۰۵/۰۸/۲۷ (۲۰۲۶/۱۱/۱۸)

> **وضعیت این بخش:** یافته‌های زیر با بررسی سورس صفحه، خروجی PowerShell سرور، فایل‌های ZIP ارسال‌شده و خواندن مستقیم metadata پنج اسمبلی مرتبط و `SystemGroup.Framework.dll` به دست آمده‌اند. **«یافتن اینترفیس/متد» معادل «آزمودن پیاده‌سازی آن» یا «تأیید API رسمی برای Pricing» نیست.** اسناد و آزمون‌های بخش‌های ۱ تا ۱۷ حفظ شده‌اند تا تاریخچه تحقیق از بین نرود.

### ۱۸.۱. پرسش اصلی و نتیجه فعلی

پرسش باز پروژه این بود: **راهکاران مقدار تجاری `SLS3.PriceList.Number` را هنگام ذخیره چگونه تولید می‌کند و Pricing چگونه می‌تواند بدون تداخل با نرم‌افزار اصلی از همان مکانیزم استفاده کند؟**

خلاصه نتایج:

| گزاره | وضعیت شواهد | توضیح |
|---|---|---|
| شماره اعلامیه در UI فقط **پس از ذخیره** نمایش داده می‌شود | مشاهده کاربر | به‌تنهایی محل تولید (مرورگر/سرور/کش) را ثابت نمی‌کند |
| تنظیمات شماره‌گذاری `PriceList` به روش/Range شماره `77` مرتبط‌اند | مشاهده داده و Trace | برای محیط بررسی‌شده؛ تعمیم به سایر نصب‌ها مجاز نیست |
| `SYS3.spAddNumberingMethodReservation` شماره را **دریافت** می‌کند | مشاهده Trace و امضای SP | پارامتر `@ReservedValue` ورودی است؛ این SP شماره بعدی را محاسبه نمی‌کند |
| `INumberingService`، متد `GenerateNewNumber` دارد | **تأیید قطعی در metadata DLL** | پیاده‌سازی و زمان فراخوانی هنوز استخراج/آزمون نشده است |
| `INumberingService`، متد `HandlePreSaveNumbering` دارد | **تأیید قطعی در metadata DLL** | سازوکار احتمالی پیش از ذخیره؛ نقش دقیق آن باید در پیاده‌سازی بررسی شود |
| `PriceList` اینترفیس `INumberedEntity` را پیاده‌سازی می‌کند | **تأیید قطعی در metadata DLL** | اتصال ساختاری موجودیت اعلامیه به زیرساخت عمومی شماره‌گذاری را نشان می‌دهد |
| فراخوانی مستقیم سرویس شماره‌دهی از PHP امکان‌پذیر است | **تأیید نشده** | شناسایی متدهای .NET به معنی وجود HTTP API پشتیبانی‌شده نیست |
| الگوریتم `MAX(Number)+1` با UI راهکاران هم‌زمان و بی‌تداخل است | **اثبات نشده** | نباید برای تولید مورد استفاده قرار گیرد |

**نتیجه عملی:** به جای تولید شماره با SQL حدسی، مسیر پژوهش باید بر یافتن **پیاده‌سازی واقعی `INumberingService` و قرارداد پشتیبانی‌شده فراخوانی آن** متمرکز باشد.

### ۱۸.۲. معماری نصب شناسایی‌شده روی سرور

آدرس مورد بررسی مربوط به فرم اعلامیه قیمت در پوسته ASP.NET است:

```text
/Shell.aspx?...&page=Sales%2FPriceList%2FPriceListPages%2FEdit.aspx
```

در HTML پوسته، فرم واقعی از طریق iframe بارگذاری می‌شود:

```text
/Sales/PriceList/PriceListPages/Edit.aspx?PageID=...
```

با این حال در نصب بررسی‌شده فایل فیزیکی زیر وجود ندارد:

```text
E:\Systemgroup\Rahkaran\App\WebRoot\Sales\PriceList\PriceListPages\Edit.aspx
```

محتوای یک فایل `Edit.aspx` که جداگانه مشاهده شد، آن را `DUMMY FILE FOR IIS TO START ASP.NET FRAMEWORK` معرفی می‌کرد؛ پس آن نمونه نیز سورس منطق تجاری ثبت نیست. **مسیر URL لزوماً به فایلی هم‌نام در WebRoot اشاره نمی‌کند.**

خروجی `web.config` و IIS:

```text
WebRoot       E:\Systemgroup\Rahkaran\App\WebRoot
ProcessPath   dotnet
Arguments     ..\Rahkaran.dll
HostingModel  inprocess
``` 

در نتیجه نقطه راه‌اندازی برنامه `E:\Systemgroup\Rahkaran\App\Rahkaran.dll` است. تعداد زیادی DLL ماژولار راهکاران در **ریشه پوشه `App`** قرار دارد، نه فقط `WebRoot\bin`. وجود `SystemGroup.Web.Loader.dll` در `WebRoot\bin` به‌تنهایی مشخص نمی‌کند منطق تجاری در آن اجرا می‌شود.

**محدودیت مهم:** از روی این اطلاعات نمی‌توان نتیجه گرفت که شماره حتماً در مرورگر تولید می‌شود؛ همان‌طور که نمی‌توان نتیجه گرفت که یک API عمومی آماده وجود دارد.

### ۱۸.۳. فایل‌های مربوط به قیمت و شماره‌گذاری

مسیر پایه همه فایل‌های جدول، مگر خلاف آن ذکر شود، `E:\Systemgroup\Rahkaran\App\` است.

| فایل | اندازه مشاهده‌شده | نسخه اسمبلی | نقش/شاهد |
|---|---:|---|---|
| `SystemGroup.Framework.dll` | 4,879,360 بایت | `20.0.31.43266` | تعریف `INumberingService` و `INumberedEntity` |
| `SystemGroup.General.Numbering.Common.dll` | 81,920 بایت | `20.0.31.43266` | قراردادهای business مربوط به روش و رزرو شماره |
| `SystemGroup.General.Numbering.Web.dll` | 88,576 بایت | `20.0.31.43266` | صفحات مدیریت روش شماره‌گذاری |
| `SystemGroup.Sales.PriceList.Common.dll` | 282,624 بایت | `20.0.31.43595` | موجودیت `PriceList` و قراردادهای لایه قیمت |
| `SystemGroup.Sales.PriceList.Web.dll` | 447,488 بایت | `20.0.31.43595` | کلاس صفحه و eventهای فرم اعلامیه قیمت |
| `SystemGroup.General.Numbering.Business.bin` | 108,040 بایت | نامعلوم | بسته غیراستاندارد اسمبلی PE معمولی نیست |
| `SystemGroup.General.Numbering.Business.dat` | 175,624 بایت | نامعلوم | بسته غیراستاندارد |
| `SystemGroup.Sales.PriceList.Business.bin` | 560,136 بایت | نامعلوم | بسته غیراستاندارد |
| `SystemGroup.Sales.PriceList.Business.dat` | 519,688 بایت | نامعلوم | بسته غیراستاندارد |

چهار فایل `.bin` و `.dat` بررسی‌شده با بایت‌های `53-47-43-52`، معادل ASCII `SGCR`، آغاز می‌شوند؛ برخلاف فایل‌های DLL استاندارد که امضای PE/MZ دارند. این **نشانه یک قالب بسته‌بندی اختصاصی** است؛ درباره رمزگذاری، فشرده‌سازی یا قابلیت بازیابی مستقیم کد **هنوز نتیجه قطعی وجود ندارد**. از تغییرنام، جایگزین‌کردن یا دستکاری این فایل‌ها در سرور باید خودداری شود.

**ردیابی نمونه‌های باینری فقط برای تطبیق نسخه:**

```text
SystemGroup.Framework.dll
SHA256 665b2ef83bd8c3c71c9189acdf347ca314ad8e21f03dbf7cde8fc21e469ce366

SystemGroup.General.Numbering.Common.dll
SHA256 4a99c86b5556cd814f222190021af504d81aedfed9764042d2b95caedb241d58

SystemGroup.Sales.PriceList.Common.dll
SHA256 0d2e42d41b138a4d1e500872266397cd825bb6e99f55d403430f072508c08923
```

این Hashها شناسه نمونه‌های خوانده‌شده‌اند، نه اعتبارسنجی امضای ناشر.

### ۱۸.۴. یافته قطعی: `INumberingService` در Framework

خواندن جدول metadata نوع و متدها، بدون اجرای اسمبلی روی سرور، وجود این اینترفیس را تأیید کرد:

```csharp
namespace SystemGroup.Framework.Numbering
{
    // بازنمایی امضای استخراج‌شده؛ نه سورس رسمی سازنده.
    public interface INumberingService
    {
        bool HandlePreSaveNumbering(
            SystemGroup.Framework.Common.INumberedEntity entity);

        void GenerateNewNumber(
            SystemGroup.Framework.Common.INumberedEntity entity);

        void ValidateNumber(
            SystemGroup.Framework.Common.INumberedEntity entity);

        void HandleEntityDelete(
            SystemGroup.Framework.Common.INumberedEntity entity);

        AssignApprochs GetEntityNumberAssignApproch(System.Type entityType);

        string[] GetActiveNumberingMethodPatternAttributeNames(
            System.Type entityType);

        void InvalidateCache();
    }
}
```

این قطعه **زیرمجموعه‌ای از متدهای واقعی اینترفیس** است؛ متدهای دیگر آن برای ایجاد روش شماره‌گذاری، دریافت قالب‌ها و ترجمه الگو نیز در metadata وجود دارند. نام عجیب `AssignApproch` عیناً مطابق نام موجود در اسمبلی است، نه اصلاح املایی آن.

امضاهای مرتبط کلیدی:

| متد | خروجی | پارامتر |
|---|---|---|
| `HandlePreSaveNumbering` | `bool` | `INumberedEntity entity` |
| `GenerateNewNumber` | `void` | `INumberedEntity entity` |
| `ValidateNumber` | `void` | `INumberedEntity entity` |
| `GetEntityNumberAssignApproch` | `AssignApprochs` | `Type entityType` |
| `GetActiveNumberingMethodPatternAttributeNames` | `string[]` | `Type entityType` |
| `HandleEntityDelete` | `void` | `INumberedEntity entity` |
| `InvalidateCache` | `void` | بدون پارامتر |

**تفسیر محتاطانه:** نام‌های `HandlePreSaveNumbering` و `GenerateNewNumber` نشان می‌دهند کتابخانه قراردادهایی برای شماره‌گذاری موجودیت‌ها پیش/حین ذخیره دارد. اما این فایل فقط **تعریف اینترفیس** را در اختیار می‌گذارد؛ بنابراین الگوریتم شمارنده، سطح قفل، تراکنش، استثناها، نحوه انتخاب Range و محل پیاده‌سازی مشخص نشده‌اند. چون خروجی `GenerateNewNumber` از نوع `void` است و ورودی موجودیتی است که `Number` دارد، **تغییر دادن خود موجودیت یک احتمال فنی است، نه رفتاری که از metadata به‌تنهایی اثبات شود**.

### ۱۸.۵. یافته قطعی: موجودیت `PriceList` یک `INumberedEntity` است

در `SystemGroup.Sales.PriceList.Common.dll` این نوع شناسایی شد:

```text
SystemGroup.Sales.PriceList.Common.PriceList
  Base: SystemGroup.Framework.Common.Entity
  Implements:
    SystemGroup.Framework.Common.INumberedEntity
    SystemGroup.Framework.Common.ITrackedEntity
    System.ComponentModel.INotifyPropertyChanging
    System.ComponentModel.INotifyPropertyChanged
```

و این امضاها در metadata موجودیت ثبت شده‌اند:

```csharp
string get_Number();
void set_Number(string value);
PriceListState get_State();
void set_State(PriceListState value);
```

خود اینترفیس عمومی نیز در `SystemGroup.Framework.dll` فقط خصوصیت شماره را تعریف می‌کند:

```csharp
namespace SystemGroup.Framework.Common
{
    public interface INumberedEntity
    {
        string Number { get; set; }
    }
}
```

بنابراین پیوند ساختاری میان `PriceList` و سرویس شماره‌گذاری **اثبات شده است**: نوع ورودی `INumberingService.GenerateNewNumber` با موجودیت `PriceList` سازگار است. اما هنوز اثبات نشده که مسیر ایجاد اعلامیه در نسخه نصب‌شده دقیقاً همین متد را فراخوانی می‌کند.

### ۱۸.۶. کلاس وبِ فرم اعلامیه قیمت

از `SystemGroup.Sales.PriceList.Web.dll` نوع زیر استخراج شد:

```text
SystemGroup.Sales.PriceList.Web.PriceListPages.Edit
```

متدهای مرتبط آن در metadata:

```csharp
void OnEntitySaving(object sender, SystemGroup.Web.UI.EntitySavingEventArgs e);
void OnEntityCreated(object sender, SystemGroup.Web.UI.EntityCreatedEventArgs e);
void OnEntityLoaded(object sender, SystemGroup.Web.UI.EntityLoadedEventArgs e);
void OnEntityChanged(object sender, SystemGroup.Web.UI.EntityChangedEventArgs e);
```

متد `OnEntitySaving` دارای بدنه کامپایل‌شده است، ولی **در این مرحله صرفاً وجود، امضا و نشانی metadata آن تأیید شده است**. نقش دقیق هر فراخوانی در بدنه، ترتیب تعامل با `INumberingService` و اجرای ادامه ذخیره در کلاس پایه نیازمند بررسی کنترل‌شده کد/IL است. نباید تنها بر اساس اسم متد، نوع عملیات آن را قطعی تلقی کرد.

در بررسی رشته‌ای اولیه نام `get_NumberingFormats` نیز دیده شد؛ این سرنخ **برای تولید شماره تجاری معتبر نیست** و با `INumberingService.GenerateNewNumber` نباید اشتباه گرفته شود.

### ۱۸.۷. اینترفیس رزرو شماره و تفاوتش با تولید شماره

در `SystemGroup.General.Numbering.Common.dll` این امضاها مشخص شدند:

```csharp
namespace SystemGroup.General.Numbering.Common
{
    public interface INumberingRangeReservationBusiness
    {
        void Save(long numberingMethodRangeRef,
                  string domainKey,
                  long reservedSerial);

        void RemoveEntity(long numberingMethodRangeRef,
                          string domainKey,
                          long reservedSerial);
    }
}
```

همچنین اینترفیس `INumberingMethodBusiness` شامل متدهایی مانند `FetchAllNumberingMethods`، `FetchMethodRangesByNumberMethodRef` و `IsNumberingMethodUsed` است. این‌ها قراردادهای خواندن/مدیریت روش‌اند، نه شواهدی برای الگوریتم شماره بعدی.

در متد `Save`، شماره رزرو (`reservedSerial`) **ورودی** است. این با شواهد SQL مربوط به `SYS3.spAddNumberingMethodReservation` سازگار است؛ بااین‌حال **ارتباط مستقیم بین این دو فراخوانی در بدنه پیاده‌سازی هنوز اثبات نشده است**.

### ۱۸.۸. تطبیق با Trace واقعی ثبت PriceList

در Trace قبلیِ ثبت یک اعلامیه واقعی در محیط بررسی‌شده، نمونه زیر دیده شد:

```text
PriceListID = 48172
PriceList.Number = 46690
NumberingMethodRangeID = 77
DomainKey = N''
ReservedValue = 46690
```

الگوی فراخوانی مشاهده‌شده شامل این مراحل بود:

1. فراخوانی `SYS3.spGetNextId` برای شناسه‌های داخلی `SLS3.PriceList`، `SLS3.PriceListItem` و `SLS3.PriceListResultRow`؛
2. خواندن/اعتبارسنجی برخی پارامترهای `PriceListParameters`؛
3. فراخوانی `SYS3.spAddNumberingMethodReservation` با `@ReservedValue=46690`؛
4. بررسی تکراری نبودن `SLS3.PriceList.Number`؛
5. درج سربرگ در `SLS3.PriceList` و درج اقلام و ردیف‌های نتیجه/توافق پرداخت.

بخش مهمی از Trace:

```sql
EXEC [SYS3].[spAddNumberingMethodReservation]
    @NumberingMethodRangeID = 77,
    @DomainKey = N'',
    @ReservedValue = 46690;
```

**نتیجه مستند:** در بخش استخراج‌شده Trace، شماره تجاری **پیش از فراخوانی رزرو، به‌صورت مقدار آماده در اختیار برنامه قرار داشته است**. هیچ دستور SQL مجزایی که به‌طور قطعی مولد `46690` باشد در همان بخش شناسایی نشده است. این *عدم مشاهده* به معنی اثبات تولید شماره در مرورگر نیست: شماره می‌تواند در لایه میانی، منطق تجاری بسته‌بندی‌شده، حافظه/کش یا در رخدادهای خارج از دامنه Trace تولید شده باشد.

با پیداشدن `INumberingService`، مسیر بررسی جدید **ردیابی فراخوانی‌های `HandlePreSaveNumbering` / `GenerateNewNumber` و پیاده‌سازی آن‌ها** است.

### ۱۸.۹. اصلاح چند برداشت قبلی

- **«`Edit.aspx` را در WebRoot پیدا می‌کنیم و SQL را از متن آن استخراج می‌کنیم»**: در این نصب قابل اتکا نیست؛ URL فرم مجازی است و کد ماژول در اسمبلی‌ها قرار دارد.
- **«همه DLLها در `WebRoot\bin` هستند»**: نادرست برای این نصب؛ DLLهای اصلی در ریشه `App` پیدا شدند.
- **«`ReflectionOnlyLoad` صفر نوع برگرداند، پس اینترفیس وجود ندارد»**: نادرست؛ بارگذاری با خطای وابستگی مواجه شد. استخراج مستقیم metadata مستقل از بارگذاری اجرایی، اینترفیس‌ها را نشان داد.
- **«`get_NumberingFormats` شماره اعلامیه را می‌سازد»**: شواهد کافی ندارد؛ نباید آن را جایگزین سرویس شماره‌گذاری کرد.
- **«`spAddNumberingMethodReservation` شماره جدید تولید می‌کند»**: نادرست؛ رزروِ شماره از پیش تعیین‌شده را ثبت می‌کند.
- **«`MAX(Number)+1` با `sp_getapplock` همان شماره‌گذاری رسمی است»**: نادرست؛ قفل اختصاصی Pricing لزوماً توسط رابط کاربری راهکاران رعایت نمی‌شود.
- **«داشتن `INumberingService` یعنی API قابل‌استفاده مستقیم در PHP داریم»**: اثبات نشده؛ این قرارداد داخلی .NET است و دسترسی، احراز هویت، محدوده تراکنش و پشتیبانی سازنده باید معلوم شود.

### ۱۸.۱۰. وضعیت آخرین تست‌های دیتابیس

علاوه بر نمونه ثبت‌شده در بخش ۱۶ (`PriceListID=48147`، شماره `46661`)، در مرحله بعدی پروژه نتیجه آزمون دیگری روی **دیتابیس تست** گزارش و با کوئری مستقل کنترل شد:

```text
PriceListID          48197
PriceList.Number     46662
State                1
ItemCount            1
PaymentCount         1
ConditionCount       0
NumberReservation    1
```

این نیز **اثبات ساختار ذخیره پیش‌نویس در تست** است؛ نه اثبات تخصیص رسمی شماره و نه مجوز استقرار. برای نمونه `48172/46690` شواهد Trace ثبت **از خود راهکاران** داریم؛ آن را با دو مورد درج آزمایشی از بیرون نرم‌افزار اشتباه نکنیم.

### ۱۸.۱۱. گام‌های فنی بعدی، با رعایت محدودیت‌های عملیاتی

1. **بررسی فقط‌خواندنی کد/IL** در یک کپی مجاز از `SystemGroup.Framework.dll`، `SystemGroup.Sales.PriceList.Web.dll` و اسمبلی‌های وابسته؛ در ILSpy متدهای `OnEntitySaving`، `HandlePreSaveNumbering` و `GenerateNewNumber` و مصرف‌کنندگان آن‌ها را جست‌وجو کنیم. از اجرای فراخوانی روی دیتابیس اصلی و دستکاری فایل‌های `.bin`/`.dat` خودداری شود.
2. **یافتن پیاده‌سازی** `INumberingService` و مکان/روش ثبت سرویس در ساختار Dependency Injection؛ ممکن است در ماژول Business بسته‌بندی‌شده باشد. اگر پیاده‌سازی در فایل‌های در دسترس پیدا نشد، یافته را «ناشناخته» نگه داریم؛ از رمزگشایی یا تغییر قالب اختصاصی بدون مجوز/مسیر رسمی استفاده نکنیم.
3. **شناسایی قرارداد رسمی یکپارچه‌سازی**: API/SDK یا فرایند واردسازی مورد تأیید راهکاران، شامل ورود داده، شماره‌گذاری، اعتبارسنجی، مجوز و خطاها. فراخوانی متد داخلی .NET از بیرون ممکن است به زمینه نشست و سرویس‌های دیگر وابسته باشد.
4. **ردیابی هم‌بسته ذخیره** در محیط تست مجاز: زمان رویداد Save، شماره تخصیص‌یافته، Range، Domain، تغییرات SQL، نقش سرویس شماره‌گذاری، محل BEGIN/COMMIT، وضعیت خطا و Retries. اگر Trace ناقص بود، تنها همان بخش موردنیاز تکمیل شود.
5. **آزمون هم‌زمانی**: در دیتابیس و نصب تست، دو کاربر رابط کاربری و یک درخواست Pricing باید شماره‌های یکتا بگیرند؛ عملکرد در خطای تکرار، لغو، Rollback، قطع ارتباط و ارسال مجدد ثبت شود. هر تست نوشتنی باید مجاز و در محیط تست باشد.
6. **پیاده‌سازی نهایی در Pricing**: کلید یکتای درخواست و idempotency، ثبت نتیجه/خطا در خود Pricing، اعتبارسنجی محصول/واحد/توافق، کنترل وضعیت `State=1` و عدم تغییر وضعیت تأیید؛ بدون ایجاد یا تغییر آبجکت در دیتابیس اصلی راهکاران.

### ۱۸.۱۲. معیار پایان کار برای فعال‌سازی ثبت عملیاتی

تا زمانی که **همه** موارد زیر مستند و در تست تأیید نشده‌اند، قابلیت ثبت مستقیم اعلامیه در دیتابیس عملیاتی نباید فعال شود:

- [x] شناسه داخلی با `SYS3.spGetNextId` از شماره تجاری تفکیک شده است.
- [x] ثبت پیش‌نویس `State=1` در پایگاه تست حداقل برای یک کالا/توافق با COMMIT و بررسی مستقل موفق بوده است.
- [x] `PriceList` به‌عنوان `INumberedEntity` و متدهای `INumberingService` در metadata شناسایی شده‌اند.
- [x] Range شماره‌گذاری مربوط به نمونه مورد بررسی (`77`) و فراخوانی رزرو در Trace شناسایی شده است.
- [ ] پیاده‌سازی واقعی شماره‌دهی و ترتیب فراخوانی هنگام Save به‌طور قابل بازتولید مشخص شده است.
- [ ] مسیر فراخوانی/Import **پشتیبانی‌شده و مجاز** برای Pricing مشخص شده است.
- [ ] هماهنگی شماره‌گذاری در اجرای هم‌زمان UI راهکاران و Pricing در محیط تست تأیید شده است.
- [ ] رفتار Rollback، خطای رزرو، Crash، Timeout و ارسال مجدد مشخص و آزموده شده است.
- [ ] ایجاد پیش‌نویس با چند قلم و چند توافق پرداخت و بازکردن/ویرایش آن در UI تست تأیید شده است.
- [ ] عدم ایجاد/تغییر آبجکت پایگاه اصلی، سطح دسترسی حداقلی و ثبت رخدادها تصویب شده است.

### ۱۸.۱۳. منابع شواهد و قواعد نگهداری مستند

| منبع | استفاده در این پژوهش |
|---|---|
| Trace ثبت اعلامیه راهکاران؛ رویداد `48172/46690` | تمایز شناسه/شماره تجاری، ترتیب رزرو/INSERT |
| `Rahkaran-Modules.txt` و `Numbering-Code-Clues.txt` | محل فایل‌ها، مشخصات بسته‌ها و نام‌های کشف‌شده |
| `Numbering-API-Methods.txt` | ثبت شکست روش `ReflectionOnlyLoad`؛ **نه** شاهد نبود اینترفیس |
| `Rahkaran-Diagnostics.zip` | پنج DLL مرتبط با `PriceList` و `Numbering` برای خواندن مستقیم metadata |
| `Rahkaran-Diagnostics(1).zip` | `SystemGroup.Framework.dll` و گزارش Hash/کپی سالم |
| خروجی تنظیمات IIS و PowerShell | مسیر برنامه، مدل Hosting و فایل‌های ماژولار |
| اسناد/SQL بخش‌های ۱ تا ۱۷ | ساختار و آزمون‌های تاریخی ثبت پیش‌نویس |

**ملاحظات نگهداری:** خود DLLها و بسته‌های اختصاصی را بدون تأیید مالک حقوقی نرم‌افزار وارد مخزن Git نکنید. تنها metadata، روش بازتولید مجاز و نتایج غیرمحرمانه را مستند کنید. اطلاعات ورود، CSRF، Cookie، Authorization، Connection String محرمانه و اطلاعات مشتری نباید در مستند GitHub قرار گیرند.

---

## ۱۹. جمع‌بندی تصمیم فنی در تاریخ ۱۴۰۵/۰۸/۲۷

**۱. بخش ثبت پیش‌نویس:** ساختار INSERT برای یک کالا و یک توافق در تست کار کرده و جداول مورد نیاز شناخته شده‌اند؛ ایجاد قوانین قیمت و تأیید راهکاران در محدوده فعلی نیست.

**۲. بخش شماره‌گذاری:** اکنون نام دقیق قرارداد مرکزی شماره‌گذاری و متدهای اصلی آن معلوم است. `GenerateNewNumber(INumberedEntity)` و `HandlePreSaveNumbering(INumberedEntity)` نقاط بررسی بعدی‌اند، اما مسیر پیاده‌سازی و امکان استفاده از خارج از برنامه هنوز معلوم نیست.

**۳. تصمیم استقرار:** تا روشن‌شدن تخصیص رسمی شماره و اثبات هم‌زمانی با خود راهکاران، **هیچ نسخه‌ای از تخصیص شماره دستی یا `MAX(Number)+1` نباید در محیط تولید فعال شود**؛ حتی اگر روی دیتابیس تست بارها COMMIT موفق داشته باشد.

**۴. تصمیم مستندسازی:** این نسخه جایگزین کامل مستند قبلی است و تمام بخش‌های قدیمی را حفظ می‌کند؛ بخش‌های ۱۸ و ۱۹ حاصل بررسی جدید هستند. هر یافته بعدی باید همراه با نام اسمبلی، نسخه، روش مشاهده، شواهد و وضعیت «تأییدشده/استنباط/نیازمند آزمون» افزوده شود.






## ۲۰. یافته‌های تکمیلی مسیر ذخیره و شماره‌گذاری راهکاران — ۱۴۰۵/۰۸/۳۰ (۲۰۲۶/۱۱/۲۱)

> تاریخ: ۱۴۰۵/۰۸/۳۰ (۲۰۲۶/۱۱/۲۱)  
> مرجع تکمیلی بخش ۱۸ مستند `docs/RAHKARAN-PRICE-REGISTRATION-RESEARCH.fa.md` در مخزن `cognitive-kernel/Pricing`.  
> دامنه: تحلیل **ایستا و فقط‌خواندنی** اسمبلی‌های نصب‌شده، بدون اجرای کد راهکاران، تغییر IIS یا نوشتن در SQL Server.  
> وضعیت: مسیر عمومی اجرای شماره‌گذاری در لایه Business شناسایی شده؛ پیاده‌سازی تخصیص شماره و دسترسی پشتیبانی‌شده از PHP همچنان ناشناخته است.

### ۲۰.۱. فایل‌های مبنا

- `Rahkaran-Diagnostics(2).zip`: شامل `SystemGroup.Web.dll`، به اندازه `38,438,912` بایت؛ SHA256: `B9B954A87787DEF9E71E426D650D17FA4F0BC26632C4435C44CF336CDFB18350`.
- بسته‌های قبلی: `SystemGroup.Framework.dll`، `SystemGroup.Sales.PriceList.Web.dll`، `SystemGroup.Sales.PriceList.Common.dll`.
- روش: خواندن مستقیم جدول‌های metadata (.NET CLI) و توکن‌های فراخوانی در IL، بدون Reflection اجرایی.

### ۲۰.۲. مسیر ذخیره از صفحه وب

در `SystemGroup.Sales.PriceList.Web.dll` کلاس `SystemGroup.Sales.PriceList.Web.PriceListPages.Edit` از `SgEditorPage<PriceList>` مشتق شده است. در `OnEntitySaving` اختصاصی فرم، فراخوانی کلاس پایه نیز دیده می‌شود؛ برای تولید شماره فراخوانی مستقیمی به `GenerateNewNumber` در همین متد مشاهده نشده است.

در `SystemGroup.Web.dll`:

```text
SgEditorPage<T>.SaveEntity
  -> SgEditorPage<T>.DoSaveEntity
  -> EditorExtensions<T>.SaveEntityWithExtensions
  -> EditorExtensions<T>.get_EntityBusiness
       -> IMetaBusinessService.ServiceInterfaceType
       -> ServiceFactory.Create(Type)
  -> [سرویس تجاری شناسایی‌شده در metadata] SaveWithExtensions
```

در `SystemGroup.Sales.PriceList.Common.dll`، قرارداد `IPriceListBusiness` اینترفیس‌های جنریک `IBusinessBase<PriceList>`، `ISaveService<PriceList>` و قراردادهای مرتبط را توسعه می‌دهد. کد concrete business مربوط به PriceList در پنج DLL عمومی استخراج‌شده موجود نیست و در این تحلیل مشاهده نشده است؛ **نمی‌توان با قطعیت گفت در زمان اجرا از کدام override یا پیاده‌سازی استفاده می‌شود**.

### ۲۰.۳. مسیر عمومی شماره‌گذاری در Framework

در `SystemGroup.Framework.dll` کلاس `SystemGroup.Framework.Business.BusinessBase<T>` دارای متدهای زیر است:

```text
BusinessBase<T>.SaveWithExtensions
BusinessBase<T>.Save
BusinessBase<T>.DoSave
BusinessBase<T>.OnSavingRecord
BusinessBase<T>.DoNumberAssignment
```

از توکن‌های فراخوانی IL، مسیر داخل پیاده‌سازی عمومی چنین شناسایی شد:

```text
BusinessBase<T>.Save / SaveWithExtensions
  -> DoSave
     -> Repository.Save(..., OnSavingRecord, ...)
        -> OnSavingRecord(record, ...)
           -> DoNumberAssignment(record)
              -> record is INumberedEntity ?
                 -> ServiceFactory.Create<INumberingService>()
                 -> BeforeNumberingRecord event (اگر مشترک داشته باشد)
                 -> INumberingService.HandlePreNumbering<T>(eventArgs)
                 -> INumberingService.HandlePreSaveNumbering(INumberedEntity)
           -> OnInsertingRecord / OnUpdatingRecord
           -> SavingRecord event
```

**سطح اطمینان:** وجود این فراخوانی‌ها و تقدم `DoNumberAssignment` در `OnSavingRecord` در IL **تأیید شده است**. اجرای عینی این توالی برای `PriceList` در نصب بررسی‌شده، مشروط به استفاده پیاده‌سازی concrete اعلامیه قیمت از همین مسیر عمومی و نبود override مؤثر است؛ این قسمت **نیازمند تأیید runtime در محیط تست** است.

`DoNumberAssignment` فقط وقتی رکورد به `INumberedEntity` تبدیل‌پذیر باشد وارد این شاخه می‌شود. پیاده‌سازی رویداد `BeforeNumberingRecord` و نتیجه `HandlePreNumbering` می‌توانند بر رفتار نهایی اثر بگذارند، بنابراین صرفِ نام این متدها نباید به الگوریتم اختصاص شماره تعبیر شود.

در `BusinessBase<T>.CreateRequiredTransactionScope`، مقدار `IsolationLevel=2` (معادل `ReadCommitted` در `System.Transactions`) به سازنده `SgTransactionScope` ارسال می‌شود. در `DoSave` و `SaveWithExtensions` ساخت/تکمیل scope تراکنشی قابل مشاهده است؛ اما **این موضوع به‌تنهایی یکتایی شماره و سازگاری هم‌زمانی با UI را اثبات نمی‌کند**.

### ۲۰.۴. تمایز UI از سرویس تولید شماره

در `SystemGroup.Web.dll`، `SgNumberingTextBox.AssignReadOnly` متد `INumberingService.GetEntityNumberAssignApproch(Type)` را فراخوانی می‌کند و وضعیت قابل‌ویرایش بودن کنترل را تعیین می‌کند. این فراخوانی، تولید شماره تجاری نیست.

در `SystemGroup.Framework.dll`، `ServiceFactory.Create(Type)` به `ServiceRepository.GetImplementationType` و `CreateServiceInstance` یا مسیر proxy شدن سمت کلاینت وابسته است. در `CreateServiceInstance` بررسی مجوز، ساخت نمونه و اتصال EventEngine دیده شد. این یعنی اینترفیس یک قرارداد داخلی در بستر سرویس‌های راهکاران است؛ **وجود آن معادل API/SDK عمومی یا مجاز برای PHP نیست**.

### ۲۰.۵. وضعیت موارد حل‌نشده

1. کد concrete پیاده‌ساز `INumberingService.HandlePreSaveNumbering` و `GenerateNewNumber` در DLLهای بررسی‌شده یافت نشده است. بسته‌های `SystemGroup.General.Numbering.Business.bin/.dat` با امضای `SGCR` از نوع اسمبلی PE معمولی نیستند؛ بدون مجوز نباید آن‌ها را دستکاری یا بازگشایی کرد.
2. هنوز اثبات نشده `HandlePreSaveNumbering` در ذخیره واقعی PriceList شماره بعدی را محاسبه می‌کند، رزرو می‌کند، یا به متد دیگری ارجاع می‌دهد.
3. هنوز API یا مسیر Import **رسمی، مجاز و پشتیبانی‌شده** برای ایجاد پیش‌نویس PriceList از Pricing شناسایی نشده است.
4. رفتار هم‌زمانی کاربران راهکاران و Pricing، رزرو `SYS3.NumberingRangeReservation`، شکست تراکنش و ارسال مجدد باید در محیط تست سنجیده شوند.

### ۲۰.۶. تصمیم و گام پیشنهادی

**فعلاً از `MAX(Number)+1` در محیط عملیاتی استفاده نشود.** ابتدا از ارائه‌دهنده راهکاران درباره سرویس/SDK پشتیبانی‌شده برای «ایجاد پیش‌نویس اعلامیه قیمت با شماره‌دهی رسمی و بدون تأیید» استعلام شود. اگر بعداً محیط اجرایی آزمایشیِ مجاز راهکاران فراهم شد، در همان محیط یک Save واقعی فرم PriceList را با شواهد Trace کامل ثبت و با مسیر generic بالا تطبیق دهید؛ خصوصاً رزرو شماره، دامنه تراکنش، تغییر رکورد و رفتار خطا/هم‌زمانی. در وضعیت فعلی فقط **دیتابیس آزمایشی** موجود است و این آزمون اجرایی قابل انجام نیست. از اجرای reflection یا سرویس‌های داخلی با اتصال به production برای آزمایش خودداری شود.

> **یادداشت تاریخی:** این بخش ابتدا به‌صورت یادداشت جداگانه تحلیل IL تهیه و سپس با حفظ محتوای آن در سند اصلی ادغام شد. برای نتایج بعدی مربوط به Loader و بسته‌های `SGCR` به بخش ۲۱ رجوع شود.

---

## ۲۱. بررسی بارگذاری ماژول‌های Business و شواهد `Framework.Spl` — ۱۴۰۵/۰۸/۳۰ (۲۰۲۶/۱۱/۲۱)

> **وضعیت این بخش:** حاصل خروجی مستقیم PowerShell ارائه‌شده از سرور نصب‌شده راهکاران است. اسکریپت‌های موفق، فقط نام فایل، مسیر، manifest، سرآیند باینری و رشته‌های قابل‌خواندن را بررسی کرده‌اند؛ **هیچ کد DLL اجرا نشده، هیچ بسته اختصاصی بازگشایی نشده و هیچ تغییر یا اتصال نوشتنی به SQL Server/IIS انجام نشده است.** شواهد زیر جایگزین حدس‌های قبلی درباره Loader نمی‌شوند؛ صرفاً آن‌ها را محدود و دقیق‌تر می‌کنند.

### ۲۱.۱. محدودیت محیط اجرای آزمایش

- **یک نسخه اجرایی/وبِ آزمایشی راهکاران وجود ندارد.** تنها دیتابیس آزمایشی جدا از دیتابیس عملیاتی در دسترس است.
- بنابراین آزمون «ذخیره اعلامیه در UI تست و بررسی Network/Trace همان عملیات» در وضعیت فعلی قابل انجام نیست. **هیچ Save آزمایشی نباید روی نرم‌افزار عملیاتی اجرا شود.**
- آزمون‌های قبلی `INSERT` و `COMMIT` روی **دیتابیس تست** (بخش‌های ۱۶ تا ۱۸) معتبر باقی می‌مانند؛ اما هرگز به‌تنهایی صحت شماره‌دهی رسمی یا هم‌زمانی با UI را ثابت نمی‌کنند.
- تا تعیین روش رسمی/مجاز یکپارچه‌سازی، مسیر تولیدی `Pricing → Rahkaran` برای ایجاد پیش‌نویس باید غیرفعال بماند.

### ۲۱.۲. نتیجه اسکن محدود ارجاع‌های شماره‌گذاری

در خروجی `STATIC NUMBERING REFERENCE SCAN` در تاریخ ۲۰۲۶/۱۱/۲۱، تعداد **۲۱ فایل DLL نامزد** بررسی شد و تنها **۳ فایل** دارای یکی از نشانگرهای جست‌وجوشده بودند:

| فایل | نشانه‌های یافت‌شده | برداشت مجاز |
|---|---|---|
| `SystemGroup.Framework.dll` | `INumberingService`، `HandlePreSaveNumbering`، `GenerateNewNumber`، `ServiceRepository`، `DoNumberAssignment` | تعریف و استفاده عمومی از قرارداد سرویس شماره‌گذاری؛ در بخش ۲۰ با تحلیل IL تکمیل شده است |
| `SystemGroup.General.Numbering.Common.dll` | `INumberingService`، `NumberingRangeReservation` | نوع‌ها/قراردادهای شماره‌گذاری و رزرو |
| `SystemGroup.General.Numbering.Web.dll` | `INumberingService` | ارجاع در ماژول مدیریتی وب |

**محدودیت:** اسکن رشته، صرفاً نام‌هایی را پیدا می‌کند که به‌صورت قابل‌خواندن در فایل موجود باشند. نبود نام در سایر ۱۸ DLL به معنی نبود پیاده‌سازی در کل سامانه نیست. DLLهای بررسی‌شده نیز نماینده تمام فایل‌های نصب نیستند.

### ۲۱.۳. مسیرهای پیکربندی و اجزای پویا

خروجی گزارش `Rahkaran-Assembly-Resolution.txt` نشان داد:

```text
AlternativeBinPaths = ..\Presentation\Bin
DynamicComponents: present
ComponentModel\General: present
Config\machine.config: present
```

در بررسی **دو مسیر احتمالی** زیر، پوشه وجود نداشت:

```text
E:\Systemgroup\Rahkaran\App\Presentation\Bin
E:\Systemgroup\Rahkaran\Presentation\Bin
```

عدم وجود این دو مسیر **اثبات نمی‌کند** تنظیم `AlternativeBinPaths` بی‌اثر است؛ پایه resolve شدن مسیر نسبی در زمان اجرای برنامه هنوز به‌طور کامل بررسی نشده است.

در فهرست مستقیم `E:\Systemgroup\Rahkaran\App\DynamicComponents` تعداد **۱۹ ورودی** ثبت شد؛ DLLهای Business/Common/Web مربوط به `CustomerManagement.v12`، `InvoiceManagement.v51` و `ProductManagement.v10` دیده شدند، اما **فایل مستقیم مربوط به `General.Numbering.Business` یا `Sales.PriceList.Business` در این فهرست نبود**. این مشاهده صرفاً ناظر بر اقلام همان سطح پوشه است و نبود پیاده‌سازی در سایر بخش‌های نصب را ثابت نمی‌کند.

### ۲۱.۴. manifest و بسته‌بندی اختصاصی ماژول‌های Business

گزارش `Rahkaran-Business-Loader-LITE.txt` با `STATUS: COMPLETED` ساختار `.deps.json` این دو ماژول را تأیید کرد:

| Manifest | Target | Runtime asset اعلام‌شده | وابستگی مشترک |
|---|---|---|---|
| `SystemGroup.General.Numbering.Business.deps.json` | `.NETCoreApp,Version=v8.0` | `SystemGroup.General.Numbering.Business.dll` | `SystemGroup.Framework.Spl/20.0.31.43237` |
| `SystemGroup.Sales.PriceList.Business.deps.json` | `.NETCoreApp,Version=v8.0` | `SystemGroup.Sales.PriceList.Business.dll` | `SystemGroup.Framework.Spl/20.0.31.43237` |

برای هر دو ماژول، نسخه متناظر در manifest نیز ثبت شده است:

```text
SystemGroup.General.Numbering.Business/20.0.31.43266
SystemGroup.Sales.PriceList.Business/20.0.31.43595
```

با اینکه فایل‌های manifest نام DLL را به‌عنوان **runtime asset** اعلام می‌کنند، در مسیر اصلی نصب، به‌جای این دو DLL، فایل‌های زیر مشاهده شدند:

```text
SystemGroup.General.Numbering.Business.bin  (108040 bytes)
SystemGroup.General.Numbering.Business.dat  (175624 bytes)
SystemGroup.Sales.PriceList.Business.bin     (560136 bytes)
SystemGroup.Sales.PriceList.Business.dat     (519688 bytes)
```

هر چهار فایل با ۸ بایت یکسان زیر آغاز می‌شوند:

```text
53-47-43-52-01-00-00-00  = ASCII "SGCR" + 01-00-00-00
```

این تفاوت با فرض **بسته‌بندی اختصاصی اسمبلی‌های تجاری** سازگار است؛ اما از header و manifest **نمی‌توان** نتیجه گرفت کدام فایل حاوی کد اجرایی است، قالب دقیق چیست، آیا هر دو فایل مکمل‌اند، یا در چه مرحله‌ای بارگذاری می‌شوند. به‌ویژه قرار گرفتن نام `.dll` در manifest **به معنی موجود بودن فایل فیزیکی DLL در همان مسیر نیست**.

در جست‌وجوی مستقیم سه مسیر زیر، هر دو فایل `Business.dll` موردنظر **وجود نداشتند**:

```text
E:\Systemgroup\Rahkaran\App
E:\Systemgroup\Rahkaran\App\DynamicComponents
E:\Systemgroup\Rahkaran\App\WebRoot\Bin
```

این بررسی recursive نبوده و درباره همه مسیرهای بالقوه یا ماژول‌های بارگذاری‌شده در حافظه حکم قطعی نمی‌دهد.

### ۲۱.۵. شواهد جدید از سه اسمبلی `Framework.Spl`

در گزارش `Rahkaran-Spl-Metadata.txt`، با وضعیت `STATUS: COMPLETED` و بدون اجرای DLL، نتایج زیر به دست آمد:

| اسمبلی | اندازه | نشانه‌های مهمِ مشاهده‌شده در رشته‌ها | ارزیابی |
|---|---:|---|---|
| `SystemGroup.Framework.Spl.dll` | 41,984 بایت | `DecryptedAssemblyInfo`، `ReadProtectedData`، `ReadExtendedProtectedData`، `BCRYPTDECRYPT_FAILED`، `CRYPTENCRYPT_FAILED` | با خدمات داده/اسمبلی محافظت‌شده سازگار؛ نقش دقیق تأیید نشده |
| `SystemGroup.Framework.SplData64.dll` | 376,832 بایت | `CryptDecrypt`، `CryptEncrypt`، `SystemGroup.ApplicationPlatform.Packaging` و نام تعداد زیادی ماژول `.Business` | سرنخ ارتباط با بسته‌های تجاری؛ صرف وجود رشته، دلیل پیاده‌سازی سرویس نیست |
| `SystemGroup.Framework.SplNative64.dll` | 233,472 بایت | `SGCR`، `AssemblyLoader_LoadAssembly_Params`، `Get_AssemblyLoader_LoadAssembly_Params`، `BCryptDecrypt`، `CryptDecrypt`، `SYS3.NoForceAssemblyEncryption` | **نامزد قوی‌تر برای بررسی مسیر بارگذاری SGCR**؛ هنوز هیچ فراخوانی/رابطه اجرایی تأیید نشده است |

**ارزیابی جدید:** پیش از این فقط مشابهت نام `Framework.Spl` در manifest مطرح بود؛ اکنون وجود رشته `SGCR` در `Framework.SplNative64.dll` و هم‌زمان نام‌های مرتبط با بارگذاری Assembly شاهد مستقیمِ قوی‌تری برای **احتمال نقش داشتن این ماژول در پردازش بسته‌های SGCR** است. بااین‌حال، چون فقط رشته‌ها بررسی شده‌اند، **این رابطه هنوز در سطح فرضیه فنی است** و نباید به‌عنوان «کشف قطعی Loader»، «کشف الگوریتم رمزگشایی» یا «بازیابی پیاده‌سازی `INumberingService`» ثبت شود.

فهرست ۷۰ رشته گزارش‌شده برای `SplData64.dll` بخشی از خروجی محدود جست‌وجو است؛ شمارش دقیق همه ماژول‌های موجود یا اثبات وجود ماژول Numbering در آن نیست.

### ۲۱.۶. نقشه ارتباط‌ها و سطح اطمینان

```text
[تأییدشده با IL]
PriceList (INumberedEntity)
    ↓ مسیر عمومی BusinessBase<T>.DoNumberAssignment
INumberingService.HandlePreSaveNumbering(...)
    ↓
[نامعلوم: concrete implementation و زمان/رفتار دقیق اجرا برای PriceList]

[تأییدشده با فهرست فایل و manifest]
General.Numbering.Business.deps.json ── runtime asset: Business.dll
                                       └── dependency: Framework.Spl
General.Numbering.Business.bin/.dat ── header: SGCR
Sales.PriceList.Business.bin/.dat    ── header: SGCR
Framework.SplNative64.dll           ── readable strings: SGCR / AssemblyLoader
    ⇢ [فرضیه، نه فراخوانی اثبات‌شده] استفاده از بسته‌های محافظت‌شده
```

**حد دانش موجود:** هنوز کد concrete متدهای `HandlePreSaveNumbering` و `GenerateNewNumber` در دست نیست؛ سازوکار باز کردن بسته‌ها، انتخاب Range/Domain، شماره بعدی، رزرو تحت تراکنش، برخورد هم‌زمان و رابط رسمی یکپارچه‌سازی مشخص نشده‌اند. حتی اگر یک مکانیزم بارگذاری داخلی پیدا شود، **الزاماً API پایدار یا مجاز برای Pricing فراهم نمی‌کند**.

### ۲۱.۷. تصمیم اجرایی و اولویت ادامه پژوهش

**اکنون ضرورتی برای ادامه اسکن رشته‌ای مشابه یا آزمودن رمزگشایی فایل‌های محافظت‌شده وجود ندارد.** قدم‌های ارزشمند بعدی چنین‌اند:

1. **استعلام رسمی از تأمین‌کننده راهکاران** درباره API/SDK/Import پشتیبانی‌شده برای «ایجاد اعلامیه قیمت فروش فقط در حالت پیش‌نویس (`State=1`) با شماره‌گذاری رسمی، بدون تأیید/فعال‌سازی قیمت». در استعلام باید درباره شماره‌گذاری، احراز هویت/مجوز، ورودی چند کالا و چند توافق، idempotency، تراکنش و خطا/Retry سؤال شود.
2. **بررسی صرفاً خواندنی دیتابیس آزمایشی** برای تکمیل محدودیت‌های `NumberingMethodRange`، `NumberingRangeReservation`، تداخل شماره‌ها و وجود index/constraint؛ این بررسی جایگزین آزمون شماره‌گذاری اپلیکیشن نیست.
3. **محیط تست اجرایی فقط در صورت دسترسی قانونی/عملیاتی**: اگر بعداً نسخه آزمایشی نرم‌افزار تهیه شد، آزمون هم‌بسته Save/Trace و هم‌زمانی را فقط همان‌جا انجام دهیم. در وضعیت فعلی این گزینه **در دسترس نیست**.
4. **Pricing**: تا مشخص شدن مسیر مجاز، قابلیت نوشتن در راهکاران را پشت کنترل feature flag غیرفعال نگه داریم؛ طراحی انتقال، ثبت رخداد، شناسه یکتای درخواست و اعتبارسنجی‌ها می‌تواند بدون عملیات واقعی روی راهکاران ادامه یابد.

**ممنوعیت‌های استقرار:** بدون تأیید مسیر رسمی، از `MAX(Number)+1`، ساخت دستی Reservation، فراخوانی مستقیم DLL داخلی روی production، تغییر فایل‌های `Business.bin/.dat`، دستکاری Loader، ایجاد آبجکت جدید در دیتابیس عملیاتی و اجرای تست نوشتنی روی وب عملیاتی استفاده نشود.

### ۲۱.۸. منابع شواهد و معیارهای تکمیل

| شاهد | تاریخ | نتیجه کلیدی |
|---|---|---|
| `STATIC NUMBERING REFERENCE SCAN` | ۲۰۲۶/۱۱/۲۱ | ۲۱ DLL بررسی، ۳ DLL دارای نشانگرهای جست‌وجوشده |
| `Rahkaran-Assembly-Resolution.txt` | ۲۰۲۶/۱۱/۲۱ | مسیر جایگزین و فهرست ماژول‌های `DynamicComponents` |
| `Rahkaran-Business-Loader-LITE.txt` | ۲۰۲۶/۱۱/۲۱ | `STATUS: COMPLETED`؛ target برابر .NET 8، runtime assets، headerهای SGCR |
| `Rahkaran-Spl-Metadata.txt` | ۲۰۲۶/۱۱/۲۱ | `STATUS: COMPLETED`؛ نام `SGCR` و Loader در SplNative64 |
| بخش‌های ۱۸ تا ۲۰ این مستند | ۲۰۲۶/۱۱/۱۸ تا ۲۰۲۶/۱۱/۲۱ | Trace، metadata و IL مسیر عمومی ذخیره/شماره‌گذاری |

**معیار اتمام این تحقیق برای استقرار:** تا زمانی که مسیر تخصیص شماره و ثبت پیش‌نویس از طریق رابط پشتیبانی‌شده مشخص، در محیط اجرایی آزمایشی مجاز آزموده و با رفتار هم‌زمانی/خطا تأیید نشده است، **ثبت خودکار پیش‌نویس در محیط عملیاتی فعال نمی‌شود**.

### ۲۱.۹. وضعیت مستند و تصمیم نهایی تا این تاریخ

- مستند حاضر نسخه **کامل جایگزین** فایل `docs/RAHKARAN-PRICE-REGISTRATION-RESEARCH.fa.md` است؛ بخش‌های ۱ تا ۱۹ حفظ و یافته‌های یادداشت تحلیل IL به‌عنوان بخش ۲۰ یکپارچه شده‌اند؛ یافته‌های اسکن Loader/Spl بخش ۲۱ را تشکیل می‌دهند.
- اعداد `PriceListID` و `Number` مثال‌های مشاهده/آزمون تاریخی‌اند، نه شماره‌هایی برای استفاده مجدد.
- نتیجه قطعی این مرحله **کشف یک نامزد فنی برای Loader** است؛ نه کشف API رسمی و نه تأیید پیاده‌سازی سرویس.
- تغییرات فقط در همین فایل Markdown است؛ هیچ عملیاتی روی فایل‌های نصب‌شده یا دیتابیس انجام نشده است.


---

## ۲۲. به‌روزرسانی آزمون‌های دیتابیس بازیابی‌شده — ۱۴۰۵/۰۹/۰۴ (۲۰۲۶/۱۱/۲۵)

> وضعیت: **تأییدشده با خروجی SQL ارائه‌شده توسط اپراتور**. سرور `IT-Soleymani` / دیتابیس `Rahkaran_sg3` یک نسخه بازیابی‌شده از بک‌آپ واقعی است؛ محیط وب اجرایی مستقل برای آن وجود ندارد. تاریخ داده‌ها لزوماً با تاریخ عملیاتی امروز برابر نیست.

### ۲۲.۱. تنظیمات شماره‌گذاری و رزرو

| موضوع | مقدار مشاهده‌شده |
|---|---|
| `SYS3.NumberingMethod.NumberingMethodID` | 77 |
| عنوان | `اعلامیه قیمت` |
| `NumberTemplate` | `*سریال` |
| `NumberType` | 1 |
| `NumberDigitsType` | 2 |
| `NumberOfDigits` | 0 |
| `AssignApproch` | 1 |
| `NumberingMethodState` | 1 |
| `CheckUniqueness` | 1 |
| `MissedNumberBehavior` | 0 |
| `NumberingMethodRangeID` | 77؛ **شناسه بازه را از FK تعیین کنید و برابری آن با ID روش را فرض نگیرید** |
| `Priority` / `StartSerial` / `FinishSerial` / `SerialStep` | 1 / 1 / `NULL` / 1 |
| `DomainKey` | رشته خالی |
| شمار رزروها در آن لحظه | 92 |
| کمترین / بیشترین سریال رزروشده | 46570 / 46664 |

برای ستون `SLS3.PriceList.Number` در نمونه بررسی‌شده ایندکس یکتای مستقل مشاهده نشد؛ PK روی `PriceListID` قرار دارد. در مقابل، ایندکس منحصربه‌فرد `UIX_SYS3_NumberingRangeReservation_NumberingMethodRangeRef_DomainKey_ReservedSerial` بر روی سه‌گانه بازه/دامنه/سریال وجود دارد. `CheckUniqueness=1` یک تنظیم نرم‌افزاری است و رفتار اجرایی دقیق آن هنوز شناسایی نشده است. گزارش جست‌وجوی تکراری‌ها دو ردیف با شماره خالی نشان داد؛ این داده‌ها را نباید به معنی شماره تجاری عددی تکراری تعبیر کرد.

`SYS3.spAddNumberingMethodReservation` مقدار `@ReservedValue` را از فراخواننده می‌گیرد؛ **این Procedure شماره بعدی را تولید نمی‌کند.** کنترل concurrency صرفاً با قفل اختصاصی Pricing مثل `sp_getapplock` با UI راهکاران مشترک نیست و ادعای سازگاری با UI مجاز نیست.

### ۲۲.۲. آزمایش چندکالا و چند توافق

| آزمون | وضعیت قطعی |
|---|---|
| ثبت پیش‌نویس دو کالا و دو توافق پرداخت | `PriceListID=48202`، `Number=46664`، `State=1`، `ItemCount=2`، `AgreementCount=2`؛ در دیتابیس تست COMMIT شده |
| رزرو شماره 46664 | در Range=77 / DomainKey خالی موجود است |
| محاسبه و درج آزمایشی شماره 46665 | `PriceListID=48203`، دو کالا و دو توافق در تراکنش مشاهده شد؛ **`@CommitChanges=0` و ROLLBACK**؛ رکورد و رزرو 46665 پس از آن مشاهده نشد |
| آخرین `CreationDate` در بررسی ۲۰۲۶/۱۱/۲۵ | `2026-11-24 16:13:17.197` مربوط به اعلامیه 48202 |

این نتایج **درستی بخشی از ساختار جداول و رفتار آزمایشی SQL** را نشان می‌دهند، نه هم‌زمانی با راهکاران اصلی، نه صحت الگوریتم رسمی شماره بعدی، و نه قابلیت نمایش/تأیید رکورد در یک نمونه وب تست. `StartDate` و `CreationDate` دو مفهوم مستقل هستند؛ تاریخ شروع اعتبار ممکن است پیش از زمان ایجاد رکورد باشد.

## ۲۳. شواهد رابط کاربری و مسیر وب اعلامیه قیمت — ۱۴۰۵/۰۹/۰۴

### ۲۳.۱. IIS و ASP.NET Web Forms

گزارش فقط‌خواندنی از ۱۰ فایل لاگ W3C قدیمی و جدید قابل‌خواندن، `5820` درخواست منطبق را ثبت کرده است. فایل لاگ روز جاری به‌دلیل قفل بودن قابل خواندن نبود؛ عملیات inventory سایت IIS نیز با HRESULT `0x8007000D` شکست خورد، ولی خواندن لاگ‌ها انجام شد.

| درخواست | تعداد گزارش‌شده | تفسیر |
|---|---:|---|
| `POST /Sales/PriceList/PriceListPages/Edit.aspx` | 3293 | Postback فرم؛ همه درخواست‌ها الزاماً ذخیره نیستند |
| `GET /Sales/PriceList/PriceListPages/Edit.aspx` | 525 | بازکردن فرم |
| `GET /Sales/PriceList/PriceListPages/Edit.js` | 10 | تحویل اسکریپت صفحه، نه لزوماً فایل مستقل روی دیسک |
| `GET /Download.ashx/PriceListItems.xlsx` | 41 | دریافت قالب اکسل |
| `POST /Sales/PriceList/PriceListPages/GetPriceListEndDate.aspx` | 2 | درخواست مرتبط با تاریخ پایان |

در Chrome دو نمونه POST بررسی شدند:

1. `__CALLBACKID=fvwPriceList$grdCandidateParameters$ClientTemplates$cbConceptPropertyName` و `Command=LOD`، مربوط به بارگذاری داده کنترل انتخاب.
2. `__ASYNCPOST=true` با `fvwPriceList$btnManualPostBack`، `hidPageData.Command=0` و آرایه‌های تغییرات اقلام/توافق‌ها خالی، یک Partial Postback؛ **نشانه قطعی ذخیره نیست**.

فرم از CSRF، Cookie و وضعیت کنترل‌های Web Forms استفاده می‌کند. **هیچ هدر احراز هویت یا Cookie، توکن، مقدار VIEWSTATE/EVENTVALIDATION و Payload کامل درخواست در مستندات یا GitHub نگهداری نشود.** اطلاعات نشستِ قبلاً منتشرشده باید مطابق رویه امنیتی سازمان باطل شوند. تکرار POST داخلی با PHP جایگزین API رسمی نیست.

### ۲۳.۲. یافته‌های اسمبلی وب و فرم

بر اساس بررسی metadata/IL نمونه‌های `SystemGroup.Sales.PriceList.Web.dll` و `SystemGroup.Sales.PriceList.Common.dll`:

- کلاس `SystemGroup.Sales.PriceList.Web.PriceListPages.Edit` دارای متدهای `OnEntityCreated`، `OnEntityLoaded`، `OnEntitySaving` و `btnManualPostBack_OnClick` است.
- `OnEntitySaving` اعتبارسنجی‌های مرتبط با پارامترهای اعلامیه و ادغام تاریخ/زمان را انجام می‌دهد و به مسیر پایه ذخیره متصل است؛ به‌تنهایی مولد شماره نیست.
- `btnManualPostBack_OnClick` برای تنظیم نمایش/پارامترهای فرم استفاده می‌شود؛ مشاهده نام آن در Network **به معنی اجرای Save نیست**.
- در منابع فرم، کنترل شماره `SgNumberingTextBox` با `NumberedEntity='<%# CurrentEntity %>'` و `Text='<%# Bind("Number") %>'` به موجودیت متصل است.
- قرارداد `void IPriceListService.SavePriceLists(ref List<PriceList> priceLists)` در اسمبلی Common وجود دارد. **تضمین تراکنش اتمیکِ کل لیست، شرایط احراز هویت و امکان فراخوانی بیرونی از روی امضا ثابت نشده‌اند**.

### ۲۳.۳. ساختار فایل‌های نصب‌شده

`Rahkaran-Page-Tree-20261125-125844.zip` از بررسی 26,708 فایل تولید شد؛ 370 فایل مرتبط و 71 تطبیق کلیدواژه گزارش شد. در جست‌وجوی فیزیکی فایل `Edit.aspx` و `Edit.js` با نام و مسیر PriceList پیدا نشد، ولی DLL وب و PDB آن، فایل‌های `.Business.bin/.dat` و manifestهای وابستگی یافت شدند. درخت فایل‌ها با **درخت فراخوانی متدها** متفاوت است. نبود فایل مستقل به‌تنهایی به معنی نبود محتوای صفحه نیست؛ منابع ممکن است embedded یا از route ارائه شوند.

## ۲۴. تحلیل ایستای APIها — یافته‌های ۲۰۲۶/۱۱/۲۵

### ۲۴.۱. `SystemGroup.General.Numbering.API.dll`

| ویژگی | مقدار |
|---|---|
| منشأ | `Gateway/SystemGroup.General.Numbering.API.dll` و مسیرهای Gateway دیگر |
| اندازه | 9,728 بایت |
| نسخه مشاهده‌شده | `20.0.31.43266`؛ هدف .NET 8 |
| SHA-256 فایل بررسی‌شده | `5380334d88f3d6dfc18b4b2788589ca7bbe1f8771a8d4b130569982add10ed8f` |
| روش بررسی | تحلیل ایستای metadata/IL، بدون اجرای DLL |

متدها و رفتارهای بررسی‌شده:

| کلاس/متد | Attribute | عملکرد قابل اثبات |
|---|---|---|
| `NumberingController.EntityNumbering(string entityFullName)` | `HttpGet` | تعیین ویژگی‌های readonly/required شماره در حالت New/Edit، با تکیه بر metadata و روش تخصیص |
| `NumberingController.HasEntityTypeActiveNumbering` | `HttpGet` | بررسی فعال بودن شماره‌گذاری |
| `NumberingMethodController.FetchTreeNodes` | `HttpPost` | دریافت درخت روش شماره‌گذاری |
| `NumberingMethodController.GetAttributeSelectorData` | `HttpPost` | دریافت اطلاعات selector |
| `NumberingMethodController.DefaultEntity` | `HttpGet` | داده موجودیت پیش‌فرض |

بدنه `EntityNumbering` به `INumberingService.GetEntityNumberAssignApproch` رجوع می‌کند، اما **`GenerateNewNumber` و `HandlePreSaveNumbering` را فراخوانی نمی‌کند**. بنابراین این endpoint مشخص، مولد رسمی شماره تجاری نیست؛ آنچه در DLL بررسی شد بیشتر اطلاعات UI و تنظیمات شماره‌گذاری است. نبود این فراخوانی‌ها در متدهای بررسی‌شده، نفی وجود سرویس داخلی/دیگر APIها نیست.

### ۲۴.۲. `SystemGroup.Framework.API.dll` — **یافته جدید و مهم**

فایل جدید دریافت‌شده در `Rahkaran-Framework-API.zip` شامل اسمبلی `SystemGroup.Framework.API.dll` با اندازه 97,792 بایت است؛ SHA-256 آن `e7a1fa567d6f871930583a35b81ac2d7218df68307574d39d8ccf247a83e7903` است. بررسی با پارسر ایستای ECMA-335 بر روی metadata/IL انجام شد؛ DLL load/execute نشد.

در `SystemGroup.Framework.API.RahkaranEntityController<T0,T1,T2>` دو متد عمومی زیر **واقعاً در metadata وجود دارند**:

```csharp
IActionResult Save(EntityWithExtensions<T0> entity);
IActionResult BatchSave(List<T0> entities);
```

همچنین `Get(long id)`، `New()`، `Delete(...)`، `DeleteByID(...)` و متدهای کنترل مجوز از جمله `HasPermission(...)` دیده شدند. بررسی IL نشان می‌دهد:

- `Save` وضعیت اعتبار `ModelState` و مجوز را بررسی می‌کند و به سرویس ذخیره زیرین واگذار می‌کند؛ سپس خطا/پاسخ و ثبت رویداد را مدیریت می‌کند.
- `BatchSave` وضعیت موجودیت‌ها و مجوز را بررسی کرده و فراخوانی ذخیره گروهیِ سرویس زیرین دارد. **از این یافته نه اتمیک‌بودن کل batch و نه تولید شماره برای PriceList اثبات می‌شود.**
- امضای generic controller **نشان نمی‌دهد** که برای `PriceList` حتماً یک controller مشتق‌شده ثبت شده، چه URLی قابل فراخوانی است، چه authentication/authorizationی لازم دارد یا آیا چنین endpointی برای یکپارچه‌سازی خارجی پشتیبانی شده است.
- مسیر عمومی framework با `IPriceListService.SavePriceLists(ref List<PriceList>)` **دو قرارداد متفاوت** هستند. شباهت نام عملیات ذخیره، دلیل یکی بودن آن‌ها نیست.

**نتیجه قابل اتکا:** این اسمبلی وجود سازوکار *generic HTTP-side save/batch-save* را نشان می‌دهد؛ این یافته **مسیر تحقیق را از جست‌وجوی شماره‌دهنده مستقل به بررسی ثبت خود موجودیت با سرویس بومی** تغییر می‌دهد، ولی هنوز یکپارچه‌سازی عملیاتیِ معتبر برای Pricing را اثبات نمی‌کند.

### ۲۴.۳. نقشه ارتباط با سطح اطمینان

```text
[تأییدشده: metadata / IL مستقل]
SystemGroup.Framework.API.RahkaranEntityController<T0,T1,T2>
  ├── Save(EntityWithExtensions<T0>)
  └── BatchSave(List<T0>)
       └── واگذاری به سرویس زیرین (نوع PriceList هنوز تأیید نشده)

[تأییدشده: قرارداد مجزای فروش]
SystemGroup.Sales.PriceList.Common.IPriceListService
  └── SavePriceLists(ref List<PriceList>)

[تأییدشده: مسیر عمومی تجاری]
BusinessBase<T>.OnSavingRecord
  └── DoNumberAssignment
       └── INumberingService.HandlePreSaveNumbering

[تأییدشده: API شماره‌گذاریِ UI]
General.Numbering.API.NumberingController.EntityNumbering
  └── GetEntityNumberAssignApproch
       └── ویژگی‌های فیلد شماره برای New/Edit

[نامعلوم: اتصالات و قرارداد اجرایی]
PriceList-specific API controller / URL / authorization
  ? → save service / SavePriceLists / business hook
  ? → GenerateNewNumber / reservation / transaction
  ? → COMMIT اتمیک، retry، concurrency با UI راهکاران
```

## ۲۵. تصمیم جاری، کارهای باقی‌مانده و معیار پایان پروژه

### ۲۵.۱. موارد بسته‌شده

1. ساختار درج `SLS3.PriceList`، `SLS3.PriceListItem` و `SLS3.PriceListResultRow` در بک‌آپ تست به‌صورت چندکالا/چندتوافق تأیید شده است؛ شرط دامنه **فقط `State=1`** باقی می‌ماند.
2. سازوکار شناسه داخلی `SYS3.spGetNextId` شناخته شده است.
3. تنظیمات روش شماره‌گذاری 77، بازه، رزرو و ایندکس یکتا در بک‌آپ تست مشاهده و ثبت شده‌اند؛ **الگوریتم رسمی انتخاب/رزرو شماره کشف نشده است**.
4. مسیر Web Forms و دو نوع Postback غیرذخیره شناسایی شده‌اند.
5. قرارداد `SavePriceLists`، مسیر عمومی `HandlePreSaveNumbering`، کنترلرهای `General.Numbering.API` و `Framework.API.RahkaranEntityController.Save/BatchSave` در metadata شناسایی شده‌اند.

### ۲۵.۲. موارد باز — با اولویت

| اولویت | پرسش/کار | شاهد لازم برای بستن |
|---|---|---|
| P0 | آیا controller مشتق از `RahkaranEntityController<PriceList,...>` در نصب موجود است؟ | نام DLL، نوع کامل کنترلر، Route/Http verb، امضای contract؛ بررسی ایستای فایل‌ها یا مستند رسمی |
| P0 | آیا API/SDK ثبت `PriceList` توسط تأمین‌کننده پشتیبانی می‌شود؟ | مستند یا پاسخ صریح رسمی، مجوز دسترسی بیرونی، نسخه و قرارداد |
| P0 | آیا عملیات ذخیره از مسیر رسمی، شماره‌گذاری را اجرا می‌کند؟ | شواهد IL معتبر یا آزمایش امن در **نمونه وب آزمایشی**؛ نه وب اصلی |
| P0 | هم‌زمانی با UI و رزرو شماره چگونه تضمین می‌شود؟ | آزمون concurrency و سناریوهای شکست در محیط اجرایی غیرعملیاتی |
| P1 | آیا همه کالاها/توافق‌ها در یک BatchSave تراکنشی‌اند؟ | قرارداد سرویس و تست failure injection مجاز در محیط تست |
| P1 | چگونه retry و idempotency انجام شود؟ | شناسه درخواست، audit و recovery در **دیتابیس Pricing**، همراه با تطبیق نتیجه در راهکاران |
| P1 | آیا همه اعتبارسنجی‌های UI با مسیر API اعمال می‌شوند؟ | تطبیق قواعد کالا/واحد/ارز/توافق/تاریخ و بررسی پیش‌نویس در رابط کاربری تست |

### ۲۵.۳. دستورالعمل مرحله بعد

**قدم بعدی فنی:** با تحلیل ایستای DLLهای مرتبط با `PriceList` در Gatewayها، نوع‌های `PriceListController` و ارث‌بری آن‌ها از `RahkaranEntityController` را جست‌وجو کنیم. در صورت نبودشان، سازوکار ثبت/registration این کنترلرها را بررسی کنیم. صرف وجود `Framework.API.dll` یا نبود فایل با نام `Sales.PriceList.API.dll` در یک فهرست فیلترشده، برای نتیجه‌گیری قطعی کافی نیست.

**قدم بعدی رسمی:** درخواست کتبی مشخص از پشتیبانی همکاران سیستم برای API/SDK پشتیبانی‌شده ایجاد «پیش‌نویس اعلامیه قیمت فروش با چند کالا و چند توافق پرداخت» با شماره‌گذاری بومی، نتیجه تراکنش، idempotency و کنترل مجوز.

**شرط پیش از استقرار:** تا زمان مشخص‌شدن مسیر معتبر ثبت و شماره‌دهی، آزمون concurrency، محافظت در برابر ثبت تکراری و مشاهده نتیجه در وب تست، feature flag ثبت به راهکاران در Pricing خاموش می‌ماند. **هیچ `INSERT/UPDATE/DELETE`، تغییر schema، شبیه‌سازی درخواست Save یا اجرای DLL اختصاصی روی محیط عملیاتی انجام نشود.** بررسی فقط‌خواندنی metadata، manifest، فایل‌ها و لاگ‌های بدون اطلاعات محرمانه مجاز است.

### ۲۵.۴. فهرست شواهد اضافه‌شده در این بازنگری

| منبع | دامنه اثبات |
|---|---|
| خروجی SQL `NumberingMethod/Range/Reservation/Index` و `48202/46664` | ساختار و آزمون بک‌آپ تست |
| خروجی SQL آزمون `48203/46665` با `ROLLED BACK` | اجرای آزمایشی، **نه ثبت ماندگار یا شماره‌دهی رسمی** |
| `Rahkaran-Service-Surface-READONLY.txt` و `Rahkaran-Spl-Metadata.txt` | حضور DLLها/manifestها و سرنخ بارگذاری SGCR |
| گزارش IIS مسیرهای PriceList | الگوی Web Forms/Postback؛ نه API ذخیره |
| `Rahkaran-Page-Tree-20261125-125844.zip` | درخت فایل و manifestها |
| `Rahkaran-PriceList-Numbering-Metadata-20261125.zip` | metadata/IL مسیر PriceList و Framework |
| `Rahkaran-Numbering-API-Only.zip` و `Rahkaran-Numbering-API-Analysis-2026-11-25.fa.md` | `General.Numbering.API` و نبود allocator در endpoint بررسی‌شده |
| `Rahkaran-Framework-API.zip` (۲۰۲۶/۱۱/۲۵) | `RahkaranEntityController.Save/BatchSave` در `Framework.API` |

**قانون مستندسازی:** تمام داده‌های فنی فوق مربوط به نسخه/نمونه مشخص‌شده‌اند؛ گزاره‌های قراردادی و پشتیبانی‌نشده به‌عنوان فرضیه یا «نیازمند آزمون» باقی می‌مانند. فایل‌های DLL اختصاصی، توکن‌ها، Cookieها، session، CSRF و رشته‌های اتصال در GitHub منتشر نشوند.
## ۲۶. کشف ارث‌بری واقعی کنترلر API و آزمون کشف PriceList در Gateway — ۲۰۲۶/۱۱/۲۵

### ۲۶.۱. تصحیح برداشت از کنترلر عمومی

بازبینی مستقیم جدول `TypeDef` در `SystemGroup.Framework.API.dll` تأیید کرد کلاس زیر **abstract** است (`TypeAttributes = 0x00100081`؛ شامل بیت `Abstract = 0x80`):

```csharp
SystemGroup.Framework.API.RahkaranEntityController<T0, T1, T2>
```

در metadata این کلاس، دو متد `Save` و `BatchSave` با attribute `Microsoft.AspNetCore.Mvc.HttpPostAttribute` ثبت شده‌اند:

```csharp
IActionResult Save(EntityWithExtensions<T0> entity);
IActionResult BatchSave(List<T0> entities);
```

**نتیجه محدود:** کلاس abstract مستقیماً Controller نهایی قابل‌نمونه‌سازی نیست؛ برای نتیجه‌گیری درباره API `PriceList` باید کلاس concrete مربوط به آن در Gateway شناسایی شود. وجود `[HttpPost]` روی متد پایه بدون شناخت Route registration، کنترلر concrete، تنظیمات احراز هویت و قرارداد نسخه، **هیچ URL مشخصی را اثبات نمی‌کند**. این موضوع محدودیت مهم برداشت‌های بخش ۲۴ است.

### ۲۶.۲. نمونهٔ واقعی `RahkaranEntityController` در شماره‌گذاری

از جدول `TypeDef` و امضای `TypeSpec` فایل `Gateway/SystemGroup.General.Numbering.API.dll` مشخص شد:

```csharp
SystemGroup.General.Numbering.API.NumberingMethodController
    : SystemGroup.Framework.API.RahkaranEntityController<
        SystemGroup.General.Numbering.Common.NumberingMethod,
        SystemGroup.General.Numbering.Common.NumberingMethodModel,
        SystemGroup.General.Numbering.Common.INumberingMethodFrontendService>
```

این نوع **abstract نیست** (`TypeAttributes = 0x00100001`) و متدهای اختصاصی `FetchTreeNodes`، `GetAttributeSelectorData` و `DefaultEntity` دارد. متدهای اختصاصی بررسی‌شده با `[HttpPost]` یا `[HttpGet]` نشانه‌گذاری شده‌اند. چنین ساختاری نشان می‌دهد الگوی ارث‌بری عمومی در یک ماژول نصب‌شده **واقعاً استفاده شده است**؛ اما صرف ارث‌بری و attributeها، فعال‌بودن route یا اجازهٔ دسترسی بیرونی به `Save/BatchSave` را به‌تنهایی اثبات نمی‌کند.

**نکته دامنه:** `NumberingMethod` موجودیتی برای **تنظیم روش شماره‌گذاری** است، نه موجودیت `Sales.PriceList.PriceList`. وجود `Save` موروثی برای آن به‌معنی ایجاد شماره یا ثبت پیش‌نویس اعلامیه قیمت نیست.

### ۲۶.۳. وضعیت بررسی کنترلر قیمت فروش

در باینری‌های رسیده تا این مرحله، شامل `Framework.API` و `General.Numbering.API`، **کنترلر مشخصِ `PriceList` شناسایی نشده است**. بااین‌حال، این نتیجه به همه DLLهای نصب‌شده تعمیم‌پذیر نیست، چون `Rahkaran-Page-Tree-20261125-125844.zip` فهرست **فیلترشده** دارد و محتویات تمام اسمبلی‌های `.API.dll` در Gateway را برای تحلیل نوع‌ها شامل نمی‌شود.

برای تعیین تکلیف این سؤال ابزار فقط‌خواندنی زیر آماده شد:

`Inspect-Rahkaran-Gateway-PriceList-Controllers-READONLY.ps1` (PowerShell 7) یا `scan_gateway_metadata.py` همراه `inspect_dotnet.py` (Python 3).

ابزار در مسیر اصلی `App` و پوشه‌های مستقیم انتهای نام `Gateway`، تنها فایل‌های `*.API.dll` و `*.API.deps.json` را تحلیل می‌کند و پنج گزارش می‌سازد:

- `01-API-Inventory.csv`: کل فایل‌های `.API.dll` مشمول اسکن؛ برای تعیین دامنه.
- `02-PriceList-TypeReferences.csv`: اسمبلی‌هایی که TypeRef مرتبط با PriceList / PriceAnnouncement دارند.
- `03-Controller-Types.csv`: Controllerهای موجود، BaseType، abstract بودن، و نام متدهای مرتبط.
- `04-Manifest-PriceList-Hits.csv`: manifestهای وابسته به `SystemGroup.Sales.PriceList`.
- `WARNINGS.txt`: خطاهای غیرکشنده برای تعیین اعتبار نتیجه.

**آزمون صحت محلی:** نسخه Python ابزار با دو DLL ارسالی اجرا شد و بدون خطا، `NumberingMethodController` را با ارث‌بری generic دقیق و `RahkaranEntityController` را با `IsAbstract=True` تشخیص داد؛ دامنه آزمون محلی صرفاً همان دو DLL است، نه تمام سرور.

### ۲۶.۴. جدول تصمیم پس از گزارش جدید

| نتیجه مشاهده‌شده در Gateway | تفسیر مجاز | اقدام بعدی |
|---|---|---|
| `PriceListController` با ارث‌بری از `RahkaranEntityController<PriceList,...>` | نامزد جدی Controller ذخیره اعلامیه | بررسی ایستای HTTP attributes، Route/Startup و قرارداد، سپس استعلام پشتیبانی |
| یک DLL بدون نام PriceList ولی دارای TypeRef به `SystemGroup.Sales.PriceList.Common.PriceList` | نامزد غیرمستقیم یا mediator | بررسی کلاس‌های آن؛ نوع مرجع به‌تنهایی endpoint را اثبات نمی‌کند |
| هیچ ارجاعی به PriceList در `.API.dll`های اسکن‌شده | شاهدی علیه وجود Controller مشخص در *این دامنه اسکن* | بررسی registration پویا/ماژول‌های دیگر یا رابط رسمی تأمین‌کننده؛ عدم وجود جهانی اثبات نمی‌شود |
| فقط کنترلرهای عمومی Framework و Numbering یافت شد | همچنان بدون API تأییدشده اعلامیه | **عدم فعال‌سازی Write در Pricing**؛ ادامه استعلام API/SDK رسمی |

### ۲۶.۵. مرز ایمنی و موارد اثبات‌نشده

اسکن **هیچ DLL اختصاصی راهکاران را Load یا اجرا نمی‌کند** و هیچ درخواست HTTP/SQL یا تغییر فایل‌های نصب و داده‌های اصلی ندارد. گزارش فقط نام فایل‌ها، نوع‌ها، متدها و وابستگی‌ها را شامل می‌شود. از آپلود DLLهای اختصاصی در مخزن عمومی GitHub یا ارسال Cookie/Token/connection string خودداری شود.

تا زمان شناسایی کنترلر واقعی، مستندشدن route و احراز هویت، تأیید مجوز مصرف API و آزمون ذخیره/شماره‌دهی با **وب آزمایشی**، وجود متد عمومی `Save/BatchSave` **هرگز مجوز ثبت از Pricing روی production نیست**. دامنه پروژه همچنان صرفاً ایجاد پیش‌نویس `State=1` با چند کالا و چند توافق پرداخت است.

## ۲۷. نتیجه اسکن واقعی APIهای Gateway با PowerShell 5.1 — ۲۰۲۶/۱۱/۲۵

### ۲۷.۱. منبع و دامنه اسکن

منبع این بخش خروجی واقعی کاربر از اجرای `Inspect-Rahkaran-Gateway-PS51-READONLY.ps1` در سرور نصب راهکاران است؛ بسته `Rahkaran-Gateway-PS51-20261125-132653.zip` شامل پنج گزارش اصلی و `WARNINGS.txt` خالی. اسکن فقط فایل‌های `*.API.dll` در زیرپوشه‌های Gateway مشخص‌شده را به‌صورت **اسکن رشته باینری** بررسی کرده و **هیچ DLL را Load یا اجرا نکرده** است. بنابراین نتیجه آن «شاهد نام/عبارت» است، نه `TypeDef`، ارث‌بری کنترلر، route، قرارداد API یا رفتار ذخیره واقعی.

| پوشه | DLLهای API بررسی‌شده |
|---|---:|
| `Gateway` | 174 |
| `EmployeePortalGateway` | 50 |
| `MobileGateway` | 20 |
| `CustomerSalesPortalGateway` | 15 |
| `TransportationGateway` | 12 |
| **جمع** | **271** |

بر اساس `README.txt` و فایل‌های CSV: **271** DLL اسکن‌شده، **17** ردیف نامزد (`02-PriceList-Candidates.csv`؛ بسیاری از این ردیف‌ها تکرار یک Framework/Numbering API در Gatewayهای متفاوت‌اند)، **47** manifest مرتبط، و **صفر** هشدار ثبت‌شده در `WARNINGS.txt`. تمامی 271 ردیف inventory وضعیت `Scanned` دارند.

### ۲۷.۲. تنها وجود رشته PriceList در APIهای بررسی‌شده

از 271 فایل API، **تنها یک فایل** در ستون `ContainsPriceListString` مقدار `True` داشته است:

```text
E:\Systemgroup\Rahkaran\App\CustomerSalesPortalGateway\
    SystemGroup.Sales.CustomerSalesManagementPortal.API.dll
```

- اندازه ثبت‌شده: **409,600 بایت**.
- در ستون `ContainsRahkaranEntityControllerString` برای همین فایل مقدار `False` ثبت شده است؛ **این نتیجه رشته‌ای است و عدم ارث‌بری یا نبود کنترلر generic را به‌طور قطعی اثبات نمی‌کند**.
- از رشته‌های نامزد همان فایل، کنترلرهایی مانند `CustomerSalesPortalPurchaseController`، `CustomerSalesPortalReportController`، `CustomerSalesPortalUserController` و `ProductGroupFilterController` دیده شده‌اند؛ در این اسکن رشته‌ای نام واضح `PriceListController` گزارش نشده است.
- بسته `CustomerSalesPortalGateway/SystemGroup.Sales.CustomerSalesManagementPortal.API.deps.json` نیز از جمله manifestهای دارای ارجاع PriceList است.
- در inventory این دامنه فایل مشخصی با نام `SystemGroup.Sales.PriceList.API.dll` وجود ندارد.

**تفسیر محدود:** این تنها نامزد جدید برای تحلیل metadata دقیق‌تر است، ولی وجود واژه PriceList ممکن است صرفاً ناشی از دسترسی/نمایش قیمت در پورتال مشتریان باشد، نه ایجاد `SLS3.PriceList`. «وجود رشته PriceList» **به معنی وجود endpoint ثبت اعلامیه فروش نیست**.

### ۲۷.۳. نتیجه برای مسیرهای Framework و Numbering

فایل‌های `SystemGroup.Framework.API.dll` و `SystemGroup.General.Numbering.API.dll` در چند Gateway با محتوا/اندازه یکسان ظاهر شده‌اند (اندازه‌های گزارش‌شده به‌ترتیب 97,792 و 9,728 بایت). حضور متن `RahkaranEntityController` در آن‌ها با نتایج IL قبلی سازگار است. این مسئله **وجود کنترلر concrete ویژه `PriceList` را نشان نمی‌دهد**. فایل‌های `SystemGroup.ECM.NumberingManagement.API.dll` نیز به امور شماره‌گذاری مکاتبات مربوط‌اند و نباید با `Sales.PriceList` خلط شوند.

`SystemGroup.Sales.PriceList.Business.deps.json` و `SystemGroup.Sales.PriceList.Web.deps.json` در فهرست manifestهای مرتبط وجود دارند، اما شواهد فعلی همچنان دربارهٔ **سرویس تجاری و صفحه وب** است، نه endpoint مستقل ثبت قیمت.

### ۲۷.۴. تصمیم و اقدام بعدی بدون نصب یا دستکاری سرور

اولویت بعدی بررسی **ایستای metadata و IL** فقط همین فایل محدود است:

- `CustomerSalesPortalGateway/SystemGroup.Sales.CustomerSalesManagementPortal.API.dll`
- در صورت امکان `CustomerSalesPortalGateway/SystemGroup.Sales.CustomerSalesManagementPortal.API.deps.json`

روی سرور صرفاً با PowerShell 5.1 می‌توان از همین فایل‌ها ZIP تهیه کرد؛ این کار کد راهکاران را اجرا نمی‌کند و نیازمند Python یا نصب برنامه‌ای نیست. فایل‌های اختصاصی فقط در صورت مجوز انتقال بررسی شوند و در GitHub عمومی بارگذاری نشوند. در تحلیل آفلاین باید نوع‌های تعریف‌شده، ارث‌بری Controllerها، API attributeها و اشاره واقعی به `SystemGroup.Sales.PriceList.Common.PriceList` بررسی شود؛ پس از آن درباره وجود مسیر ثبت اظهار نظر می‌کنیم.

**حد نتیجه در این مقطع:** هیچ `PriceListController` قابل‌تأیید یا URL رسمی ذخیره اعلامیه قیمت در Gateway اثبات نشده است؛ `Pricing` همچنان نباید روی production عملیات نوشتن/شماره‌دهی مستقیم را فعال کند. ثبت پیش‌نویس با `State=1` روی دیتابیس تست، مجوز فنی یا عملیاتی برای production محسوب نمی‌شود.

## ۲۸. تحلیل نهایی `CustomerSalesManagementPortal.API.dll` با metadata و IL — ۲۰۲۶/۱۱/۲۵

### ۲۸.۱. منبع آزمون و روش بررسی

منبع، ZIP ارسالی `Rahkaran-CustomerSalesPortal-API.zip` است؛ شامل دو فایل `SystemGroup.Sales.CustomerSalesManagementPortal.API.dll` (۴۰۹٬۶۰۰ بایت) و `SystemGroup.Sales.CustomerSalesManagementPortal.API.deps.json` (۶۰٬۶۹۰ بایت). اسمبلی از مسیر `CustomerSalesPortalGateway` سرور کپی شده است. SHA-256 اسمبلی: `e65b82526f050df0c709e396c90e1015bd7525fd626d058c7c3ab960a7014ad1`.

این بررسی صرفاً با تحلیل **ایستای metadata استاندارد .NET و IL** انجام شد؛ DLL بارگذاری یا اجرا نشد، هیچ درخواست HTTP/SQL ارسال نشد و هیچ فایل یا داده‌ای در راهکاران تغییر نکرد. فهرست کلاس‌ها، وراثت آن‌ها، قراردادهای متد، attributeهای HTTP و فراخوانی‌های IL خوانده شد.

### ۲۸.۲. ساختار مشاهده‌شده

| شاخص | نتیجه |
|---|---|
| TypeDef | ۱۰۸ نوع تعریف‌شده (شامل انواع پشتیبان/تولیدشده توسط کامپایلر) |
| MethodDef | ۴۳۵ متد |
| Controllerهای تعریف‌شده با پسوند Controller | ۱۱ |
| Controller اختصاصی `PriceList` | **یافت نشد** |
| Controller مشتق از `RahkaranEntityController<PriceList,...>` | **یافت نشد** |
| TypeRef مستقیم به `SystemGroup.Sales.PriceList.Common.PriceList` | **یافت نشد** |
| AssemblyRef مستقیم به `SystemGroup.Sales.PriceList.Common` در metadata این DLL | **یافت نشد** |

یازده Controller مربوط به کاربردهای پورتال مشتری هستند: `ConstructorFilterController`، `CustomerSalesPortalPurchaseController`، `CustomerSalesPortalUserController`، `CustomerSalesPortalUserPermissionController`، `EPaymentController`، `NewsController`، `PurchaseInfoController`، `PursuitDocumentsController`، `AuthController`، `CustomerSalesPortalReportController` و `ProductGroupFilterController`.

از این تعداد، ده Controller از `SystemGroup.Framework.API.RahkaranController` و `AuthController` از `Microsoft.AspNetCore.Mvc.ControllerBase` ارث می‌برند؛ **هیچ‌کدام از `RahkaranEntityController<T0,T1,T2>` ارث نمی‌برند**. این نتیجه از `TypeDef.Extends` است، نه فقط جست‌وجوی رشته‌ای.

فایل `.deps.json` نام `SystemGroup.Sales.PriceList.Common/20.0.31.43595` و `.Reference` را به‌عنوان وابستگی بسته‌ها فهرست کرده است. **وجود package dependency در manifest، با ارجاع واقعی IL به مدل `PriceList` یا endpoint ثبت برابر نیست**؛ در metadata خود DLL، چنین ارجاع مستقیمی پیدا نشد. این موضوع علت مثبت‌بودن اسکن رشته‌ای بخش ۲۷ را روشن می‌کند.

### ۲۸.۳. متدهای قیمت در پورتال و مسیر فراخوانی واقعی

هر سه متد زیر در `CustomerSalesPortalPurchaseController` قرار دارند:

| متد | Attribute | فراخوانی مشاهده‌شده در IL | نتیجه |
|---|---|---|---|
| `GetListPrices` | `[HttpPost]` | `ICustomerSalesPortalPurchaseFrontendService.GetListPrices` | خواندن قیمت لیستی کالا |
| `GetContractListPrice` | `[HttpPost]` | `ICustomerSalesPortalPurchaseFrontendService.GetContractListPrice` | خواندن قیمت قراردادی کالا |
| `GetShoppingCartItemProductsPrices` | `[HttpGet]` | `ICustomerSalesPortalPurchaseFrontendService.GetShoppingCartItemProductsPrices` | دریافت قیمت اقلام سبد خرید |

در IL دو متد اول، propertyهای `RequestSalesPriceList.get_ProductID` و `RequestSalesPriceList.get_Filter` خوانده می‌شوند. این Request در namespace `SystemGroup.Sales.CustomerSalesManagementPortal.Common` است و **نوع موجودیت `SLS3.PriceList` نیست**. متدهای `GetListPrices` و `GetContractListPrice` نتیجه سرویس مربوطه را به `ControllerBase.Ok(...)` می‌دهند. متد `GetShoppingCartItemProductsPrices` نیز نتیجه سرویس را به `Ok(...)` تحویل می‌دهد.

وجود متدهای نوشتنی مانند `FinalizeSaleRequest` و `SaveOneTimeCustomer` در همان Controller مشاهده شد، اما هر دو مربوط به فرآیند پورتال مشتریان‌اند؛ نه ذخیره سند اعلامیه قیمت. برای مثال `FinalizeSaleRequest` در IL به `ICustomerSalesPortalPurchaseFrontendService.FinalizeSaleRequest` واگذار می‌کند.

### ۲۸.۴. نتیجه و اصلاح جهت تحقیق

**نتیجه تأییدشده برای این DLL:** هیچ شاهدی از یک کنترلر یا endpoint ایجاد/ویرایش `SLS3.PriceList` در `SystemGroup.Sales.CustomerSalesManagementPortal.API.dll` به دست نیامد. متدهای دارای واژه Price/List برای **دریافت قیمت** هستند، نه ذخیره سند اعلامیه قیمت. بنابراین این DLL از نامزدهای جدی مسیر ثبت پیش‌نویس کنار گذاشته می‌شود.

**نتیجه‌ای که نمی‌توان گرفت:** این بررسی عدم وجود یک API اعلامیه قیمت در تمام نسخه‌ها، تمام ماژول‌ها یا محیط‌های راهکاران را اثبات نمی‌کند. همچنین درستی یا پشتیبانی‌شدن فراخوانی مستقیم `IPriceListService.SavePriceLists` از خارج نرم‌افزار را تأیید نمی‌کند.

**گام پیشنهادی با بیشترین ارزش اجرایی:** به‌جای ادامه جست‌وجوی تصادفی DLL، از تأمین‌کننده راهکاران درباره رابط رسمی (API/SDK/Import) برای «ایجاد پیش‌نویس `State=1` با چند کالا، چند توافق پرداخت و شماره‌گذاری رسمی بدون تأیید قیمت» استعلام شود. اگر فقط مسیر داخلی WebForms قابل دسترس باشد، باید آن را به‌عنوان **رابط داخلی و ناپایدار** طبقه‌بندی کرد، نه API مجاز برای Pricing. بررسی بعدیِ بدون نوشتن، در صورت لزوم، tracing فقط‌خواندنی مسیر صفحه `Sales.PriceList.Web` و ثبت route/متدها در گزارش است، نه ارسال POST ذخیره روی وب عملیاتی.

**تصمیم استقرار بدون تغییر:** Write روی دیتابیس/وب عملیاتی راهکاران در Pricing غیرفعال بماند؛ SQL مبتنی بر `MAX(Number)+1` و رزرو دستی شماره فقط ابزار آزمون روی کپی تست است.

