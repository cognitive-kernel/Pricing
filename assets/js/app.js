$(function(){
  const JALALI_MONTHS=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  const WEEKDAYS=['ش','ی','د','س','چ','پ','ج'];

  function div(a,b){ return Math.floor(a/b); }
  function mod(a,b){ return ((a%b)+b)%b; }

  function jalaliToGregorian(jy,jm,jd){
    jy+=1595;
    let days=-355668+365*jy+div(jy,33)*8+div(mod(jy,33)+3,4)+jd+(jm<7?(jm-1)*31:(jm-7)*30+186);
    let gy=400*div(days,146097);
    days%=146097;
    if(days>36524){
      gy+=100*div(--days,36524);
      days%=36524;
      if(days>=365) days++;
    }
    gy+=4*div(days,1461);
    days%=1461;
    if(days>365){
      gy+=div(days-1,365);
      days=(days-1)%365;
    }
    const gd=days+1;
    return [gy,1,1].map((_,i)=>i===0?gy:i===1?0:0).length?gregorianAdjust(gy,gd):[gy,1,gd];
  }

  function gregorianAdjust(gy,gd){
    const md=[31,28,31,30,31,30,31,31,30,31,30,31];
    const leap=(gy%4===0&&gy%100!==0)||gy%400===0;
    if(leap) md[1]=29;
    let gm=1,d=gd;
    for(let i=0;i<12;i++){ if(d<=md[i]){gm=i+1;break;} d-=md[i]; }
    return [gy,gm,d];
  }

  function gregorianToJalali(gy,gm,gd){
    const gdm=[0,31,59,90,120,151,181,212,243,273,304,334];
    let jy=gy<=1600?0:979;
    gy-=gy<=1600?621:1600;
    const gy2=gm>2?gy+1:gy;
    let days=365*gy+div(gy2+3,4)-div(gy2+99,100)+div(gy2+399,400)-80+gd+gdm[gm-1];
    jy+=33*div(days,12053);
    days%=12053;
    jy+=4*div(days,1461);
    days%=1461;
    if(days>365){jy+=div(days-1,365);days=(days-1)%365;}
    const jm=days<186?1+div(days,31):7+div(days-186,30);
    const jd=1+(days<186?mod(days,31):mod(days-186,30));
    return [jy,jm,jd];
  }

  function jalaliMonthLength(y,m){
    if(m<=6) return 31;
    if(m<=11) return 30;
    const a=jalaliToGregorian(y,12,1);
    const b=jalaliToGregorian(y+1,1,1);
    const da=Date.UTC(a[0],a[1]-1,a[2]);
    const db=Date.UTC(b[0],b[1]-1,b[2]);
    return Math.round((db-da)/86400000);
  }

  function normalizeDigits(value){
    return String(value||'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
  }

  function parseJalali(value){
    const m=normalizeDigits(value).trim().match(/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/);
    if(!m) return null;
    const y=+m[1],mo=+m[2],d=+m[3];
    if(y<1||mo<1||mo>12||d<1||d>jalaliMonthLength(y,mo)) return null;
    return [y,mo,d];
  }

  function formatJalali(y,m,d){ return y+'/'+String(m).padStart(2,'0')+'/'+String(d).padStart(2,'0'); }

  function todayJalali(){
    const n=new Date();
    return gregorianToJalali(n.getFullYear(),n.getMonth()+1,n.getDate());
  }

  function buildPicker(input){
    if(input._jalaliPicker) return input._jalaliPicker;
    const box=document.createElement('div');
    box.className='jalali-picker';
    box.dir='rtl';
    box.innerHTML='<div class="jalali-picker-head"><button type="button" class="jalali-prev" aria-label="ماه قبل">‹</button><button type="button" class="jalali-title"></button><button type="button" class="jalali-next" aria-label="ماه بعد">›</button></div><div class="jalali-weekdays"></div><div class="jalali-days"></div><div class="jalali-picker-foot"><button type="button" class="jalali-today">امروز</button><button type="button" class="jalali-close">بستن</button></div>';
    document.body.appendChild(box);

    const state={...((parseJalali(input.value))||todayJalali())};
    state.y=state[0];state.m=state[1];state.d=state[2];

    const weekdays=box.querySelector('.jalali-weekdays');
    WEEKDAYS.forEach(w=>{const e=document.createElement('span');e.textContent=w;weekdays.appendChild(e);});

    function position(){
      const r=input.getBoundingClientRect();
      box.style.top=(window.scrollY+r.bottom+6)+'px';
      box.style.left=Math.max(8,Math.min(window.scrollX+r.left,window.scrollX+document.documentElement.clientWidth-box.offsetWidth-8))+'px';
    }

    function render(){
      box.querySelector('.jalali-title').textContent=JALALI_MONTHS[state.m-1]+' '+state.y;
      const days=box.querySelector('.jalali-days');
      days.innerHTML='';
      const first=jalaliToGregorian(state.y,state.m,1);
      const jsDay=new Date(first[0],first[1]-1,first[2]).getDay();
      const offset=(jsDay+1)%7;
      for(let i=0;i<offset;i++) days.appendChild(document.createElement('span'));
      const len=jalaliMonthLength(state.y,state.m);
      for(let d=1;d<=len;d++){
        const b=document.createElement('button');
        b.type='button';b.textContent=String(d);
        if(d===state.d) b.className='selected';
        b.addEventListener('click',()=>{
          input.value=formatJalali(state.y,state.m,d);
          input.dispatchEvent(new Event('input',{bubbles:true}));
          input.dispatchEvent(new Event('change',{bubbles:true}));
          box.classList.remove('show');
        });
        days.appendChild(b);
      }
    }

    box.querySelector('.jalali-prev').addEventListener('click',()=>{
      state.m--;if(state.m<1){state.m=12;state.y--;}state.d=Math.min(state.d,jalaliMonthLength(state.y,state.m));render();
    });
    box.querySelector('.jalali-next').addEventListener('click',()=>{
      state.m++;if(state.m>12){state.m=1;state.y++;}state.d=Math.min(state.d,jalaliMonthLength(state.y,state.m));render();
    });
    box.querySelector('.jalali-today').addEventListener('click',()=>{
      const t=todayJalali();state.y=t[0];state.m=t[1];state.d=t[2];input.value=formatJalali(...t);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));render();
    });
    box.querySelector('.jalali-close').addEventListener('click',()=>box.classList.remove('show'));

    input.addEventListener('focus',()=>{
      const v=parseJalali(input.value)||todayJalali();
      state.y=v[0];state.m=v[1];state.d=v[2];render();box.classList.add('show');position();
    });
    input.addEventListener('click',()=>{
      const v=parseJalali(input.value)||todayJalali();
      state.y=v[0];state.m=v[1];state.d=v[2];render();box.classList.add('show');position();
    });
    input.addEventListener('keydown',e=>{if(e.key==='Escape')box.classList.remove('show');});
    window.addEventListener('resize',()=>{if(box.classList.contains('show'))position();});
    window.addEventListener('scroll',()=>{if(box.classList.contains('show'))position();},true);
    input._jalaliPicker=box;
    return box;
  }

  function initJalaliPickers(){
    document.querySelectorAll('[data-jalali-picker="true"]').forEach(input=>{
      if(!input._jalaliPicker) buildPicker(input);
      input.setAttribute('autocomplete','off');
    });
  }

  document.addEventListener('click',e=>{
    document.querySelectorAll('.jalali-picker.show').forEach(box=>{
      if(!box.contains(e.target) && box!==e.target._jalaliPicker) box.classList.remove('show');
    });
  });

  initJalaliPickers();
  setTimeout(initJalaliPickers,300);
  setTimeout(initJalaliPickers,1000);

  jQuery('#filter').on('submit',function(e){
    e.preventDefault();
    const q=jQuery('#q').val();
    jQuery.get('/pricing/public/prices',{q:q,ajax:1},function(html){
      jQuery('#price-table').html(html);
      initPriceActionRows();
    });
  });

  function initPriceActionRows(){
    document.querySelectorAll('.price-action-row[data-href]').forEach(function(row){
      if(row._priceActionBound) return;
      row._priceActionBound=true;
      row.addEventListener('click',function(event){
        if(event.target.closest('a,button,input,form,select,textarea')) return;
        window.location.href=row.dataset.href;
      });
    });
  }

  function initPriceForm(){
    const form=document.getElementById('price-form');
    if(!form || form._priceValidationBound) return;
    form._priceValidationBound=true;
    form.addEventListener('submit',function(event){
      const required=Number(form.dataset.requiredSources||0);
      let filled=0;
      for(let i=0;i<3;i++){
        const supplier=form.querySelector('[name="prices['+i+'][supplier]"]');
        const packagePrice=form.querySelector('[name="prices['+i+'][package_price]"]');
        const unitPrice=form.querySelector('[name="prices['+i+'][unit_price]"]');
        const date=form.querySelector('[name="prices['+i+'][price_received_at]"]');
        if((supplier?.value||'').trim()!==''||(packagePrice?.value||'').trim()!==''||(unitPrice?.value||'').trim()!==''||(date?.value||'').trim()!=='') filled++;
      }
      if(filled<required){
        event.preventDefault();
        alert('برای این وضعیت حداقل '+required+' قیمت جدید بازار باید ثبت شود.');
      }
    });
  }

  initPriceActionRows();
  initPriceForm();
});