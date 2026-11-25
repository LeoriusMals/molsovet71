(()=>{'use strict';
const pad=n=>String(n).padStart(2,'0');
function init(input){
 if(input.dataset.timeUi==='1')return;
 input.dataset.timeUi='1';
 const wrap=document.createElement('span');wrap.className='time-ui-wrap';
 input.parentNode.insertBefore(wrap,input);wrap.append(input);
 const trigger=document.createElement('button');trigger.type='button';trigger.className='time-ui-trigger';trigger.setAttribute('aria-label','Открыть выбор времени');trigger.setAttribute('aria-expanded','false');
 trigger.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';wrap.append(trigger);
 const pop=document.createElement('div');pop.className='time-ui-pop';pop.hidden=true;pop.setAttribute('role','dialog');pop.setAttribute('aria-label','Выбор времени');document.body.append(pop);
 let hour='09',minute='00',stage='hour';
 function readValue(){const m=/^(\d{1,2}):(\d{2})$/.exec(input.value.trim());if(m&&+m[1]<24&&+m[2]<60)return{h:pad(+m[1]),m:m[2]};return null}
 function render(){
  const values=stage==='hour'?Array.from({length:24},(_,i)=>pad(i)):Array.from({length:60},(_,i)=>pad(i));
  const selected=stage==='hour'?hour:minute;
  pop.innerHTML='<div class="time-ui-head"><div><span class="time-ui-kicker">МЕРОПРИЯТИЕ</span><b>Выберите время</b></div><button type="button" class="time-ui-x" data-time-close aria-label="Закрыть">×</button></div><div class="time-ui-display"><strong>'+hour+':'+minute+'</strong><span>Часы и минуты</span></div><div class="time-ui-tabs"><button type="button" data-time-stage="hour" class="'+(stage==='hour'?'is-active':'')+'">Часы</button><button type="button" data-time-stage="minute" class="'+(stage==='minute'?'is-active':'')+'">Минуты</button></div><div class="time-ui-grid '+(stage==='minute'?'minutes':'hours')+'">'+values.map(v=>'<button type="button" class="time-ui-cell'+(v===selected?' is-selected':'')+'" data-time-value="'+v+'">'+v+'</button>').join('')+'</div><div class="time-ui-footer"><button type="button" class="time-ui-clear" data-time-clear>Очистить</button><div><button type="button" class="time-ui-next" data-time-next>'+(stage==='hour'?'К минутам':'К часам')+'</button><button type="button" class="time-ui-done" data-time-done>Готово</button></div></div>';
 }
 function position(){
  const r=wrap.getBoundingClientRect();
  const mobile=window.matchMedia('(max-width: 640px)').matches;
  pop.style.position='fixed';
  pop.style.bottom='auto';
  pop.style.margin='0';
  if(mobile){
   pop.style.left='12px';
   pop.style.right='12px';
   pop.style.top='auto';
   pop.style.bottom='12px';
   pop.style.width='auto';
   pop.style.maxWidth='420px';
   pop.style.margin='0 auto';
  }else{
   pop.style.bottom='auto';
   const w=Math.min(320,window.innerWidth-32);
   pop.style.width=w+'px';
   const left=Math.max(16,Math.min(r.right-w,window.innerWidth-w-16));
   const estimated=340;
   let top=r.bottom+8;
   if(top+estimated>window.innerHeight-12&&r.top>estimated)top=Math.max(12,r.top-estimated-8);
   pop.style.left=left+'px';
   pop.style.top=top+'px';
   pop.style.right='auto';
  }
 }
 function open(){const v=readValue();if(v){hour=v.h;minute=v.m}else{hour='09';minute='00'}stage='hour';render();pop.hidden=false;position();trigger.setAttribute('aria-expanded','true')}
 function close(){pop.hidden=true;trigger.setAttribute('aria-expanded','false')}
 function commit(){input.value=hour+':'+minute;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));close()}
 trigger.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();pop.hidden?open():close()});
 input.addEventListener('click',e=>{e.stopPropagation();open()});
 input.addEventListener('input',()=>{const v=readValue();if(v){hour=v.h;minute=v.m}});
 input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();if(!pop.hidden)commit();else open()}else if(e.key==='Escape')close()});
 pop.addEventListener('click',e=>{const stageBtn=e.target.closest('[data-time-stage]'),value=e.target.closest('[data-time-value]');if(stageBtn){stage=stageBtn.dataset.timeStage;render();return}if(value){if(stage==='hour'){hour=value.dataset.timeValue;stage='minute'}else{minute=value.dataset.timeValue}render();return}if(e.target.closest('[data-time-next]')){stage=stage==='hour'?'minute':'hour';render();return}if(e.target.closest('[data-time-done]')){commit();return}if(e.target.closest('[data-time-clear]')){input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));close();return}if(e.target.closest('[data-time-close]'))close()});
 document.addEventListener('pointerdown',e=>{if(!wrap.contains(e.target)&&!pop.contains(e.target))close()});document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});window.addEventListener('resize',()=>{if(!pop.hidden)position()});window.addEventListener('scroll',()=>{if(!pop.hidden)close()},true);
}
function boot(){document.querySelectorAll('#eventTime').forEach(init)}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
new MutationObserver(records=>records.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches?.('#eventTime'))init(n);n.querySelectorAll?.('#eventTime').forEach(init)}}))).observe(document.documentElement,{childList:true,subtree:true});
})();