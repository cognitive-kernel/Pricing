# نقشه ساختار و ارتباطات راهکاران
نسخه 2026-11-24؛ ستون‌های مشاهده‌شده، نه فهرست کامل DDL. کلیدها و مسیرها در جامعه آزمون تایید شده‌اند؛ تعمیم نیازمند کنترل است.

## انبار
| جدول | ستون‌های مورد استفاده |
|---|---|
| LGS3.Part | PartID, Code, Name |
| LGS3.Store | StoreID, PlantRef, Name |
| LGS3.InventoryVoucher | InventoryVoucherID, InventoryVoucherSpecificationRef, State, StoreRef, CounterpartStoreRef, Date, Number, CreationDate, LastModificationDate, ReferenceType, ReferenceRef, LedgerRef, FiscalYearRef, CounterpartEntityText |
| LGS3.InventoryVoucherItem | InventoryVoucherItemID, InventoryVoucherRef, ReferenceType, ReferenceRef, ReferenceCode, State, PartRef, PartUnitRef, ReturnableVoucherItemRef, InventoryVoucherSpecificationRef, UnitRef, MajorUnitQuantity, Quantity, SecondUnitQuantity, FiscalYearRef, StoreRef, Date, VoucherCreationDate |
| LGS3.InventoryVoucherSpecification | InventoryVoucherSpecificationID, Code, Name, Direction, IsReturn, Category, State, TypeOfEffectOnStock, PurchaseType, PricingFactor |
| LGS3.ItemPrice | ItemPriceID, InventoryVoucherItemRef, TrackingFactor, CardexRef, Quantity, IsInput, HasReference, IsReturn, VoucherCreationDate, VoucherDate, Fee, Price, PriceType, Date, FeeReferenceCode, FeeReferenceRef, VoucherItemRef, PricingFactorItemPriceRef, SortingDateInCardex, Effect, NetFee, NetPrice |
| LGS3.ReceiptPermitItem | ReceiptPermitItemID, ReceiptPermitRef, StoreRef, ReferenceType, ReferenceRef, PartRef, UnitRef, PartUnitRef, Quantity, MajorUnitQuantity, State, CounterpartEntityRef, MaxMajorUnitQuantity, PrevState |
| LGS3.ReceiptPermit | ReceiptPermitID, Date, PlantRef, StoreRef, CounterpartStoreRef, DelivererRef, ReferenceType, ReferenceRef, State, CounterpartEntityRef, ReceiptVoucherType, BranchRef, CreationDate, LastModificationDate, FiscalYearRef, LedgerRef, ShippingPointRef, CounterpartPlantRef, PrevState |
| LGS3.Reserve | ReserveID, ReferenceType, ReferenceRef |
| LGS3.ReserveItem | ReserveItemID, ReserveRef |
| LGS3.ReserveDelivery | ReserveItemRef, InventoryVoucherItemRef |

### اتصال پایه
vi.InventoryVoucherRef = iv.InventoryVoucherID؛ iv.InventoryVoucherSpecificationRef = vs.InventoryVoucherSpecificationID؛ vi.StoreRef = st.StoreID؛ vi.PartRef = p.PartID.
LedgerRef=1, iv.State<>3, TypeOfEffectOnStock=1, Category<>13 (اختتامیه 99 فقط کنترل), Direction=1 ورودی و 2 خروجی. مقدار MajorUnitQuantity مثبت، علامت از Direction. مرکز از Store.PlantRef. تفکیک کالا/مرکز/انبار و انتقال صریح بین مخازن؛ سیاست تجمیع مخازن باید مشخص باشد.

## خرید
| جدول | ستون‌های مشاهده‌شده |
|---|---|
| PRC3.Invoice | InvoiceID, Number, InvoiceDate, FiscalYearRef, SupplierRef, IssuanceDate, CurrencyRef, OperationalCurrencyExchangeRate, State, Type, Price, Additions, Deductions, NetPrice, RefundableAdditions |
| PRC3.InvoiceItem | InvoiceItemID, InvoiceRef, RowNumber, Price, UnitRef, Fee, Quantity, MajorUnitQuantity, State, PurchaseItemRef, Type, ReferenceType, ReferenceRef, SupplierRef, Additions, RefundableAdditions, Deductions, NetPrice, RefundableTax, CurrencyRef, PurchaseRequestItemRef |
| PRC3.OrderItem | OrderItemID, OrderRef, ReferenceType, ReferenceRef, SupplierRef, PurchaseItemRef, Quantity, MajorUnitQuantity, UnitRef, Fee, Price, AdvancePaymentAmount, CurrencyRef, PurchasingAgentRef, PurchaseRequestItemRef, State, BranchRef, NetPrice, OperationalCurrencyExchangeRateRef, DemandDate, PriceListItemRef |
| PRC3.Order | OrderID, OrderDate, FiscalYearRef, State, CreationDate, LastModificationDate, BranchRef, PurchasingDepartmentRef, SupplierRef, Price, AdvancePaymentAmount, NetPrice, CurrencyRef, ReferenceType, ReferenceRef, OperationalCurrencyExchangeRateRef, ContractRef, DeliveryCenterRef, PaymentSettlementRef, PurchaseAgentRef, AgreementRef |
| PRC3.PurchaseItem | PurchaseItemID, Type, PartRef, ServiceRef |

