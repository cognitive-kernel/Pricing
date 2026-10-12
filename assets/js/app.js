$(function(){
  const JALALI_MONTHS=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

  document.addEventListener('change',function(event){
    const select=event.target.closest('form[data-auto-submit-on-change] select');
    if(select && select.value !== '') select.form.requestSubmit();
  });

  document.addEventListener('change',function(event){
    if(event.target && event.target.id==='queue-select-all'){
      document.querySelectorAll('#queue-items-table .queue-item-select').forEach(function(box){
        box.checked=event.target.checked;
        const row=box.closest('.queue-price-row');
        if(box.checked && typeof window.syncQueueSecondStageToRow==='function') window.syncQueueSecondStageToRow(row);
        else if(!box.checked && typeof window.restoreQueueSecondStageRow==='function') window.restoreQueueSecondStageRow(row);
      });
      if(typeof window.renderQueuePricePreview==='function') window.renderQueuePricePreview();
    } else if(event.target && event.target.classList.contains('queue-item-select')){
      const row=event.target.closest('.queue-price-row');
      if(event.target.checked && typeof window.syncQueueSecondStageToRow==='function') window.syncQueueSecondStageToRow(row);
      else if(!event.target.checked && typeof window.restoreQueueSecondStageRow==='function') window.restoreQueueSecondStageRow(row);
      const boxes=Array.from(document.querySelectorAll('#queue-items-table .queue-item-select'));
      const all=document.getElementById('queue-select-all');
      if(all){
        all.checked=boxes.length>0 && boxes.every(function(box){return box.checked;});
        all.indeterminate=boxes.some(function(box){return box.checked;}) && !all.checked;
      }
      if(typeof window.renderQueuePricePreview==='function') window.renderQueuePricePreview();
    }
  });

  function initQueuePricePreview(){
    const table=document.getElementById('queue-items-table');
    if(!table) return;
    const firstType=document.getElementById('first-adjustment');
    const firstValue=document.getElementById('first-value');
    const secondType=document.getElementById('second-adjustment');
    const secondValue=document.getElementById('second-value');
    const agreement=document.getElementById('queue-payment-agreement');
    const masterInquiryDate=document.getElementById('queue-master-inquiry-date');
    function syncInquiryDates(){
      if(!masterInquiryDate) return;
      table.querySelectorAll('.queue-price-row input[name$="[inquiry_date]"]').forEach(function(input){input.value=masterInquiryDate.value;});
    }
    if(masterInquiryDate){masterInquiryDate.addEventListener('input',syncInquiryDates);masterInquiryDate.addEventListener('change',syncInquiryDates);}
    function adjust(price,type,percent){const factor=Math.max(0,Number.isFinite(percent)?percent:0)/100;return type==='decrease'?price*(1-factor):price*(1+factor);}
    function money(value){return value.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
    function render(mode,targetRow){
      const rows=targetRow?[targetRow]:Array.from(table.querySelectorAll('.queue-price-row'));
      rows.forEach(function(row){
        const check=row.querySelector('.queue-item-select');
        const priceInput=row.querySelector('.queue-saved-price');
        const grossCell=row.querySelector('.queue-gross-price');
        const finalCell=row.querySelector('.queue-final-price');
        const agreementCell=row.querySelector('.queue-agreement-title');
        if(agreementCell&&agreement) agreementCell.textContent=agreement.selectedIndex>0?agreement.options[agreement.selectedIndex].text:'—';
        if(mode==='global'&&(!check||!check.checked)) return;
        const enteredPrice=priceInput?priceInput.value.trim():'';
        if(enteredPrice===''||!Number.isFinite(Number(enteredPrice))){if(grossCell)grossCell.textContent='—';if(finalCell)finalCell.childNodes[0].textContent='—';return;}
        const price=Number(enteredPrice);
        const firstPercent=Math.max(0,Number.parseFloat(firstValue?.value||'0')||0);
        const stageOne=adjust(price,firstType?.value||'increase',firstPercent);
        const discount=Math.min(100,Math.max(0,Number.parseFloat(row.querySelector('.queue-discount')?.value||'0')||0));
        const op=row.querySelector('.queue-operation')?.value==='0'?'0':'1';
        const gross=stageOne;
        const stageTwo=adjust(gross,op==='0'?'decrease':'increase',discount);
        const agreementPercent=Math.max(0,Number.parseFloat(row.querySelector('.queue-agreement-percent')?.value||'0')||0);
        const net=op==='1'?stageTwo+(stageTwo*agreementPercent/100):stageTwo-(stageTwo*agreementPercent/100);
        if(grossCell)grossCell.textContent=money(gross);
        if(finalCell)finalCell.childNodes[0].textContent=money(net);
      });
    }
    window.renderQueuePricePreview=function(){render('global');};
    function syncSecondStageToSelectedRows(){
      table.querySelectorAll('.queue-price-row').forEach(function(row){if(row.querySelector('.queue-item-select')?.checked)window.syncQueueSecondStageToRow(row);});
      render('global');
    }
    window.syncQueueSecondStageToRow=function(row){
      if(!row||!row.querySelector('.queue-item-select')?.checked)return;
      const op=row.querySelector('.queue-operation');
      const discountInput=row.querySelector('.queue-discount');
      if(!row.dataset.queueSecondStageDefaultsSaved){
        row.dataset.queueOriginalOperation=op?op.value:'';
        row.dataset.queueOriginalDiscount=discountInput?discountInput.value:'';
        row.dataset.queueOriginalGross=row.querySelector('.queue-gross-price')?.textContent||'—';
        const finalCell=row.querySelector('.queue-final-price');
        row.dataset.queueOriginalFinal=finalCell?.childNodes[0]?.textContent||'—';
        row.dataset.queueSecondStageDefaultsSaved='1';
      }
      if(op)op.value=secondType?.value==='decrease'?'0':'1';
      if(discountInput&&secondValue)discountInput.value=secondValue.value;
      render('row',row);
    };
    window.restoreQueueSecondStageRow=function(row){
      if(!row||row.dataset.queueSecondStageDefaultsSaved!=='1')return;
      const op=row.querySelector('.queue-operation');
      const discountInput=row.querySelector('.queue-discount');
      if(op)op.value=row.dataset.queueOriginalOperation;
      if(discountInput)discountInput.value=row.dataset.queueOriginalDiscount;
      const grossCell=row.querySelector('.queue-gross-price');
      const finalCell=row.querySelector('.queue-final-price');
      if(grossCell)grossCell.textContent=row.dataset.queueOriginalGross||'—';
      if(finalCell&&finalCell.childNodes[0])finalCell.childNodes[0].textContent=row.dataset.queueOriginalFinal||'—';
    };
    [firstType,firstValue,secondType,secondValue,agreement].filter(Boolean).forEach(function(control){control.addEventListener('input',function(){render('global');});control.addEventListener('change',function(){render('global');});});
    if(secondType)secondType.addEventListener('change',syncSecondStageToSelectedRows);
    if(secondValue){secondValue.addEventListener('input',syncSecondStageToSelectedRows);secondValue.addEventListener('change',syncSecondStageToSelectedRows);}
    table.addEventListener('input',function(event){if(event.target.matches('.queue-saved-price,.queue-discount,.queue-agreement-percent')){const row=event.target.closest('.queue-price-row');if(row)render('row',row);}});
    table.addEventListener('change',function(event){if(event.target.matches('.queue-saved-price,.queue-discount,.queue-operation,.queue-agreement-percent')){const row=event.target.closest('.queue-price-row');if(row)render('row',row);}});
    render('global');
  }

  initQueuePricePreview();
  const WEEKDAYS=['ش','ی','د','س','چ','پ','ج'];
  function div(a,b){return Math.floor(a/b);}
  function mod(a,b){return ((a%b)+b)%b;}
  function jalaliToGregorian(jy,jm,jd){jy+=1595;let days=-355668+365*jy+div(jy,33)*8+div(mod(jy,33)+3,4)+jd+(jm<7?(jm-1)*31:(jm-7)*30+186);let gy=400*div(days,146097);days%=146097;if(days>36524){gy+=100*div(--days,36524);days%=36524;if(days>=365)days++;}gy+=4*div(days,1461);days%=1461;if(days>365){gy+=div(days-1,365);days=(days-1)%365;}return gregorianAdjust(gy,days+1);}
  function gregorianAdjust(gy,gd){const md=[31,28,31,30,31,30,31,31,30,31,30,31];const leap=(gy%4===0&&gy%100!==0)||gy%400===0;if(leap)md[1]=29;let gm=1,d=gd;for(let i=0;i<12;i++){if(d<=md[i]){gm=i+1;break;}d-=md[i];}return[gy,gm,d];}
  function gregorianToJalali(gy,gm,gd){const gdm=[0,31,59,90,120,151,181,212,243,273,304,334];let jy=gy<=1600?0:979;gy-=gy<=1600?621:1600;const gy2=gm>2?gy+1:gy;let days=365*gy+div(gy2+3,4)-div(gy2+99,100)+div(gy2+399,400)-80+gd+gdm[gm-1];jy+=33*div(days,12053);days%=12053;jy+=4*div(days,1461);days%=1461;if(days>365){jy+=div(days-1,365);days=(days-1)%365;}const jm=days<186?1+div(days,31):7+div(days-186,30);const jd=1+(days<186?mod(days,31):mod(days-186,30));return[jy,jm,jd];}
  function jalaliMonthLength(y,m){if(m<=6)return 31;if(m<=11)return 30;const a=jalaliToGregorian(y,12,1);const b=jalaliToGregorian(y+1,1,1);return Math.round((Date.UTC(b[0],b[1]-1,b[2])-Date.UTC(a[0],a[1]-1,a[2]))/86400000);}
  function normalizeDigits(value){return String(value||'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));}
  function parseJalali(value){const m=normalizeDigits(value).trim().match(/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/);if(!m)return null;const y=+m[1],mo=+m[2],d=+m[3];if(y<1||mo<1||mo>12||d<1||d>jalaliMonthLength(y,mo))return null;return[y,mo,d];}
  function formatJalali(y,m,d){return y+'/'+String(m).padStart(2,'0')+'/'+String(d).padStart(2,'0');}
  function todayJalali(){const n=new Date();return gregorianToJalali(n.getFullYear(),n.getMonth()+1,n.getDate());}
  function buildPicker(input){
    if(input._jalaliPicker)return input._jalaliPicker;
    const box=document.createElement('div');box.className='jalali-picker';box.dir='rtl';box.innerHTML='<div class="jalali-picker-head"><button type="button" class="jalali-prev" aria-label="ماه قبل">‹</button><button type="button" class="jalali-title"></button><button type="button" class="jalali-next" aria-label="ماه بعد">›</button></div><div class="jalali-weekdays"></div><div class="jalali-days"></div><div class="jalali-picker-foot"><button type="button" class="jalali-today">امروز</button><button type="button" class="jalali-close">بستن</button></div>';document.body.appendChild(box);
    const state={...((parseJalali(input.value))||todayJalali())};state.y=state[0];state.m=state[1];state.d=state[2];
    const weekdays=box.querySelector('.jalali-weekdays');WEEKDAYS.forEach(w=>{const e=document.createElement('span');e.textContent=w;weekdays.appendChild(e);});
    function position(){const r=input.getBoundingClientRect();box.style.top=(window.scrollY+r.bottom+6)+'px';box.style.left=Math.max(8,Math.min(window.scrollX+r.left,window.scrollX+document.documentElement.clientWidth-box.offsetWidth-8))+'px';}
    function render(){box.querySelector('.jalali-title').textContent=JALALI_MONTHS[state.m-1]+' '+state.y;const days=box.querySelector('.jalali-days');days.innerHTML='';const first=jalaliToGregorian(state.y,state.m,1);const jsDay=new Date(first[0],first[1]-1,first[2]).getDay();const offset=(jsDay+1)%7;for(let i=0;i<offset;i++)days.appendChild(document.createElement('span'));const len=jalaliMonthLength(state.y,state.m);for(let d=1;d<=len;d++){const b=document.createElement('button');b.type='button';b.textContent=String(d);if(d===state.d)b.className='selected';b.addEventListener('click',()=>{input.value=formatJalali(state.y,state.m,d);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));box.classList.remove('show');});days.appendChild(b);}}
    box.querySelector('.jalali-prev').addEventListener('click',()=>{state.m--;if(state.m<1){state.m=12;state.y--;}state.d=Math.min(state.d,jalaliMonthLength(state.y,state.m));render();});box.querySelector('.jalali-next').addEventListener('click',()=>{state.m++;if(state.m>12){state.m=1;state.y++;}state.d=Math.min(state.d,jalaliMonthLength(state.y,state.m));render();});box.querySelector('.jalali-today').addEventListener('click',()=>{const t=todayJalali();state.y=t[0];state.m=t[1];state.d=t[2];input.value=formatJalali(...t);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));render();});box.querySelector('.jalali-close').addEventListener('click',()=>box.classList.remove('show'));
    input.addEventListener('focus',()=>{const v=parseJalali(input.value)||todayJalali();state.y=v[0];state.m=v[1];state.d=v[2];render();box.classList.add('show');position();});input.addEventListener('click',()=>{const v=parseJalali(input.value)||todayJalali();state.y=v[0];state.m=v[1];state.d=v[2];render();box.classList.add('show');position();});input.addEventListener('keydown',e=>{if(e.key==='Escape')box.classList.remove('show');});window.addEventListener('resize',()=>{if(box.classList.contains('show'))position();});window.addEventListener('scroll',()=>{if(box.classList.contains('show'))position();},true);input._jalaliPicker=box;return box;
  }
  function initJalaliPickers(){document.querySelectorAll('[data-jalali-picker="true"]').forEach(input=>{if(!input._jalaliPicker)buildPicker(input);input.setAttribute('autocomplete','off');});}
  document.addEventListener('click',e=>{document.querySelectorAll('.jalali-picker.show').forEach(box=>{if(!box.contains(e.target)&&box!==e.target._jalaliPicker)box.classList.remove('show');});});
  initJalaliPickers();setTimeout(initJalaliPickers,300);setTimeout(initJalaliPickers,1000);
  jQuery('#filter').on('submit',function(e){e.preventDefault();const q=jQuery('#q').val();const statusFilter=jQuery('#status-filter').val()||'all';window.location.href='/pricing/public/prices/list?q='+encodeURIComponent(q)+'&status_filter='+encodeURIComponent(statusFilter);});
  const statusFilter=document.getElementById('status-filter');if(statusFilter)statusFilter.addEventListener('change',function(){document.getElementById('filter')?.requestSubmit();});
  function initPriceActionRows(){document.querySelectorAll('.price-action-row[data-href]').forEach(function(row){if(row._priceActionBound)return;row._priceActionBound=true;row.addEventListener('dblclick',function(event){if(event.target.closest('a,button,input,form,select,textarea'))return;window.location.href=row.dataset.href;});});}
  function initPriceForm(){const form=document.getElementById('price-form');if(!form||form._priceValidationBound)return;form._priceValidationBound=true;form.addEventListener('submit',function(event){const required=Number(form.dataset.requiredSources||0);let filled=0;for(let i=0;i<3;i++){const source=form.querySelector('[name="prices['+i+'][source]"]');const price=form.querySelector('[name="prices['+i+'][price]"]');const unit=form.querySelector('[name="prices['+i+'][unit_id]"]');const date=form.querySelector('[name="prices['+i+'][inquiry_date]"]');const hasAny=(source?.value||'').trim()!==''||(price?.value||'').trim()!==''||(unit?.value||'0')!=='0'||(date?.value||'').trim()!=='';if(hasAny)filled++;}if(filled<required){event.preventDefault();alert('برای این وضعیت حداقل '+required+' قیمت جدید بازار باید ثبت شود.');}});}
  function initCompanyItemsSearch(){
    const input=document.getElementById('company-items-search');
    const table=document.getElementById('company-items-table');
    const count=document.getElementById('company-items-search-count');
    if(!input||!table||input._companySearchBound)return;
    input._companySearchBound=true;
    const rows=Array.from(table.querySelectorAll('tbody .company-item-row'));
    function normalize(value){return String(value||'').toLocaleLowerCase('fa').replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/\\s+/g,' ').trim();}
    function filter(){
      const query=normalize(input.value);
      let visible=0;
      rows.forEach(function(row){
        const matches=!query||normalize(row.textContent).includes(query);
        row.hidden=!matches;
        if(matches)visible++;
      });
      if(count)count.textContent=query?(visible+' مورد از '+rows.length+' مورد نمایش داده می‌شود'+(visible===0?'؛ موردی پیدا نشد.':'')):(rows.length+' مورد بارگذاری شده است.');
    }
    input.addEventListener('input',filter);
    filter();
  }
  function initCompanyRowSave(){const table=document.getElementById('company-items-table');if(!table||table._companySaveBound)return;table._companySaveBound=true;table.addEventListener('click',async function(event){const button=event.target.closest('.company-row-save');if(!button)return;const row=button.closest('.company-item-row');const price=row.querySelector('.company-final-price');const agreement=row.querySelector('.company-agreement');const message=row.querySelector('.company-row-message');const amount=(price?.value||'').trim();if(!amount||!Number.isFinite(Number(amount))||Number(amount)<=0){price?.focus();message.textContent=' مبلغ معتبر وارد کنید.';return;}if(!agreement?.value){agreement?.focus();message.textContent=' توافق پرداخت را انتخاب کنید.';return;}button.disabled=true;message.textContent=' در حال ثبت…';try{const body=new URLSearchParams({_csrf:document.getElementById('company-row-csrf')?.value||'',company_code:document.getElementById('company-row-code')?.value||'',product_code:row.dataset.productCode||'',payment_agreement_id:agreement.value,final_price:amount});const response=await fetch('/pricing/public/products/bulk-save-row',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString(),credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'ثبت ردیف انجام نشد.');row.classList.add('table-success');message.textContent=' ثبت شد';}catch(error){message.textContent=' '+(error.message||'خطا در ثبت ردیف');}finally{button.disabled=false;}});}
  initCompanyItemsSearch();initCompanyRowSave();initPriceActionRows();initPriceForm();

  function initPriceApprovals(){
    const form=document.getElementById('approval-decision-form');
    const selectAll=document.getElementById('approval-select-all');
    const exportButton=document.getElementById('approval-export-btn');
    const messageBox=document.getElementById('approval-message');
    if(!form&&!selectAll&&!exportButton)return;
    function showMessage(message,success){
      if(!messageBox)return;
      messageBox.textContent=message|| (success?'عملیات انجام شد.':'خطایی رخ داد.');
      messageBox.className='alert mb-3 '+(success?'alert-success':'alert-danger');
      messageBox.hidden=false;
      messageBox.scrollIntoView({behavior:'smooth',block:'nearest'});
    }
    function boxes(){return Array.from(document.querySelectorAll('#approval-decision-form .approval-select'));}
    function syncMaster(){
      if(!selectAll)return;
      const all=boxes();
      const checked=all.filter(box=>box.checked).length;
      selectAll.checked=all.length>0&&checked===all.length;
      selectAll.indeterminate=checked>0&&checked<all.length;
    }
    if(selectAll&&!selectAll._approvalBound){
      selectAll._approvalBound=true;
      selectAll.addEventListener('change',function(){
        boxes().forEach(box=>{box.checked=selectAll.checked;});
        syncMaster();
      });
    }
    if(form&&!form._approvalBound){
      form._approvalBound=true;
      form.addEventListener('change',function(event){
        if(event.target.classList.contains('approval-select'))syncMaster();
      });
      form.addEventListener('submit',async function(event){
        event.preventDefault();
        const button=event.submitter;
        if(!button)return;
        if(!boxes().some(box=>box.checked)){
          showMessage('ابتدا حداقل یک ردیف را انتخاب کنید.',false);
          return;
        }
        if(!window.confirm(button.value==='approve'?'ردیف‌های انتخاب‌شده تأیید شوند؟':'ردیف‌های انتخاب‌شده رد شوند؟'))return;
        const data=new FormData(form);
        data.set('decision',button.value);
        button.disabled=true;
        try{
          const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
          const type=(response.headers.get('content-type')||'').toLowerCase();
          let result={};
          if(type.includes('application/json'))result=await response.json();
          else {const text=await response.text();throw new Error(text.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim()||'پاسخ نامعتبر از سرور دریافت شد.');}
          if(!response.ok||result.ok===false)throw new Error(result.message||'ثبت تصمیم انجام نشد.');
          showMessage(result.message||'تصمیم ثبت شد.',true);
          window.setTimeout(()=>window.location.reload(),700);
        }catch(error){showMessage(error.message||'خطا در ارتباط با سرور.',false);}
        finally{button.disabled=false;}
      });
    }
    if(exportButton&&!exportButton._approvalBound){
      exportButton._approvalBound=true;
      exportButton.addEventListener('click',async function(){
        exportButton.disabled=true;
        try{
          const response=await fetch('/pricing/public/prices/approvals/export',{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/vnd.ms-excel, application/json'}});
          const type=(response.headers.get('content-type')||'').toLowerCase();
          if(!response.ok||type.includes('application/json')||type.includes('text/html')){
            const text=await response.text();
            let message=text.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
            try{const json=JSON.parse(text);message=json.message||message;}catch(ignore){}
            throw new Error(message||'فایل اکسل از سرور دریافت نشد.');
          }
          const blob=await response.blob();
          if(blob.size===0)throw new Error('فایل خروجی خالی است.');
          const url=URL.createObjectURL(blob);
          const link=document.createElement('a');
          link.href=url;link.download='approved-market-prices.xls';
          document.body.appendChild(link);link.click();link.remove();
          window.setTimeout(()=>URL.revokeObjectURL(url),1000);
          showMessage('خروجی اکسل دریافت شد.',true);
        }catch(error){showMessage(error.message||'خطا در دریافت خروجی اکسل.',false);}
        finally{exportButton.disabled=false;}
      });
    }
    syncMaster();
  }
  initPriceApprovals();

});