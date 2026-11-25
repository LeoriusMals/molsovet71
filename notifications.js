(function(){
'use strict';
const KEY='ms_portal_notifications_v1', USERS='ms_local_auth_users_v1', SESSION='ms_local_auth_session_v1';
const read=(k,d)=>{try{return JSON.parse(localStorage.getItem(k)||'null')??d}catch(_){return d}};
const write=(k,v)=>{try{localStorage.setItem(k,JSON.stringify(v))}catch(_){}};
function current(){const s=read(SESSION,null),us=read(USERS,[]);return s?(us.find(u=>String(u.id)===String(s.id))||{id:s.id,login:s.login}):null}
function uid(u){return String(u?.id??u?.userId??u?.login??u?.username??u?.email??'')}
function all(){return read(KEY,[])}
function push(recipient,type,title,message,link){
 if(!recipient)return;
 const list=all(); list.unshift({id:'n'+Date.now().toString(36)+Math.random().toString(36).slice(2,7),recipient:String(recipient),type,title,message,link:link||'',createdAt:Date.now(),read:false});
 write(KEY,list.slice(0,500)); window.dispatchEvent(new CustomEvent('ms:notifications-updated'));
}
window.MSNotifications={
 add:function(data){const u=data.userId??data.recipient??uid(current());push(u,data.type||'info',data.title||'Новое уведомление',data.message||'',data.link||'')},
 list:function(){const u=current();return all().filter(n=>u&&n.recipient===uid(u))},
 markRead:function(id){const u=current();write(KEY,all().map(n=>u&&n.recipient===uid(u)&&(!id||n.id===id)?{...n,read:true}:n));window.dispatchEvent(new CustomEvent('ms:notifications-updated'))},
 markAllRead:function(){this.markRead()},
 clear:function(){const u=current();write(KEY,all().filter(n=>!u||n.recipient!==uid(u)));window.dispatchEvent(new CustomEvent('ms:notifications-updated'))}
};
const css=`.ms-bell-wrap{position:relative;display:inline-flex;align-items:center;margin:0 8px;z-index:10010;font:inherit}.ms-bell{position:relative;width:42px;height:42px;border:1px solid #dce9df;background:#fff;color:#176a43;border-radius:13px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:background .18s,transform .18s}.ms-bell:hover{background:#edf7f0;transform:translateY(-1px)}.ms-bell svg{width:21px;height:21px}.ms-bell-count{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:99px;background:#d93648;color:white;font:700 11px/18px system-ui;text-align:center;border:2px solid white}.ms-notif-pop{position:absolute;right:0;top:calc(100% + 10px);width:min(370px,calc(100vw - 28px));max-height:min(520px,75vh);overflow:auto;background:#fff;border:1px solid #e0e9e2;border-radius:18px;box-shadow:0 18px 55px #163d2926;color:#183c2b;display:none}.ms-notif-pop.open{display:block;animation:msNotifIn .16s ease-out}.ms-notif-head{position:sticky;top:0;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:16px;border-bottom:1px solid #edf1ee;z-index:1}.ms-notif-head strong{font-size:16px}.ms-notif-action{border:0;background:transparent;color:#18764b;font:600 12px system-ui;cursor:pointer;padding:6px}.ms-notif-item{display:flex;gap:11px;padding:13px 15px;border-bottom:1px solid #f0f3f0;cursor:pointer}.ms-notif-item:hover{background:#f8fbf8}.ms-notif-item.unread{background:#f0f8f2}.ms-notif-icon{width:36px;height:36px;flex:0 0 36px;border-radius:11px;background:#e5f4e9;color:#167345;display:flex;align-items:center;justify-content:center}.ms-notif-icon svg{width:18px;height:18px}.ms-notif-copy{min-width:0;flex:1}.ms-notif-copy b{display:block;font-size:13px;margin:0 0 4px}.ms-notif-copy p{font-size:12px;line-height:1.45;color:#5c7165;margin:0;overflow-wrap:anywhere}.ms-notif-time{font-size:10px;color:#87968c;margin-top:6px}.ms-notif-dot{width:7px;height:7px;flex:0 0 7px;background:#1b9b5d;border-radius:50%;margin-top:5px}.ms-notif-empty{padding:30px 20px;text-align:center;color:#7a8d80;font-size:13px}.ms-notif-empty svg{width:30px;height:30px;color:#76a889;margin-bottom:8px}@keyframes msNotifIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}@media(max-width:600px){.ms-bell-wrap{margin:0 3px}.ms-bell{width:38px;height:38px}.ms-notif-pop{position:fixed;top:72px;right:12px;width:calc(100vw - 24px);max-height:70vh}}@media(prefers-reduced-motion:reduce){.ms-notif-pop.open{animation:none}.ms-bell{transition:none}}`;
const style=document.createElement('style');style.id='msNotificationStyles';style.textContent=css;document.head.appendChild(style);
const bell=document.createElement('div');bell.className='ms-bell-wrap';bell.innerHTML=`<button class="ms-bell" type="button" aria-label="Уведомления" aria-expanded="false" title="Уведомления"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><span class="ms-bell-count" hidden>0</span></button><section class="ms-notif-pop" role="dialog" aria-label="Уведомления"><header class="ms-notif-head"><strong>Уведомления</strong><button class="ms-notif-action" data-ms-readall>Прочитать все</button></header><div class="ms-notif-list"></div></section>`;
const button=bell.querySelector('.ms-bell'), pop=bell.querySelector('.ms-notif-pop'), list=bell.querySelector('.ms-notif-list'), count=bell.querySelector('.ms-bell-count');
const icons={points:'<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.4l6.1-.9z"/>',report:'<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h4M10 14l2 2 4-4"/>',task:'<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 4V2h8v2M8 10h8M8 14h6"/>',security:'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',role:'<path d="M12 3 20 7v5c0 5-4 8-8 9-4-1-8-4-8-9V7z"/><path d="m9 12 2 2 4-4"/>',info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7h.01"/>'};
function time(t){const d=Math.max(0,Date.now()-t);if(d<60000)return 'Только что';if(d<3600000)return Math.floor(d/60000)+' мин. назад';if(d<86400000)return Math.floor(d/3600000)+' ч. назад';return new Date(t).toLocaleDateString('ru-RU',{day:'numeric',month:'short'})}
function render(){const ns=window.MSNotifications.list(),unread=ns.filter(n=>!n.read).length;count.hidden=!unread;count.textContent=unread>99?'99+':String(unread);list.innerHTML='';if(!ns.length){list.innerHTML='<div class="ms-notif-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><div>Пока нет уведомлений</div></div>';return}ns.slice(0,60).forEach(n=>{const el=document.createElement('article');el.className='ms-notif-item'+(!n.read?' unread':'');el.tabIndex=0;el.innerHTML='<div class="ms-notif-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'+(icons[n.type]||icons.info)+'</svg></div><div class="ms-notif-copy"><b></b><p></p><div class="ms-notif-time"></div></div>'+(!n.read?'<span class="ms-notif-dot"></span>':'');el.querySelector('b').textContent=n.title||'Уведомление';el.querySelector('p').textContent=n.message||'';el.querySelector('.ms-notif-time').textContent=time(n.createdAt||Date.now());const open=()=>{window.MSNotifications.markRead(n.id);if(n.link)location.href=n.link};el.addEventListener('click',open);el.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();open()}});list.appendChild(el)})}
button.addEventListener('click',()=>{const open=pop.classList.toggle('open');button.setAttribute('aria-expanded',String(open));if(open)render()});
bell.querySelector('[data-ms-readall]').addEventListener('click',()=>{window.MSNotifications.markAllRead();render()});
document.addEventListener('click',e=>{if(!bell.contains(e.target)){pop.classList.remove('open');button.setAttribute('aria-expanded','false')}});
window.addEventListener('ms:notifications-updated',render);
const portalTop=document.querySelector('body > .app > .main > header.top');
const profileHeaderLinks=document.querySelector('body > header.top > .toplinks');
const target=portalTop||profileHeaderLinks||document.querySelector('.topbar .topbar-right,.topbar-right,.header-actions,.nav-actions,.user-actions,.profile-actions')||document.querySelector('header nav')||document.querySelector('nav');
if(target){if(target===portalTop){bell.style.flex='0 0 auto';bell.style.margin='0 0 0 4px';target.appendChild(bell)}else target.prepend(bell)}else document.body.appendChild(bell);
render();