رسید خرید 2/3 ← PRC3.InvoiceItem.ReferenceType=3 و ReferenceRef=InventoryVoucherItemID؛ InvoiceRef→InvoiceID. Header Type=1, CurrencyRef=1؛ State=3 تایید و 1 پیش‌نویس مشاهده‌شده. کنترل مجموع مقدار اصلی، NetPrice، کالا از PurchaseItemRef، وضعیت و ارز قلم.
رسید vi.ReferenceType=2 و ReferenceRef→ReceiptPermitItemID؛ مجوز از ReceiptPermitRef. مرجع نوع 4 مجوز به موجودیت 742 اشاره مشاهده شده ولی معنای آن تایید نشده؛ Header.ReferenceType=3 را سفارش فرض نکن.
مسیر تاییدشده سفارش: SYS3.RecursiveEntityRelation SourceType=720 (OrderItemID), TargetType=669 (InventoryVoucherItemID). OrderItem.PurchaseItemRef→PurchaseItem.PartRef؛ OrderRef→Order.OrderID. مبلغ واحد = NetPrice/MajorUnitQuantity، نه Fee. تمام رسیدهای مربوط به سفارش را برای کنترل بقای مقدار لحاظ کن. SourceType 727 درخواست خرید، 637 قلم مجوز، 742 نامشخص.

## فروش
| جدول | ستون‌های مشاهده‌شده |
|---|---|
| SLS3.Invoice | InvoiceID, Number, Date, CurrencyRef, OperationalCurrencyExchangeRate, Status, PlantRef, Price, AdditionAmount, ReductionAmount, EffectiveNetPrice, NetPrice, PaymentAgreementRef, FiscalYearRef |
| SLS3.InvoiceItem | InvoiceItemID, InvoiceRef, Type, ReferenceType, ReferenceRef, Date, ProductRef, ProductUnitRef, PriceBaseUnitRef, PriceBaseFee, Quantity, MajorUnitQuantity, MajorUnitInitialQuantity, InitialQuantity, Fee, Price, EffectiveNetPrice, Status, ReductionAmount, AdditionAmount, PlantRef, InventoryRef, NetPrice, CanceledBy, CanceledDate |
| SLS3.Product | ProductID, Number, Name, PartRef |
| SLS3.ReturnInvoice | ReturnInvoiceID, Number, Date, Status, PlantRef, CurrencyRef, Price, EffectiveNetPrice, NetPrice, FiscalYearRef |
| SLS3.ReturnInvoiceItem | ReturnInvoiceItemID, ReturnInvoiceRef, Type, ReferenceType, ReferenceRef, Date, ProductRef, ProductUnitRef, Quantity, MajorUnitQuantity, Fee, Price, EffectiveNetPrice, Status, ReductionAmount, AdditionAmount, NetPrice, CanceledBy, CanceledDate, IsWaste |
| SLS3.OrderItem | OrderItemID, OrderRef, ProductRef |
| DSD3.AssignmentDeliveryItem | AssignmentRef, OrderRef |
| DSD3.Assignment | AssignmentID |

InvoiceItem.InvoiceRef→Invoice.InvoiceID؛ ReturnInvoiceItem.ReturnInvoiceRef→ReturnInvoice.ReturnInvoiceID. ProductRef→ProductID→PartRef (نه کد متنی). Header/Item Status=11، CanceledBy/CanceledDate IS NULL، CurrencyRef=1، MajorUnitQuantity>0، EffectiveNetPrice>=0. EffectiveNetPrice مبلغ کل قلم است. ReturnInvoiceItem فاقد PlantRef است؛ از Header استفاده شود. تاریخ مالی Header.Date؛ تاریخ فیزیکی vi.Date.

## Entity relation graph
SYS3.EntityRelation و SYS3.RecursiveEntityRelation: SourceType, SourceRef, TargetType, TargetRef. جهت مهم؛ DISTINCT جفت شناسه قبل از SUM. روابط بازگشتی ممکن است چند مسیر داشته باشند.

| مسیر | کاربرد |
|---|---|
| Recursive 669→906 | حواله فروش→قلم فاکتور |
| EntityRelation 669→914 | رسید برگشت فروش→قلم برگشت مالی |
| Recursive 906→914 | فروش اولیه→برگشت مالی |
| Recursive 935→906 | قلم سفارش فروش→فاکتور |
| Recursive 720→669 | قلم سفارش خرید→رسید |
| EntityRelation 669→669 | رسید موقت فنی→رسید دائم فنی |

### فروش پخش
برای 7575 قلم فاقد رابطه مستقیم 669→906، InvoiceItem.ReferenceType=4 و ReferenceRef=OrderItemID با ProductRef برابر، 100% تایید. مسیر:
InvoiceItem → OrderItem → OrderRef = AssignmentDeliveryItem.OrderRef → AssignmentRef = AssignmentID → Reserve.ReferenceType=1036, ReferenceRef=AssignmentID → ReserveItem.ReserveRef → ReserveDelivery.ReserveItemRef → InventoryVoucherItemRef → حواله 10005.
کنترل کالا از PartRef، مرکز، وضعیت، تاریخ، مقدار، تکرار جفت‌ها. اتصال در سطح OrderID/AssignmentID فقط کاندید است؛ ممکن است چند مشتری/فاکتور/حواله برای یک کالا ایجاد کند. برگشت پخش 10012 با ReturnableVoucherItemRef = حواله 10005. مجموع خالص حواله = مقدار ناخالص منهای برگشت‌های معتبر.
### فنی
رسید 10008 ReferenceType=1, ReferenceRef=رسید موقت 10007؛ رابطه EntityRelation 669→669 تایید. خروج 10010 ReturnableVoucherItemRef=رسید 10008. 10007 گردش موقت و فاقد قیمت؛ در FIFO مستقل وارد نشود.
### سنوات
افتتاحیه بعدی 1 با ReturnableVoucherItemRef به اختتامیه 99 سال قبل؛ کنترل مقدار/کالا/انبار/مرکز و فاصله یک روز. سال مالی شناسه‌های 2,6,7,8,9 به ترتیب؛ شناسه را سال شمسی فرض نکن.
