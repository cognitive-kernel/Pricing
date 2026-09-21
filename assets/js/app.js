$(function(){
  function initJalaliPickers(){
    if(typeof jQuery==='undefined' || typeof jQuery.fn.pDatepicker!=='function') return false;
    jQuery('[data-jalali-picker="true"]').each(function(){
      const $input=jQuery(this);
      if($input.data('persian-datepicker-initialized')) return;
      $input.pDatepicker({
        format:'YYYY/MM/DD',
        autoClose:true,
        initialValue:false,
        observer:true,
        calendar:{
          persian:{
            locale:'fa'
          }
        }
      });
      $input.data('persian-datepicker-initialized',true);
    });
    return true;
  }

  initJalaliPickers();
  setTimeout(initJalaliPickers,300);
  setTimeout(initJalaliPickers,1000);

  jQuery('#filter').on('submit',function(e){
    e.preventDefault();
    const q=jQuery('#q').val();
    jQuery.get('/pricing/public/prices',{q:q,ajax:1},function(html){
      jQuery('#price-table').html(html);
    });
  });
});