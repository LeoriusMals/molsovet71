(()=>{'use strict';
const aliases={
'Арсеньевский район':'Арсеньево','Белёвский район':'Белёв','Богородицкий район':'Богородицк',
'Венёвский район':'Венёв','Воловский район':'Волово','Дубенский район':'Дубна',
'Заокский район':'Заокский','Каменский район':'Каменский','Кимовский район':'Кимовск',
'Киреевский район':'Киреевск','Куркинский район':'Куркино','Тёпло-Огарёвский район':'Тёплое',
'Узловский район':'Узловая','Чернский район':'Чернь','Щёкинский район':'Щёкино',
'Плавский район':'Плавск','Суворовский район':'Суворов','Одоевский район':'Одоев',
'Ясногорский район':'Ясногорск'
};
const canonical=v=>typeof v==='string'?(v==='Ленинский район'?'':(aliases[v]||v)):v;
function read(k){try{return JSON.parse(localStorage.getItem(k)||'null')}catch(_){return null}}
function write(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(_){}}
const status=read('ms_municipality_status_v1');
if(status&&typeof status==='object'&&!Array.isArray(status)){
 const next={...status};
 Object.keys(aliases).forEach(old=>{if(next[old]){const target=aliases[old];next[target]={...next[old],...(next[target]||{})};delete next[old]}});
 delete next['Ленинский район'];
 write('ms_municipality_status_v1',next);
}
function migrateObject(value){
 if(Array.isArray(value)){value.forEach(migrateObject);return}
 if(!value||typeof value!=='object')return;
 Object.keys(value).forEach(k=>{
  if(k==='municipality'&&typeof value[k]==='string')value[k]=canonical(value[k]);
  else if(k==='municipalities'&&Array.isArray(value[k]))value[k]=value[k].map(canonical);
  else migrateObject(value[k]);
 });
}
['ms_local_auth_users_v1','adminConceptDemo','ratingData'].forEach(k=>{const v=read(k);if(v){migrateObject(v);write(k,v)}});
})();