// Detect changes to locally stored accounts and admin-managed portal data.
// Notifications are addressed by stable local account ID/login.
function notifyAccountChanges(oldValue,newValue){
 let before,after;try{before=JSON.parse(oldValue||'[]');after=JSON.parse(newValue||'[]')}catch(_){return}
 if(!Array.isArray(before)||!Array.isArray(after))return;
 const me=current(), oldBy=new Map(before.map(u=>[uid(u),u]));
 after.forEach(u=>{const id=uid(u),old=oldBy.get(id);if(!old)return;
  const name=u.name||u.fullName||'Ваша учётная запись';
  if(old.role!==u.role)push(id,'role','Изменена роль','Ваша роль изменена: '+(old.role||'не указана')+' → '+(u.role||'не указана')+'.','profile.html');
  if(old.status!==u.status){const active=String(u.status||'').toLowerCase().includes('актив');push(id,'role',active?'Учётная запись активирована':'Изменён статус аккаунта',active?'Доступ к кабинету восстановлен.':'Статус учётной записи изменён: '+(u.status||'не указан')+'.','profile.html')}
  if(old.password&&u.password&&old.password!==u.password)push(id,'security','Пароль изменён','Пароль вашей учётной записи был изменён. Если это сделали не вы, обратитесь к администратору.','settings.html');
 });
}
function summarizeData(oldValue,newValue,key){
 try{
  const a=JSON.parse(oldValue||'null'),b=JSON.parse(newValue||'null');
  if(!a||!b||typeof a!=='object'||typeof b!=='object')return;
  const currentUser=current();if(!currentUser)return;
  const name=String(currentUser.name||'').toLowerCase(), login=String(currentUser.login||'').toLowerCase(), id=uid(currentUser);
  const findRecipient=(obj)=>{const raw=[obj.userId,obj.memberId,obj.participantId,obj.authorId,obj.assigneeId,obj.assignedTo,obj.login,obj.username,obj.email,obj.user,obj.author,obj.participant,obj.assignee].filter(v=>v!=null).map(v=>typeof v==='object'?String(v.id??v.login??v.name??'').toLowerCase():String(v).toLowerCase());return raw.some(v=>v===id.toLowerCase()||v===login||v===name||v.includes(login))}
  const arrays=[];
  function walk(x,y,path,depth){if(depth>3||!x||!y||typeof x!=='object'||typeof y!=='object')return;
    for(const k of new Set([...Object.keys(x),...Object.keys(y)])){const xv=x[k],yv=y[k],p=path?path+'.'+k:k;
      if(Array.isArray(xv)&&Array.isArray(yv)){const oldMap=new Map(xv.map((it,i)=>[String(it?.id??it?.reportId??it?.taskId??it?.title??i),it]));yv.forEach((it,i)=>{const keyId=String(it?.id??it?.reportId??it?.taskId??it?.title??i),old=oldMap.get(keyId);if(!old){if(findRecipient(it))arrays.push({kind:'new',p,it});return}
        if(findRecipient(it)){for(const field of ['status','state','points','score','assignedTo','assignee','deadline','dueDate','comment','reviewComment','achievement'])if(old[field]!==it[field])arrays.push({kind:field,p,it,old,value:it[field]})}
      });}
      else if(xv&&yv&&typeof xv==='object'&&typeof yv==='object')walk(xv,yv,p,depth+1)
    }
  }
  walk(a,b,'',0);
  arrays.slice(0,8).forEach(ev=>{const p=ev.p.toLowerCase(),v=String(ev.value??'');
   let type='info',title='Обновление портала',message='Изменились данные, связанные с вами.',link='';
   if(/report|отчет|заяв/.test(p)){type='report';title=ev.kind==='new'?'Новое событие по отчёту':'Изменился статус отчёта';message=ev.kind==='new'?'Для вас добавлена запись, связанная с отчётом.':'Изменение отчёта: '+v;link='participant.html#reports'}
   else if(/task|задач/.test(p)){type='task';title=ev.kind==='new'?'Назначена новая задача':'Изменена задача';message=ev.kind==='new'?'Вам назначена новая задача.':'Изменение задачи: '+v;link='moderator-tasks.html'}
   else if(/rating|point|score|балл/.test(p)){type='points';title='Изменились баллы';message='Обновлены ваши баллы: '+v;link='participant.html#rating'}
   else if(/event|achievement|достиж/.test(p)){type='points';title='Новое достижение или событие';message='Появилось обновление, связанное с вашими достижениями.';link='participant.html#events'}
   push(id,type,title,message,link);
  });
 }catch(_){}
}
const nativeSet=Storage.prototype.setItem;
if(!window.__msNotifStorageHook){window.__msNotifStorageHook=true;Storage.prototype.setItem=function(key,value){let old=null;try{old=this.getItem(key)}catch(_){}const result=nativeSet.call(this,key,value);if(this===localStorage&&old!==String(value)){if(key===USERS)notifyAccountChanges(old,String(value));if(key==='adminConceptDemo'||key==='ratingData'||key==='ms_events_v1')summarizeData(old,String(value),key)}return result}}
})();